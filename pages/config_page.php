<?php
auth_reauthenticate( );
access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );

html_page_top1("Jira Import" );
html_page_top2();

print_manage_menu();
?>

<br/>

<form action="<?php echo plugin_page( 'config_update' ) ?>" method="post">
<?php echo form_security_field( 'plugin_JiraInport_config_update' ) ?>

<label>Jira database host<br/><input name="jira_db_host" value="<?php echo plugin_config_get( 'jira_db_host' ) ?>"/></label><br/>
<label>Jira database user<br/><input name="jira_db_user" value="<?php echo plugin_config_get( 'jira_db_user' ) ?>"/></label><br/>
<label>Jira database password<br/><input name="jira_db_password" value="<?php echo plugin_config_get( 'jira_db_password' ) ?>"/></label><br/>
<label>Jira database name<br/><input name="jira_db_name" value="<?php echo plugin_config_get( 'jira_db_name' ) ?>"/></label><br/>
<label>Jira  project name<br/><input name="jira_project_name" value="<?php echo plugin_config_get( 'jira_project_name' ) ?>"/></label><br/>
<label>Mantis  project name<br/><input name="mantis_project_name" value="<?php echo plugin_config_get( 'mantis_project_name' ) ?>"/></label><br/>
<hr />
<label>Keep same bug numbers<input name="keep_bug_nums" value="<?php echo plugin_config_get('keep_bug_nums')==ON?'1" checked="checked':0 ?>" type="checkbox" /> </label><br>



<br/>
<label><input type="checkbox" name="reset"/> Reset</label>
<br/>
<input type="submit"/>

</form>
<?php
html_page_bottom1( );
