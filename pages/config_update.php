<?php

auth_reauthenticate();
access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );

function maybe_set_option( $name, $value ) {
	if ( $value != plugin_config_get( $name ) ) {
		plugin_config_set( $name, $value );
	}
}

$options=array(
	"string"=>array(
		'jira_db_host',
		'jira_db_user',
		'jira_db_password',
		'jira_db_name',
		'jira_project_name',
		'mantis_project_name',
	),
	"bool"=>array('keep_bug_nums')
);

foreach ($options['string'] as $opt_name) {
	$value=gpc_get_string($opt_name);
	maybe_set_option($opt_name,$value);
}

foreach ($options['bool'] as $opt_name) {
	$value=gpc_get_bool($opt_name);
	maybe_set_option($opt_name,$value);
}

print_successful_redirect( plugin_page( 'import_prepare', true ) );
