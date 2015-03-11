<?php
ob_start();
$rootpath = "../";
$role = 'user';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

if (!isset($s_id) || $s_accountrole == "guest" || $s_accountrole == "interlets"){
	header('Location: ' . $rootpath . 'login.php');
}

$id = (isset($_GET["id"])) ? $_GET['id'] : 0;
$mode = $_GET["mode"];

if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
	$validity = $_POST["validity"];
	$vtime = count_validity($validity);
	$msg = array(
		'validity'		=> (int) $_POST["validity"],
		'vtime'			=> (int) $vtime,
		'content'		=> pg_escape_string($_POST["content"]),
		'description'	=> pg_escape_string($_POST["description"]),
		'msg_type'		=> (int) $_POST["msg_type"],
		'id_user'		=> (int) $_POST["id_user"],
		'id_category'	=> (int) $_POST["id_category"],
		'amount'		=> (int) $_POST["amount"],
		'units'			=> pg_escape_string($_POST["units"]),
	);

	$errors= validate_input($msg, $mode);

	if (count($errors))
	{
		foreach ($errors as $error)
		{
			$alert->add_error($error);
		}
	}
	else 
	{
		if ($mode == 'new')
		{
			insert_msg($msg);
			if ($id = $db->Insert_ID())
			{
				$alert->add_success('Vraag/aanbod toegevoegd.');
				header('Location: ' . $rootpath . 'messages/view.php?id=' . $id);
				exit;				
			}
			else
			{
				$alert->add_error('Fout bij het opslaan van vraag/aanbod');
			}
		}
		else 
		{
			if (update_msg($id, $msg))
			{
				$alert->add_success('Vraag/aanbod aangepast');
				header('Location: ' . $rootpath . 'messages/view.php?id=' . $id);
				exit;				
			}
			else
			{
				$alert->add_error('Fout bij het opslaan van aanpassing vraag/aanbod.');
			}
		}
	}
}
else if ($mode == 'edit' && $id)
{
	$msg = get_msg($id);
}
else if ($mode == 'new')
{
	$msg = array(
		'validity'		=> '2',
		'vtime'			=> (int) $vtime,
		'content'		=> '',
		'description'	=> '',
		'msg_type'		=> 1,
		'id_user'		=> $s_id,
		'id_category'	=> '',
		'amount'		=> '',
		'units'			=> '',
	);
}

include($rootpath."includes/inc_header.php");

if($mode == "new"){
	echo "<h1>Nieuw Vraag & Aanbod toevoegen</h1>";
} else {
	echo "<h1>Vraag & Aanbod aanpassen</h1>";
}

if($s_accountrole == 'admin'){
	echo "<strong>[admin mode]</strong><br>";
}

echo "<div class='border_b'><p>";
echo "<form method='post'>";

echo "<table  class='data'  cellspacing='0' cellpadding='0' border='0'>\n";
echo "<tr>\n<td align='right'>";
echo "V/A ";
echo "</td><td>";
echo "<select name='msg_type'>";
render_selector_options(array('1' => 'Aanbod', '0' => 'Vraag'), $msg['msg_type']);
echo "</select>";
echo "</td></tr>";

echo "<tr><td valign='top' align='right'>Wat </td><td>";
echo '<input type="text" name="content" size="30" value="' . $msg['content'] . '">';
echo "</td></tr>";

echo "<tr><td valign='top' align='right'>Omschrijving </td><td>";
echo "<textarea name='description' rows='4' cols='50'>";
echo $msg['description'];
echo "</textarea>";
echo "</td></tr>";

// Who selection is only for admins
if($s_accountrole == "admin"){
	$user_list = get_users();
	
	echo "<tr><td align='right'>";
	echo "Wie";
	echo "</td><td>";
	echo "<select name='id_user'>\n";
	render_selector_options($user_list, $msg['id_user']);
	echo "</select>\n";
	echo "</td>\n</tr>\n\n<tr><td></td>\n<td>";
	echo "</td>\n</tr>\n\n";
} else {
	echo "<input type='hidden' name='id_user' size='8'>";
}

echo "<tr><td align='right'>";
echo "Categorie ";
echo "</td>\n<td>";
echo "<select name='id_category'>\n";

$cat_list = get_cats();
render_selector_options($cat_list, $msg['id_category']);

echo "</select>\n";
echo "</td>\n</tr>";

echo "<tr>\n<td valign='top' align='right'>Geldigheid </td>\n";

echo "<td>";
echo '<input type="text" name="validity" size="4" value="' . $msg['validity'] . '"> maanden';
echo "</td>\n</tr>\n";

$currency = readconfigfromdb("currency");
echo "<tr><td valign='top' align='right'>Prijs </td>";
echo "<td>";
echo '<input type="text" name="amount" size="8" value="' . $msg['amount'] . '">' . $currency;
echo "</td>\n</tr>\n";

echo "<tr>\n<td valign='top' align='right'>Per </td>\n";
echo "<td>";
echo '<input type="text" name="units" value="' . $msg['units'] . '"> (uur, stuk, ...)';
echo "</td>\n</tr>\n";
echo "<tr><td></td><td>";
echo "<input type='submit' value='Opslaan' name='zend' id='zend'>";
echo "</td></tr>\n\n</table>\n\n";
echo "</form>";
echo "</p></div>";

/*
if($mode == "edit"){
	loadvalues($id);
} else {
	writecontrol("mode", "new");
	writecontrol("id_user", $s_id);

*/

////////////////////////////////////////////////////////////////////////////

function render_selector_options($option_ary, $selected)
{
	foreach ($option_ary as $key => $value)
	{
		echo '<option value="' . $key . '"';
		echo ($key == $selected) ? ' selected="selected"' : '';
		echo '>' . htmlspecialchars($value, ENT_QUOTES) . '</option>';
	}
}

// FIXME: Geldigheid wordt niet geladen....
/*
function loadvalues($msgid){
	$msg = get_msg($msgid);
	writecontrol("mode", "edit");
	writecontrol("id" , $msg["id"]);
	writecontrol("msg_type", $msg["msg_type"]);
	writecontrol("id_user", $msg["id_user"]);
	$category = $msg["id_category"];
	writecontrol("id_category", $category);
	writecontrol("content", $msg["content"]);
	writecontrol("description", $msg["Description"]);
	writecontrol("amount", $msg["amount"]);
	writecontrol("units", $msg["units"]);
}

function writecontrol($key,$value){
	$value = str_replace("\n", '\n', $value);
	$value = str_replace('"',"'",$value);
	echo "<script type=\"text/javascript\">document.getElementById('" .$key ."').value = \"" .$value ."\";</script>";
}
*/

function validate_input($msg){
	global $db;
	$error_list = array();
	if (empty($msg["content"]) || (trim($msg["content"]) == ""))
		$error_list["content"] = "<font color='#F56DB5'>Vul <strong>inhoud</strong> in!</font>";
		$query =" SELECT * FROM categories ";
		$query .=" WHERE  id = '".$msg["id_category"]."' ";
		$rs = $db->Execute($query);
    	$number = $rs->recordcount();
		if( $number == 0 ){
			$error_list["id_category"]="<font color='#F56DB5'>Categorie <strong>bestaat niet!</strong></font>";
	}

	$query = "SELECT * FROM users ";
	$query .= " WHERE id = '".$_POST["id_user"]."'" ;
	$query .= " AND status <> '0'" ;
	$rs = $db->Execute($query);
    $number2 = $rs->recordcount();

	if( $number2 == 0 ){
		$error_list["id_user"]="<font color='#F56DB5'>Gebruiker <strong>bestaat niet!</strong></font>";
	}
	return $error_list;

}

function count_validity($validity){
        $valtime = time() + ($validity*30*24*60*60);

        $vtime =  date("Y-m-d H:i:s",$valtime);
        return $vtime;
}

function update_msg($id, $posted_list){
    global $db;
    if(!empty($posted_list["validity"])){
    	$posted_list["validity"] = $posted_list["vtime"];
    } else {
		unset($posted_list["validity"]);
    }
    $posted_list["mdate"] = date("Y-m-d H:i:s");

    $posted_list['"Description"'] = $posted_list['description'];
    unset($posted_list['uuid'], $posted_list['vtime'], $posted_list['description']);

    return $db->AutoExecute('messages', $posted_list, 'UPDATE', 'id = ' . $id);
}

function insert_msg($posted_list){
    global $db;
	$posted_list["cdate"] = date("Y-m-d H:i:s");
    $posted_list["validity"] = $posted_list["vtime"];
    $posted_list['"Description"'] = $posted_list['description'];
    unset($posted_list['uuid'], $posted_list['vtime'], $posted_list['description']);

    return $db->AutoExecute('messages', $posted_list, 'INSERT');
}

function get_users(){
    global $db;
    $user_ary = array();
	$query = "SELECT id, name, letscode FROM users ";
	$query .= " WHERE status = 1 OR status = 2 order by letscode";
 	$users = $db->GetArray($query);
 	foreach ($users as $user)
 	{
		$user_ary[$user['id']] = $user['name'] . ' ' . $user['letscode'];
	}
	return $user_ary;
}

function get_cats(){
	global $db;
	$query = "SELECT id, fullname  FROM categories WHERE leafnote=1 order by fullname";
	return $db->GetAssoc($query);
}

function get_msg($id){
	global $db;
	$query = "SELECT * , messages.validity AS valdate ";
	$query .= " FROM messages WHERE id=" .$id ;
	return $db->GetRow($query);
}

include($rootpath."includes/inc_footer.php");
