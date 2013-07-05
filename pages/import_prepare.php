<?php
auth_reauthenticate( );
access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );

html_page_top1("Jira Import" );
html_page_top2('check your settings!');

print_manage_menu();

$options=array(
	"string"=>array(
		array('jira_db_host',"Jira database host"),
		array('jira_db_user',"Jira database user"),
		array('jira_db_password',"Jira database password"),
		array('jira_db_name',"Jira database name"),
		array('jira_project_name',"Jira project name"),
		array('mantis_project_name',"Mantis project name")
	),
	"bool"=>array(array('keep_bug_nums',"Keep bugs numbers"))
);
echo "<table>";
foreach ($options['string'] as $opt) {
	$name=$opt[0];
	$desc=$opt[1];
	$value=plugin_config_get($name);
	echo "<tr><td>$desc</td><td>$value</td></tr>";
}
foreach ($options['bool'] as $opt) {
	$name=$opt[0];
	$desc=$opt[1];
	$value=plugin_config_get($name);
	echo "<tr><td>$desc</td><td>".($value==ON?"ON":"OFF")."</td></tr>";
}
echo "</table>";

echo "<form method='POST' action=".plugin_page('process').">";

$jira= new mysqli(
	plugin_config_get('jira_db_host'),
	plugin_config_get('jira_db_user'),
	plugin_config_get('jira_db_password'),
	plugin_config_get('jira_db_name'));
if ($jira->connect_errno) {
    echo "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
}
$priority_map=plugin_config_get('priority_map');
$resolution_map=plugin_config_get('resolution_map');
$status_map=plugin_config_get('status_map');
$relation_map=plugin_config_get('relation_map');

$jira->set_charset("utf8");
$query="SELECT ID,pname from priority";
echo generate_map_table($jira,[NONE,LOW,NORMAL,HIGH,URGENT,IMMEDIATE],$query,"priority",$priority_map);

$query="SELECT ID,pname from resolution";
echo generate_map_table($jira,[OPEN,FIXED,REOPENED,UNABLE_TO_DUPLICATE,NOT_FIXABLE,DUPLICATE,NOT_A_BUG,SUSPENDED,WONT_FIX],$query,"resolution",$resolution_map);

$query="SELECT ID,pname from issuestatus";
echo generate_map_table($jira,[NEW_,FEEDBACK,ACKNOWLEDGED,CONFIRMED,ASSIGNED,RESOLVED,CLOSED],$query,"status",$status_map);

$query="SELECT ID,LINKNAME FROM issuelinktype";
echo generate_map_table($jira,[BUG_REL_NONE,BUG_REL_ANY,BUG_RELATED,BUG_DEPENDANT,BUG_BLOCKS,BUG_HAS_DUPLICATE],$query,"relation",$relation_map,
	function($default,$name) {
		ob_start();
		relationship_list_box($default,$name);
		return ob_get_clean();
		
	});


$attachments_path=plugin_config_get('attachments_path');
?>
<br/>
<hr/>


<label>Jira attachments path<input name="attachments_path" type="text" value="<?php echo $attachments_path ?>"></label>
(make it using command like: <br/>
<b>cp -r /home/atlassian/application-data/jira/data/attachments/ /tmp/jira-attachments/;chmod 0755 /tmp/jira-attachments/;</b><br/> as root), don't forget to remove the directory after migrate<br/>

<input type="submit" /> </form>


<?php
html_page_bottom1( );

function generate_map_table($jira,$mantis_items,$query,$map_type,$status_map,$map_function=false) {
	$res=$jira->prepare($query);
	$res->execute();
	$res->store_result();
	$res->bind_result($ID,$pname);
	$map_table='';
	$mantis_options='';
	if (!$map_function) 
		foreach ($mantis_items as $p) 
			$mantis_options.="<option value='$p'>".get_enum_element( $map_type,$p)."</option>";						

	$map_table.=	"<hr />$map_type map<br>".
			"<table>".
			"<tr><td>Jira</td><td>Mantis</td></tr>";
	$val='';
	while ($res->fetch()) {
		if (is_array($status_map)) $mantis_options=$map_function===false?select_option($mantis_options,$status_map[$ID]):$map_function($status_map[$ID],"{$map_type}[$ID]");
		if ($mantis_options=='') $mantis_options=$map_function($status_map[$ID],"{$map_type}[$ID]");
		$map_table.="<tr><td>$pname<td>".($map_function===false?"<select $val name='{$map_type}[$ID]'>$mantis_options</select>":$mantis_options)."</td></tr>";
		if ($map_function!==false) $mantis_options='';
	}
	$map_table.="</table>";

	$res->close();
	unset($res);
	return $map_table;
}




function select_option($option,$value) {
	$option=preg_replace('/ selected>/','>',$option);
	return preg_replace("/value='$value'>/","value='$value' selected>",$option);
}
