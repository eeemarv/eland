<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_form.php");

$posted_list = array();

if (isset($_POST["zend"])){
	$posted_list["name"] = $_POST["name"];
	$posted_list["id_parent"] = $_POST["id_parent"];
	$posted_list["leafnote"] = ($_POST["id_parent"] == 0) ? 0 : 1;

	$error_list = array();
	if (!isset($posted_list["name"])|| (trim($posted_list["name"])=="")){
		$error_list["name"]="<font color='#F56DB5'>Vul <strong>naam</strong> in!</font>";
	}
	if (!isset($posted_list["id_parent"])|| (trim($posted_list["id_parent"])=="")){
	$error_list["id_parent"]="<font color='#F56DB5'>Vul <strong>hoofdrubriek</strong> in!</font>";
	}

	if (!count($error_list)){
		$posted_list["cdate"] = date("Y-m-d H:i:s");
		$posted_list["id_creator"] = $s_id;
		$posted_list["fullname"] = ($posted_list['leafnote']) ? $db->GetOne("SELECT name FROM categories WHERE id=". (int) $posted_list["id_parent"]) . ' - ' : '';
		$posted_list['fullname'] .= $posted_list["name"];

		if ($db->AutoExecute("categories", $posted_list, 'INSERT'))
		{
			$alert->success('Categorie toegevoegd.');
			header('Location: overview.php');
			exit;
		}

		$alert->error('Categorie niet toegevoegd.');
	}
	else
	{
		$alert->error('Fout in één of meerdere velden');
	}
}

include($rootpath."includes/inc_header.php");

echo "<h1>Categorie toevoegen</h1>";
echo "<div class='border_b'>";
echo "<form method='POST' action='add.php'>";
echo "<table class='data' cellspacing='0' cellpadding='0' border='0'>";
echo "<tr><td valign='top' align='right'>Naam </td><td>";
echo "<input type='text' name='name' size='30' required";
if (isset($posted_list["name"])){
	echo  " value ='".$posted_list["name"]."'>";
}
echo "</td><td>";
if(isset($error_list["name"])){
	echo $error_list["name"];
}
echo "</td></tr>";

echo "<tr><td valign='top' align='right'>Hoofdcategorie of deelcategorie van";
echo "<td valign='top'>";
echo "<select name='id_parent'>";

$parent_cats = array(0 => '-- Hoofdcategorie --');
$parent_cats += $db->GetAssoc('SELECT id, name FROM categories WHERE leafnote = 0 ORDER BY name');
$id_parent = ($posted_list['id_parent']) ? $posted_list['id_parent'] : 0;

render_select_options($parent_cats, $id_parent);

echo "</select>";
echo "</td><td>";
echo "</td></tr>";

echo "<tr><td></td><td>";
echo "<input type='submit' name='zend' value='Toevoegen'>";
echo "</td><td>&nbsp;</td></tr></table>";
echo "</form>";
echo "</p></div>";

include($rootpath."includes/inc_footer.php");
