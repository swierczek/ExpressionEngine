require './bootstrap.rb'

feature 'Installer' do
  before :all do
    # Set the environment variable so reset_db does not import the database
    ENV['installer'] = 'true'

    @installer = Installer::Prepare.new
    @installer.enable_installer
    @installer.disable_rename
    @installer.replace_config
  end

  before :each do
    # Delete existing config and create a new one
    @config = File.expand_path('../../system/user/config/config.php')
    File.delete(@config) if File.exist?(@config)
    File.new(@config, 'w')
    FileUtils.chmod(0666, @config)

    @page = Installer::Base.new
    @page.load
    no_php_js_errors
  end

  after :all do
    # Delete the environment variable that overrode reset_db
    ENV.delete('installer')

    # Add the FALSE && back into boot.php
    @installer.disable_installer
    @installer.enable_rename
    @installer.revert_config
  end

  it 'loads' do
    @page.should have(0).inline_errors
    @page.install_form.all_there?.should == true
  end

  context 'when installing' do
    it 'installs successfully using 127.0.0.1 as the database host' do
      @page.install_form.db_hostname.set '127.0.0.1'
      @page.install_form.db_name.set $test_config[:db_name]
      @page.install_form.db_username.set $test_config[:db_username]
      @page.install_form.db_password.set $test_config[:db_password]
      @page.install_form.username.set 'admin'
      @page.install_form.email_address.set 'hello@ellislab.com'
      @page.install_form.password.set 'password'
      @page.install_form.install_submit.click

      no_php_js_errors
      @page.req_title.text.should eq 'Completed'
      @page.install_success.success_header.text.should match /ExpressionEngine (\d+\.\d+\.\d+) is now installed/
      @page.install_success.all_there?.should == true
    end

    it 'installs successfully using localhost as the database host' do
      @page.install_form.db_hostname.set 'localhost'
      @page.install_form.db_name.set $test_config[:db_name]
      @page.install_form.db_username.set $test_config[:db_username]
      @page.install_form.db_password.set $test_config[:db_password]
      @page.install_form.username.set 'admin'
      @page.install_form.email_address.set 'hello@ellislab.com'
      @page.install_form.password.set 'password'
      @page.install_form.install_submit.click

      no_php_js_errors
      @page.req_title.text.should eq 'Completed'
      @page.install_success.success_header.text.should match /ExpressionEngine (\d+\.\d+\.\d+) is now installed/
      @page.install_success.all_there?.should == true
    end
  end

  context 'when using invalid database credentials' do
    it 'shows an error with no database credentials' do
      @page.install_form.install_submit.click

      no_php_js_errors
      @page.install_form.all_there?.should == true
      @page.inline_errors.should have(6).items
    end

    it 'shows an error when using the incorrect database credentials' do
      @page.install_form.db_hostname.set 'nonsense'
      @page.install_form.db_name.set 'nonsense'
      @page.install_form.db_username.set 'nonsense'
      @page.install_form.username.set 'admin'
      @page.install_form.email_address.set 'hello@ellislab.com'
      @page.install_form.password.set 'password'
      @page.install_form.install_submit.click

      no_php_js_errors
      @page.install_form.all_there?.should == true
      @page.should have_error
      @page.error.text.should include 'Unable to connect to your database using the configuration settings you submitted.'
    end
  end

  context 'when using an invalid database prefix' do
    it 'shows an error when the database prefix is too long' do
      @page.execute_script("$('input[maxlength=30]').prop('maxlength', 80);")
      @page.install_form.db_prefix.set '1234567890123456789012345678901234567890'
      @page.install_form.install_submit.click
      @page.inline_errors.should have_at_least(1).items
      @page.has_inline_error(/This field cannot exceed \d+ characters in length./) == true
    end

    it 'shows an error when using invalid characters in the database prefix' do
      @page.install_form.db_prefix.set '<nonsense>'
      @page.install_form.install_submit.click
      @page.inline_errors.should have_at_least(1).items
      @page.has_inline_error('There are invalid characters in the database prefix.') == true
    end

    it 'shows an error when using exp_ in the database prefix' do
      @page.install_form.db_prefix.set 'exp_'
      @page.install_form.install_submit.click
      @page.inline_errors.should have_at_least(1).items
      @page.has_inline_error('The database prefix cannot contain the string "exp_".') == true
    end
  end

  context 'when using an invalid username' do
    it 'shows an error when using invalid characters' do
      @page.install_form.username.set 'non<>sense'
      @page.install_form.install_submit.click
      @page.inline_errors.should have_at_least(1).items
      @page.has_inline_error('Your username cannot use the following characters:') == true
    end

    it 'shows an error when using a too-short username' do
      @page.install_form.username.set '123'
      @page.install_form.install_submit.click
      @page.inline_errors.should have_at_least(1).items
      @page.has_inline_error('Your username must be at least 4 characters long') == true
    end

    it 'shows an error when using a too-long username' do
      @page.execute_script("$('input[maxlength=50]').prop('maxlength', 80);")
      @page.install_form.username.set '12345678901234567890123456789012345678901234567890123456789012345678901234567890'
      @page.install_form.install_submit.click
      @page.inline_errors.should have_at_least(1).items
      @page.has_inline_error(/Your username cannot be over \d+ characters in length/) == true
    end
  end

  context 'when using an invalid email address' do
    it 'shows an error when no domain is supplied' do
      @page.install_form.email_address.set 'nonsense'
      @page.install_form.install_submit.click
      @page.inline_errors.should have_at_least(1).items
      @page.has_inline_error('This field must contain a valid email address') == true
    end

    it 'shows an error when no tld is supplied' do
      @page.install_form.email_address.set 'nonsense@example'
      @page.install_form.install_submit.click
      @page.inline_errors.should have_at_least(1).items
      @page.has_inline_error('This field must contain a valid email address') == true
    end

    it 'shows an error when no username is supplied' do
      @page.install_form.email_address.set 'example.com'
      @page.install_form.install_submit.click
      @page.inline_errors.should have_at_least(1).items
      @page.has_inline_error('This field must contain a valid email address') == true
    end
  end

  context 'when using an invalid password' do
    it 'shows an error when the password is too short' do
      @page.install_form.password.set '123'
      @page.install_form.install_submit.click
      @page.inline_errors.should have_at_least(1).items
      @page.has_inline_error(/Your password must be at least \d+ characters long/) == true
    end

    it 'shows an error whent he password is too long' do
      @page.execute_script("$('input[maxlength=72]').prop('maxlength', 80);")
      @page.install_form.password.set '12345678901234567890123456789012345678901234567890123456789012345678901234567890'
      @page.install_form.install_submit.click
      @page.inline_errors.should have_at_least(1).items
      @page.has_inline_error(/Your password cannot be over \d+ characters in length/) == true
    end

    it' shows an error when the username and password are the same' do
      @page.install_form.username.set 'nonsense'
      @page.install_form.password.set 'nonsense'
      @page.install_form.install_submit.click
      @page.inline_errors.should have_at_least(1).items
      @page.has_inline_error('The password cannot be based on the username') == true
    end
  end
end
