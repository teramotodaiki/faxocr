# -*- coding: utf-8 -*-
<?php
/*
 * Shinsai FaxOCR
 *
 * Copyright (C) 2009-2013 National Institute of Public Health, Japan.
 * All rights Reserved.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 */

require_once 'config.php';
require_once 'init.php';
require_once 'lib/common.php';
require_once 'lib/file_conf.php';
require_once 'lib/sheet_marker.php';
require_once 'contrib/peruser.php';

define('sht_config', true);

$msg = "start\n";

//
// ファイルハンドリング
//
if (isset($file_id) && $file_id) {
	// $tgt_file = DST_DIR . $file_id . ".xls";
	$tgt_file = DST_DIR . $file_id . ORIG_EXT;
} else {
	print "不正なアクセスです\n";
	// put_err_page("不正なアクセスです");
	die;
}

// Excelファイル読み込み処理
if ($tgt_file) {

	$xls = NEW Excel_Peruser;
	$xls->setErrorHandling(1);
	$xls->setInternalCharset($charset);
	$result = $xls->fileread($tgt_file);

	if ($xls->isError($result)) {
		$errmsg = $result->getMessage();
		$xls = null;
	}
}

put_config($file_id, $_REQUEST);
get_config($file_id);
put_rails($file_id);

//
// HTMLファイル作成処理
//
if ($xls) {
	$html = put_header();
	$html .= put_css($xls);
	$html .= put_excel($xls);
	$html .= put_footer();
	file_put_contents(DST_DIR . $file_id . ".html", $html);

	$msg .= "put_excel\n";
}

// XXX: debugging purpose
/*
file_put_contents("/tmp/faxocr.log",
  "----------------------------------------\n" .
  date("Y/m/d H:i:s") . "\n(" .
  count($_REQUEST) . ")\n" .
  $msg . "\n\n",
  FILE_APPEND | LOCK_EX
);
*/

die;

function get_config($file_id)
{
	global $field_index;
	global $conf;

	//
	// 設定情報読込
	//
	if (!$conf)
		$conf = new FileConf($file_id);

	$xls_fields_list = $conf->array_getall("field");

	$num = 0; // XXX: needed?

	foreach ($xls_fields_list as $xls_fields) {
		$location = $xls_fields["sheet_num"] . "-" . $xls_fields["row"] . "-" . $xls_fields["col"];
		$field_index[$location] = $location;
		if (isset($xls_fields["colspan"])) {
			for($i=1; $i<$xls_fields["colspan"]; $i++) {
				$col = $xls_fields["col"]+$i;
				$location = $xls_fields["sheet_num"] . "-" . $xls_fields["row"] . "-" . $col;
				$field_index[$location] = $location;
			}
		}
	}

	return $field_index;
}

//
// config出力
//
function put_config($file_id, $REQUEST)
{
	global $group_id;
	global $sheet_id;
	global $conf;
	global $xls;
	global $sheet_marker;

	$conf = new FileConf($file_id);

	if (isset($group_id))
		$conf->set("gid", $group_id);

	if (isset($sheet_id))
		$conf->set("sid", $sheet_id);

	foreach (array("block_width", "block_height", "block_size", "block_offsetx",
								 "block_offsety", "candidate_code") as $item) {
		if (isset($REQUEST[$item])) {
			$val = $REQUEST[$item];
			if (strlen($val) != 0) {
				$conf->set($item, $val);
			}
		}
	}

    $sheet_marker = new SheetMarker($xls, $conf->get("block_width"), $conf->get("block_height"), $conf->get("block_offsetx"), $conf->get("block_offsety"), $conf->get("block_size"));

	$sn = 0;

	$list_of_cell_size = array();
	foreach (range(0, $xls->maxcell[$sn]) as $i) {
		array_push($list_of_cell_size, floor($xls->getColWidth($sn, $i) * $sheet_marker->marker_scale * $sheet_marker->scale));
	}
	$val = var_export_1line($list_of_cell_size);
	$conf->set("cell_width", $val);

	$list_of_cell_size = array();
	foreach (range(0, $xls->maxrow[$sn]) as $i) {
		array_push($list_of_cell_size, floor($xls->getRowHeight($sn, $i) * $sheet_marker->marker_scale * $sheet_marker->scale));
	}
	$val = var_export_1line($list_of_cell_size);
	$conf->set("cell_height", $val);

	$conf->commit();
}

function php_hash_to_ruby_hash($str)
{
	$result = preg_replace("/^array\(/", "{", $str);
	return preg_replace("/,\)$/", "}", $result);
}

function var_export_1line($val)
{
	return preg_replace("/[ \n]/", "", var_export($val, TRUE));
}

function put_header()
{
	$html = <<< STR
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta http-equiv="Content-Style-Type" content="text/css" />
  <meta http-equiv="Content-Script-Type" content="text/javascript" />
  <title>Shinsai FaxOCR</title>
</head>
<body>

STR;
	return $html;
}

function put_footer()
{
	$html .= "</body>\n";
	$html .= "</html>\n";

	return $html;
}

function put_excel($xls)
{
	global $field_index;
	global $conf;
	global $debug_mode;

    $sheet_marker = new SheetMarker($xls, $conf->get("block_width"), $conf->get("block_height"), $conf->get("block_offsetx"), $conf->get("block_offsety"), $conf->get("block_size"));

	$sid = sprintf("%05d", $conf->get("sid"));
	$cid = strtok($conf->get("candidate_code"), "-");

	// シート表示
	// for ($sn = 0; $sn < 1; $sn++) {
	$sn = 0;

	while ($cid != FALSE) {
		$cid = sprintf("%05d", $cid);

		// 調査シートHTML
		$html .= "\n<hr style=\"page-break-after:always; visibility:hidden;\">\n\n";
		$html .= "<div id=\"ex3\" class=\"jqDnR\" style=\"z-index: 3; position: relative; width: " . $sheet_marker->disp_sheet->sheet_width . "px; height: " . $sheet_marker->disp_sheet->sheet_height . "px; font-size: 12px; padding: 0px; border: 0px; margin: 0px; \">\n";

		// marker: top - left
		$html .= "<img src=\"/home/faxocr/etc/mark.gif\" class=\"mark-img\" style=\"position: absolute; top: " . $sheet_marker->disp_sheet->position_top_marker . "px; left: " . $sheet_marker->disp_sheet->position_left_marker . "px; width: " . $sheet_marker->disp_sheet->size_of_marker . "px; height: " . $sheet_marker->disp_sheet->size_of_marker . "px; z-index: 100; \">";
		// sheet ID: top
		$html .= "<div style=\"position: absolute; top: " . $sheet_marker->disp_sheet->position_top_marker . "px; left: " . $sheet_marker->disp_sheet->position_of_sheet_id_from_left_side . "px; z-index: 100; \"><font style=\"line-height: " . $sheet_marker->disp_sheet->size_of_marker * 0.9 . "px; font-size: " . $sheet_marker->disp_sheet->size_of_marker * 0.9 . "px; font-family: 'OCRB'; \">" . $cid . "</font></div>\n";

		// marker: top - right
		$html .= "<img src=\"/home/faxocr/etc/mark.gif\" class=\"mark-img\" style=\"position: absolute; top: " . $sheet_marker->disp_sheet->position_top_marker . "px; left: " . $sheet_marker->disp_sheet->position_right_marker . "px; width: " . $sheet_marker->disp_sheet->size_of_marker . "px; height: " . $sheet_marker->disp_sheet->size_of_marker . "px; z-index: 100; \">\n";

		// marker: bottom - left
		$html .= "<img src=\"/home/faxocr/etc/mark.gif\" class=\"mark-img\" style=\"position: absolute; top: " . $sheet_marker->disp_sheet->position_bottom_marker . "px; left: " . $sheet_marker->disp_sheet->position_left_marker . "px; width: " . $sheet_marker->disp_sheet->size_of_marker . "px; height: " . $sheet_marker->disp_sheet->size_of_marker . "px; z-index: 100; \">";
		// sheet ID: bottom
		$html .= "<div style=\"position: absolute; top: " . ($sheet_marker->disp_sheet->position_bottom_marker + $sheet_marker->disp_sheet->size_of_marker * 0.1) . "px; left: " . $sheet_marker->disp_sheet->position_of_sheet_id_from_left_side . "px; z-index: 100; \"><font style=\"line-height: " . $sheet_marker->disp_sheet->size_of_marker * 0.9 . "px; font-size: " . $sheet_marker->disp_sheet->size_of_marker * 0.9 . "px; font-family: 'OCRB'; \">" . $sid . "</font></div>\n";

		$cid = strtok("-");

		// debug
/*
		$html .= "<div style='position: relative; z-index: 100;'>";
		$html .= "scale: " . $sheet_marker->scale . "<br />";
		$html .= "marker_scale: " . $sheet_marker->marker_scale . "<br />";
		$html .= "tablewidth: " . $sheet_marker->disp_sheet->tblwidth . "<br />";
		$html .= "tableheight: " . $sheet_marker->disp_sheet->tblheight . "<br />";
		$html .= "row: " . $sheet_marker->row_count . "<br />";
		$html .= "row_size(1): " . $sheet_marker->get_row_size(1) . "<br />";
		$html .= "sheet_width: " . $sheet_marker->disp_sheet->sheet_width . "<br />";
		$html .= "sheet_height: " . $sheet_marker->disp_sheet->sheet_height . "<br />";
		$html .= "marker_offset_x: " . $sheet_marker->disp_sheet->marker_offset_x . "<br />";
		$html .= "marker_offset_y: " . $sheet_marker->disp_sheet->marker_offset_y . "<br />";
		$html .= "size_of_marker: " . $sheet_marker->disp_sheet->size_of_marker . "<br />";
		$html .= "position_of_sheet_id_from_left_side: " . $sheet_marker->disp_sheet->position_of_sheet_id_from_left_side . "<br />";
		$html .= "position_top_marker: " . $sheet_marker->disp_sheet->position_top_marker . "<br />";
		$html .= "position_left_marker: " . $sheet_marker->disp_sheet->position_left_marker . "<br />";
		$html .= "position_bottom_marker: " . $sheet_marker->disp_sheet->position_bottom_marker . "<br />";
		$html .= "position_right_marker: " . $sheet_marker->disp_sheet->position_right_marker . "<br />";
		$html .= "</div>";
*/

		// シートテーブル表示		
		$html .= "<table class=\"sheet\" border=\"0\" cellpadding=\"0\"";
		$html .= " cellspacing=\"0\" width=\"" . $sheet_marker->disp_sheet->tblwidth . "px\" height=\"" . $sheet_marker->disp_sheet->tblheight . "px\"";
		$html .= " style=\"table-layout: fixed; border-collapse: collapse; table-layout: fixed; position: absolute; top: " . $sheet_marker->disp_sheet->offset_top_table . "px; left: " . $sheet_marker->disp_sheet->offset_left_table . "px; z-index: 10; \"";
		$html .=" bgcolor=\"#FFFFFF\" >\n";

		for ($r = 0; $r <= $sheet_marker->row_count; $r++) {
			$trheight = $sheet_marker->disp_sheet->get_row_size($r);
			$html .= "  <tr height=\"" . $trheight . "px\">" . "\n";

			for ($i = 0; $i <= $sheet_marker->col_count; $i++) {
				$tdwidth  = $sheet_marker->disp_sheet->get_col_size($i);

				$dispval = $xls->dispcell($sn, $r, $i);
				$dispval = strconv($dispval);
				if (isset($xls->hlink[$sn][$r][$i])){
					$dispval = "<a href=\"" . $xls->hlink[$sn][$r][$i] . "\">" . $dispval . "</a>";
				}

				$xf = $xls->getAttribute($sn, $r, $i);
				if (isset($xf['wrap']) && $xf['wrap'])
					$dispval = preg_replace('/\n/', '<br />', $dispval);
				$xfno = ($xf['xf'] > 0) ? $xf['xf'] : 0;

				$align = "x";
				if (isset($xf['halign']) && $xf['halign'] != 0)
					$align= "";
				if ($align == "x") {
					if ($xf['type'] == Type_RK) $align = " Align=\"right\"";
					else if ($xf['type'] == Type_RK2) $align = " Align=\"right\"";
					else if ($xf['type'] == Type_NUMBER) $align = " Align=\"right\"";
					else if ($xf['type'] == Type_FORMULA && is_numeric($dispval)) $align = " Align=\"right\"";
					else if ($xf['type'] == Type_FORMULA2 && is_numeric($dispval)) $align = " Align=\"right\"";
					else if ($xf['type'] == Type_FORMULA && ($dispval=="TRUE" || $dispval=="FALSE")) $align = " Align=\"center\"";
					else if ($xf['type'] == Type_FORMULA2 && ($dispval=="TRUE" || $dispval=="FALSE")) $align = " Align=\"center\"";
					else if ($xf['type'] == Type_BOOLERR) $align = " Align=\"center\"";
					else $align= '';
					if ($xf['format'] == "@") $align = "";
				} else {
					$align = "";
				}

				if (substr($dispval,0,1) == "'") $dispval = substr($dispval, 1);
				if (substr($dispval,0,6) == "&#039;") $dispval = substr($dispval, 6);

				// セル表示
				$bgcolor = ($xf['fillpattern'] == 1);
				$loc = $sn . "-" . $r . "-" . $i;
				if (isset($field_index[$loc])) {
					$dispval = "";
				}

				$celattr =  $xls->getAttribute($sn, $r, $i);
				$fontsize =  $celattr["font"]["height"] * $sheet_marker->marker_scale * $sheet_marker->scale / 16;
				if (isset($xls->celmergeinfo[$sn][$r][$i]['cond'])) {
					if ($xls->celmergeinfo[$sn][$r][$i]['cond'] == 1) {
						$colspan = $xls->celmergeinfo[$sn][$r][$i]['cspan'];
						$rowspan = $xls->celmergeinfo[$sn][$r][$i]['rspan'];
						if ($colspan > 1) {
							$rcspan = " colspan=\"" . $colspan . "\"";
						} else {
							$rcspan = " width=\"" . $tdwidth . "\"";
						}

						if ($rowspan > 1)
							$rcspan .= " rowspan=\"" . $rowspan . "\"";
						$class = " class=\"XFs" . $sn . "r" . $r . "c" . $i . "\"";
						$id = " id=\"". $sn . "-" . $r ."-" . $i . "\"";
						$html .= " <td $class $rcspan $align style=\"font-size: " . $fontsize . "px;\">$dispval</td>\n";
					}
				} else {
					$class = " class=\"XF" . $xfno . "\" ";
					$id = " id=\"". $sn . "-" . $r . "-" . $i . "\"";
					$html .= " <td nowrap=\"nowrap\" $class $align style=\"font-size: " . $fontsize . "px;\">$dispval</td>\n";
				}
			}
			$html .= "</tr>\n";
		}
		$html .= "</table>\n";
		$html .= "</div>\n";
	}  // while ($cid != FALSE)

	return $html;
}

//
// Railsスクリプトの作成
//
function put_rails($file_id)
{
	global $rails_env;
	global $conf;

	$tgt_items = "";

	if (!$conf)
		$conf = new FileConf($file_id);

	$size = $conf->get("block_size") ? $conf->get("block_size") : 32;
	$sheet_name = $conf->get("name");
	eval('$no_of_columns = count(' . $conf->get("cell_width")  . ') - 2;');
	eval('$no_of_rows    = count(' . $conf->get("cell_height") . ') - 2;');
	$cell_width_ruby = php_hash_to_ruby_hash($conf->get("cell_width"));
	$cell_height_ruby = php_hash_to_ruby_hash($conf->get("cell_height"));
	$target = $conf->get("target") == "registered" ? 1 : 0;

	//
	// XLSフィールド情報取得
	//
	$xls_fields_list = $conf->array_getall("field");
	$fields_list = array();
	foreach ($xls_fields_list as $fields) {
		$col = $fields["col"];
		$row = $fields["row"];
		$id = $row * 100 + $col;
		$fields_list += array($id => $fields);
	}
	ksort($fields_list);

	$cnt = 0;
	foreach ($fields_list as $fields) {
		$cspan = isset($fields["colspan"]) ? $fields["colspan"] : 1;
		$name = $fields["item_name"];
		$col = $fields["col"];
		$row = $fields["row"];
		$type = $fields["type"];
		$cnt++;
		$tgt_items .= "props << [\"{$name}\", " .
			      	         "\"{$name}\", " .
			      	         "{$cnt}, " .
			      	         "\"{$type}\", " .
			      	         "{$col}, " .
			      	         "{$row}, " .
			      	         "{$cspan}]\n";
	}

	// カラム数補正
	$tmp_no_of_columns = $no_of_columns + 1;
	$tmp_no_of_rows = $no_of_rows + 1;

	$tgt_script = <<< "STR"
#!/usr/bin/ruby
# -*- coding: utf-8 -*-

require "rubygems"
require "active_record"
require "yaml"

rails_prefix = ARGV[0] || "./"
group = ARGV[1] || exit(0)
filename = "{$sheet_name}" || "自動生成調査" # XXX

config_db = rails_prefix + "/config/database.yml"
db_env = "{$rails_env}" || "development" # XXX
cellinfo = Hash.new()

ActiveRecord::Base.configurations = YAML.load_file(config_db)
ActiveRecord::Base.establish_connection(db_env)

Dir.glob(rails_prefix + '/app/models/*.rb').each do |model|
  load model
end

# Initialization
@group = Group.find(group)

#
# create a default candidate (hardcoded)
#
@candidate = @group.candidates.build
@candidate.candidate_code = "00000" 		# (string, not null)
@candidate.candidate_name = "一般報告者"	# (string, not null)
@candidate.group_id = group			# (int, not null)
@candidate.tel_number = "03-1111-1111"		# (string)
@candidate.fax_number = "03-1111-1111"		# (string)
if @candidate.save
  print "default candidate: success\\n"
else
  print "default candidate: fail\\n"
  @candidate = Candidate.find_by_candidate_code(@candidate.candidate_code)
end

#
# create a new survey
#
@survey = @group.surveys.build
@survey.survey_name = filename
@survey.status = 1 # 0: close, 1: open
@survey.report_header = ""
@survey.report_footer = ""

if {$target} > 0 then
  @candidates = @group.candidates
  @candidates.each do |candidate|
    survey_candidate = SurveyCandidate.new
    survey_candidate.candidate_id = candidate.id
    survey_candidate.role = 'sr'
    if (candidate.id) then
      @survey.survey_candidates << survey_candidate
    end
  end
else
  survey_candidate = SurveyCandidate.new
  survey_candidate.candidate_id = @candidate.id
  survey_candidate.role = 'sr'
  if (@candidate.id) then
    @survey.survey_candidates << survey_candidate
  end
end

if @survey.save
  print "survey candidate: success\\n"
else
  print "survey candidate: fail\\n"
  exit(0)
end
survey_id = @survey.id

#
# create survey properties
#

#
# [Property]
#
# [0] survey_property.ocr_name
# [1] survey_property.ocr_name_full
# [2] survey_property.view_order
# [3] survey_property.data_type
# [4] sheet_property.position_x
# [5] sheet_property.position_y
# [6] sheet_property.colspan

# XXX
props = []
{$tgt_items}

props.each do |prop|

  # object building
  @survey_property = @survey.survey_properties.build
  @survey_property.survey_id = survey_id	# integer
  @survey_property.ocr_name = prop[0]		# string (must be unique!)
  @survey_property.ocr_name_full = prop[1]	# string
  @survey_property.view_order = prop[2]		# integer
  @survey_property.data_type = prop[3]		# string
  # print prop[0] + "/" + prop[1] + "\\n"

  # save
  if @survey_property.save
    print  "survey property " + prop[0] + ": success\\n"
    cellinfo[@survey_property.id] = [prop[4], prop[5], prop[6]]
  else
    print  "survey property " + prop[0] + ": fail\\n"
    exit(0)
  end
end

#
# create sheet/property mapping
#
@sheet = @survey.sheets.build

# sheet作成
# @sheet.sheet_code = survey_id.to_s # string
@sheet.sheet_code = "%05d" % {$conf->get("sid")}
@sheet.sheet_name = "自動生成シート" # string
@sheet.survey_id = survey_id # integer
@sheet.block_width = {$tmp_no_of_columns} || 0 # XXX
@sheet.block_height = {$tmp_no_of_rows} || 0 # XXX
@sheet.cell_width = "$cell_width_ruby" || 0 # XXX
@sheet.cell_height = "$cell_height_ruby" || 0 # XXX
@sheet.status = 1
# save
if @sheet.save
  print  "sheet: save success\\n"
else
  print  "sheet: save fail\\n"
  exit(0)
end

#
# sheet_property generation
#
survey_properties = @survey.survey_properties
survey_properties.each do |@survey_property|

  prop = cellinfo[@survey_property.id]
  if prop.nil? then
    next
  end
  # survey_propertyからコピー
  sheet_property = SheetProperty.new
  sheet_property.sheet_id = @sheet.object_id
  sheet_property.survey_property_id = @survey_property.id
  sheet_property.position_x = prop[0]
  sheet_property.position_y = prop[1]
  sheet_property.colspan = prop[2]
  @sheet.sheet_properties << sheet_property

  # print "> " + prop[0].to_s + "/" + prop[1].to_s + "/" + prop[2].to_s + "\\n"
end

# save
if @sheet.save
  print  "sheet_property: save success\\n"
else
  print  "sheet_property: save fail\\n"
  exit(0)
end

exit(0)


STR;

	// ファイル生成
	file_put_contents(DST_DIR . $file_id . ".rb", $tgt_script);
}

?>
