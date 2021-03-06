
git "cluscore" do
  repository "https://github.com/cluscore/cluscore"
  destination "#{node[:faxocr][:home_dir]}/src/cluscore"
  action :sync
  user "faxocr"
  group "faxocr"
end

bash "compiling cluscore" do
  cwd "#{node[:faxocr][:home_dir]}/src/cluscore"
  user "faxocr"
  group "faxocr"
  code <<-EOH
    ./configure --prefix=/usr/local
    make
    EOH
end

bash "installing cluscore" do
  cwd "#{node[:faxocr][:home_dir]}/src/cluscore"
  code <<-EOH
    make install
    EOH
  not_if { ::File.exists?("/usr/local/lib/libcluscore.a") and ::File.mtime("/usr/local/lib/libcluscore.a") >= ::File.mtime("#{node[:faxocr][:home_dir]}/src/cluscore/cluscore/.libs/libcluscore.a") }
end

# vim:set expandtab shiftwidth=2 tabstop=2 softtabstop=2:
