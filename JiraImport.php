<?php
class JiraImportPlugin extends MantisPlugin {
    function register() {
        $this->name = 'JiraImport';    # Proper name of plugin
        $this->description = 'Import jira issues via mysql';    # Short description of the plugin
        $this->page = 'config_page';           # Default plugin page

        $this->version = '0.1';     # Plugin version string
        $this->requires = array(    # Plugin dependencies, array of basename => version pairs
            'MantisCore' => '=> 1.2.0',  #   Should always depend on an appropriate version of MantisBT
            );

        $this->author = 'Alexey Zbinyakov';         # Author/team name
        $this->contact = 'zbinyakov@gmail.com';        # Author/team e-mail address
        $this->url = '';            # Support webpage
    }
    function config() {
        return array(
            'jira_db_host' => '127.0.0.1',
	    'jira_db_user' => 'root',
	    'jira_db_password' => '',
	    'jira_db_name' => '',
            'keep_bug_nums' => OFF,
	    'jira_project_name' => '',
	    'mantis_project_name' => '',
	    'priority_map'=>'',
	    'resolution_map'=>'',
	    'status_map'=>'',
	    'relation_map'=>'',
	    'attachments_path'=>'/tmp/jira-attachments/',
        );
    }
}
