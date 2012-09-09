# -*- coding: utf-8 -*-
class SurveysController < ApplicationController
  before_filter :verify_group_authority
  before_filter :verify_survey_authority
  before_filter :verify_role, :except => [:index, :index_group, :show, :show_group]

  def verify_survey_authority
    super(:survey_id => params[:id])
  end
  def verify_role
    super('s')
  end

  # GET /surveys
  # GET /surveys.xml
  def index
    @group = Group.find(params[:group_id])
    @surveys = @group.surveys
    respond_to do |format|
      format.html # index.html.erb
      format.xml  { render :xml => @surveys }
    end
  end

  # GET /surveys/1
  # GET /surveys/1.xml
  def show
    @group = Group.find(params[:group_id])
    @survey = @group.surveys.find(params[:id])
    @survey_candidates = @survey.survey_candidates
    @survey_properties = @survey.survey_properties
    @sheets = @survey.sheets
    sheet_ids = @survey.sheet_ids
    datetime = DateTime.now
    datetime = datetime - 1
    date_begin = datetime.strftime("%Y/%m/%d %H:%M:%S")
    @answer_sheets = AnswerSheet.find_all_by_sheet_id(sheet_ids,
        :conditions => ['date >= ?', date_begin],
        :order => 'date desc')
    respond_to do |format|
      format.html # show.html.erb
      format.xml  { render :xml => @survey }
    end
  end

  # GET /surveys/new
  # GET /surveys/new.xml
  def new
    @group = Group.find(params[:group_id])
    @survey = @group.surveys.build
    respond_to do |format|
      format.html # new.html.erb
      format.xml  { render :xml => @survey }
    end
  end

  # GET /surveys/1/edit
  def edit
    @group = Group.find(params[:group_id])
    @survey = @group.surveys.find(params[:id])
    respond_to do |format|
      format.html # new.html.erb
    end
  end

  # POST /surveys
  # POST /surveys.xml
  def create
    @group = Group.find(params[:group_id])
    @survey = @group.surveys.build(params[:survey])
    @candidates = @group.candidates
    @candidates.each do |candidate|
      survey_candidate = SurveyCandidate.new
      survey_candidate.candidate_id = candidate.id
      survey_candidate.role = 'sr'
      @survey.survey_candidates << survey_candidate
    end
    @survey.report_wday = ""
    @survey.report_wday_by_array = params[:report_wday_by_array]

    if @survey.save
      redirect_to group_survey_url(@group, @survey)
    else
      render :action => "new"
    end
  end

  # PUT /surveys/1
  # PUT /surveys/1.xml
  def update
    @group = Group.find(params[:group_id])
    @survey = Survey.find(params[:id])
    survey_attr = params[:survey]
    survey_attr['report_wday_by_array'] = params[:report_wday_by_array]
    @survey.report_wday = ""
    #if @survey.update_attributes(params[:survey])
    if @survey.update_attributes(survey_attr)
      redirect_to group_survey_url(@group, @survey)
    else
      render :action => "edit"
    end
  end

  # DELETE /surveys/1
  # DELETE /surveys/1.xml
  def destroy
    @group = Group.find(params[:group_id])
    @survey = Survey.find(params[:id])
    sheet = Sheet.find_by_survey_id(params[:id])
    survey_candidate = SurveyCandidate.find_by_survey_id(params[:id])
    survey_property = SurveyProperty.find_by_survey_id(params[:id])
    if sheet != nil || survey_candidate != nil || survey_property != nil
      flash[:notice] = "このサーベイは使用されているため削除できません"
    else
      @survey.destroy
    end
    respond_to do |format|
      format.html { redirect_to group_surveys_path(@group) }
      format.xml  { head :ok }
    end
  end
end