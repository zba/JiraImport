<?php
require_once( 'core.php' );
require_once( 'email_api.php' );
auth_reauthenticate();
access_ensure_global_level( config_get( 'manage_user_threshold' ) );
$jira= new mysqli(
	plugin_config_get('jira_db_host'),
	plugin_config_get('jira_db_user'),
	plugin_config_get('jira_db_password'),
	plugin_config_get('jira_db_name'));
if ($jira->connect_errno) {
    echo "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
}
$jira->set_charset("utf8");
//USERS
$user_map=array();
$query="SELECT ID,user_name,first_name,last_name,email_address from cwd_user";
$res = $jira->prepare($query);
	$res->execute();
	$res->store_result();
	$res->bind_result($ID,$f_username,$first_name,$last_name,$f_email);
if (!$res) {printf("Error: %s\n", $jira->error); exit;}
	$validate_email=config_get('validate_email');
	config_set('validate_email',OFF);
while($res->fetch()) {
	$f_realname=$first_name." ".$last_name;
	$f_password=auth_generate_random_password( $f_email . $f_username );
	//FIXME create option to select default access level
	$f_access_level=55;
	$f_protected=OFF;
	$f_enabled=ON;
	$t_realname = string_normalize( $f_realname );

	$id=user_get_id_by_name($f_username);
	if ($id!==false) {
		$user_map[$f_username]=$id;
		continue;
	}
	user_ensure_name_valid( $f_username );
	user_ensure_realname_valid( $t_realname );
	$f_email = email_append_domain( $f_email );
	email_ensure_not_disposable( $f_email );
	lang_push( config_get( 'default_language' ) );
	$t_admin_name = user_get_name( auth_get_current_user_id() );
	$t_cookie = user_create( $f_username, $f_password, $f_email, $f_access_level, $f_protected, $f_enabled, $t_realname, $t_admin_name );
	$user_map[$f_username]=user_get_id_by_name($f_username);
	lang_pop();
	
}
$res->free_result();
unset($res);
config_set('validate_email',$validate_email);

//PROJECTS

$query="SELECT ID,pkey from project where pname=?";
$res=$jira->prepare($query);
$res->bind_param("s",plugin_config_get('jira_project_name'));
$res->execute();
$res->store_result();
$res->bind_result($jira_project_id,$jira_project_key);
$res->fetch();
$res->close();
require_once('project_api.php');
$issue_map=array();
$mantis_project_id=project_get_id_by_name(plugin_config_get('mantis_project_name'));

//ISSUES
$processed_issues=array();
$priority_map=gpc_get_string_array('priority');
$resolution_map=gpc_get_string_array('resolution');
$status_map=gpc_get_string_array('status');
$relation_map=gpc_get_string_array('relation');
$attachments_path=gpc_get_string('attachments_path');
plugin_config_set('priority_map',$priority_map);
plugin_config_set('resolution_map',$resolution_map);
plugin_config_set('status_map',$status_map);
plugin_config_set('relation_map',$relation_map);
plugin_config_set('attachments_path',$attachments_path);


require_once('bug_api.php');

$query="SELECT ID,pkey,REPORTER,ASSIGNEE,issuetype,issuestatus,RESOLUTION,ENVIRONMENT,PRIORITY,SUMMARY,DESCRIPTION,UNIX_TIMESTAMP(CREATED),UNIX_TIMESTAMP(UPDATED),WATCHES FROM jiraissue WHERE PROJECT=$jira_project_id";
#echo "<br> $jira_project_id <br> $query <br>";
$res=$jira->prepare($query);
$res->execute();
$res->store_result();
$res->bind_result($ID,$pkey,$REPORTER,$ASSIGNEE,$issuetype,$issuestatus,$RESOLUTION,$ENVIRONMENT,$PRIORITY,$SUMMARY,$DESCRIPTION,$CREATED,$UPDATED,$WATCHES);
$keep_bug_nums=plugin_config_get('keep_bug_nums');
$keep_bug_nums=ON;
while ($res->fetch()) {
bug_create($ID,$pkey,$REPORTER,$ASSIGNEE,$issuetype,$issuestatus,$RESOLUTION,$ENVIRONMENT,$PRIORITY,$SUMMARY,$DESCRIPTION,$CREATED,$UPDATED,$WATCHES,$processed_issues,$jira,$jira_project_id,$mantis_project_id,$jira_project_key,$keep_bug_nums,$status_map,$priority_map,$resolution_map,$relation_map,$user_map,$issue_map);
	
}


$res->close();



//LABELS->TAGS
$label_map=array();
$query="select LABEL from label group by LABEL";
$res=$jira->prepare($query);
$res->execute();
$res->store_result();
$res->bind_result($LABEL);
while ($res->fetch()) {
	$tag=tag_get_by_name($LABEL);
	$label_map[$LABEL]=$tag?$tag['id']:tag_create($LABEL);	
}
$res->close();

$query="select LABEL,ISSUE from label";
$res=$jira->prepare($query);
$res->execute();
$res->store_result();
$res->bind_result($LABEL,$ISSUE);
while ($res->fetch()) {	
	if (!isset($issue_map[$ISSUE])) continue;
	$bug_id=$issue_map[$ISSUE];	
	tag_bug_attach($label_map[$LABEL],$bug_id);
}


$res->close();
//ATTACHES


$query="select ID,issueid,FILENAME,author,MIMETYPE,FILESIZE FROM fileattachment";
$res=$jira->prepare($query);
$res->execute();
$res->store_result();
$res->bind_result($ID,$ISSUE,$FILENAME,$author,$MIMETYPE,$FILESIZE);
while ($res->fetch()) {	
	if (!isset($issue_map[$ISSUE])) continue;
	$bug_id=$issue_map[$ISSUE];
	//echo "-- $attachments_path/$jira_project_key/$jira_project_key-$bug_id/$ID</br>";
	if (!is_file("$attachments_path/$jira_project_key/$jira_project_key-$bug_id/$ID")) continue;
	$tmp_name="/tmp/jira2matis_".rand(1000000,100000000000);
	//echo "$attachments_path/$jira_project_key/$jira_project_key-$bug_id/$ID</br>";
	copy("$attachments_path/$jira_project_key/$jira_project_key-$bug_id/$ID",$tmp_name);
	$p_file=array(
		'name'=>$FILENAME,
		'tmp_name'=>$tmp_name,
		'type'=>$MIMETYPE,
		'error'=>0,
		'size'=>$FILESIZE
	);
	file_add( $bug_id, $p_file,'bug', $p_title = '', '', $p_user_id = isset($user_map[$author])?$user_map[$author]:0 );
	unlink($tmp_name);
}
$res->close();
html_page_top1("Jira Import" );
html_page_top2();

print_manage_menu();
echo "Done";
html_page_bottom1( );


function bug_create(
	$ID,
	$pkey,
	$REPORTER,
	$ASSIGNEE,
	$issuetype,
	$issuestatus,
	$RESOLUTION,
	$ENVIRONMENT,
	$PRIORITY,
	$SUMMARY,
	$DESCRIPTION,
	$CREATED,
	$UPDATED,
	$WATCHES,
	&$processed_issues,
	&$jira,
	$jira_project_id,
	$mantis_project_id,
	$jira_project_key,
	$keep_bug_nums,
	$status_map,
	$priority_map,
	$resolution_map,
	$relation_map,
	$user_map,
	&$issue_map) {			
	
	$j_id=preg_replace("/^$jira_project_key-/",'',$pkey);	
	//TODO follow option of keep_bug_nums
   if (isset($processed_issues[$j_id])) return $j_id;
   
	$bug_data = new BugData;
	$bug_data->project_id=$mantis_project_id;
	$bug_data->reporter_id=isset($user_map[$REPORTER])?$user_map[$REPORTER]:0;
	$bug_data->handler_id=isset($user_map[$ASSIGNEE])?$user_map[$ASSIGNEE]:0;
	$bug_data->priority=$priority_map[$PRIORITY];
#	$bug_data->severity=NORMAL;
	$bug_data->status=$status_map[$issuestatus];
	if ($RESOLUTION) $bug_data->resolution=$resolution_map[$RESOLUTION];
	$bug_data->date_submitted=$CREATED;
	$bug_data->last_updated=$UPDATED;
	$bug_data->summary=$SUMMARY;
	$bug_data->description=$DESCRIPTION?$DESCRIPTION:"no desc";
	
	$bug_id=$bug_data->create();
	if ($keep_bug_nums==ON) {
		bug_set_id_update($bug_id,$j_id,$UPDATED);
		$bug_id=$j_id;
	}
	$processed_issues[$j_id]=1;
	bug_set_field($bug_id, 'date_submitted', $CREATED );
	bug_set_field($bug_id, 'last_updated', $UPDATED );
	$bug_map[$j_id]=$bug_id;
	$issue_map[$ID]=$bug_id;
	
	
	$query="SELECT issueid,AUTHOR,actionbody,CREATED,UPDATED FROM jiraaction where issueid=$ID AND actiontype='comment'";
	#echo "$query<br>";
	$ares=$jira->prepare($query);
	$ares->execute();
	$ares->store_result();
	$ares->bind_result($issueid,$AUTHOR,$actionbody,$A_CREATED,$A_UPDATED);
	while($ares->fetch()) {
		bugnote_add( $bug_id, $actionbody, $p_time_tracking = '0:00', $p_private = false, $p_type = BUGNOTE, $p_attr = '', $p_user_id = isset($user_map[$AUTHOR])?$user_map[$AUTHOR]:0, $p_send_email = FALSE, $p_log_history = TRUE);
		
	}
	$ares->close();
	unset($ares);	
	$query="SELECT LINKTYPE,DESTINATION FROM issuelink WHERE SOURCE=$ID";	
	$lres=$jira->prepare($query);
	$lres->execute();
   $lres->store_result();
   $lres->bind_result($LINKTYPE,$DESTINATION);
   while ($lres->fetch()) {
       $query="SELECT ID,pkey,REPORTER,ASSIGNEE,issuetype,issuestatus,RESOLUTION,ENVIRONMENT,PRIORITY,SUMMARY,DESCRIPTION,UNIX_TIMESTAMP(CREATED),UNIX_TIMESTAMP(UPDATED),WATCHES FROM jiraissue WHERE PROJECT=$jira_project_id AND ID=$DESTINATION";       
			$llres=$jira->prepare($query);
			$llres->execute();
			$llres->store_result();
			$llres->bind_result($lID,$lpkey,$lREPORTER,$lASSIGNEE,$lissuetype,$lissuestatus,$lRESOLUTION,$lENVIRONMENT,$lPRIORITY,$lSUMMARY,$lDESCRIPTION,$lCREATED,$lUPDATED,$lWATCHES);
			while ($llres->fetch()) {
				$newid=bug_create($lID,$lpkey,$lREPORTER,$lASSIGNEE,$lissuetype,$lissuestatus,$lRESOLUTION,$lENVIRONMENT,$lPRIORITY,$lSUMMARY,$lDESCRIPTION,$lCREATED,$lUPDATED,$lWATCHES,$processed_issues,$jira,$jira_project_id,$mantis_project_id,$jira_project_key,$keep_bug_nums,$status_map,$priority_map,$resolution_map,$relation_map,$user_map,$issue_map);
				relationship_add($bug_id,$newid,$relation_map[$LINKTYPE]);
			}
			$llres->close();
       
   }
   $lres->close();   
   return $bug_id;
	
}





function bug_set_id_update($from_id,$to_id,$updated='NOW()') {
#echo "($from_id,$to_id,$updated<br>";
	$t_bug_table = db_get_table( 'mantis_bug_table' );
    	$query = "UPDATE $t_bug_table
                                  SET id= " . db_param() . ",
				      last_updated=".db_param()."
                                  WHERE id=" . db_param();
        db_query_bound( $query, Array( $to_id, $updated,$from_id ) );

        bug_clear_cache( $to_id );
		
}
