<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_form.php");

if (!$_GET["id"])
{
	header('Location: overview.php');
}

$id = $_GET['id'];

$cats = $db->GetArray('SELECT * FROM categories ORDER BY fullname');

$child_count_ary = array();

foreach ($cats as $cat)
{
	$child_count_ary[$cat['id_parent']]++;
}

$cat = $cats[$id];

if(isset($_POST["zend"])){

	$cat["name"] = $_POST["name"];
	$cat["id_parent"] = $_POST["id_parent"];
	$cat["leafnote"] = ($_POST["id_parent"] == 0) ? 0 : 1;

	$error_list = array();
	if (!(isset($cat["name"])|| (trim($cat["name"] )=="")))
	{
		$error_list["name"]="<font color='#F56DB5'>Vul <strong>naam</strong> in!</font>";	
	}
	if (($cat['stat_msgs_wanted'] + $cat['stat_msgs_offers']) && !$cat['leafnote'])
	{
		$error_list['a'] = 'a'; // display error in alert;
		$alert->error('Hoofdcategoriën kunnen geen berichten bevatten.');
	}
	if ($cat['leafnote'] && $child_count_ary[$id])
	{
		$error_list['b'] = 'b'; // display error in alert;
		$alert->error('Subcategoriën kunnen geen categoriën bevatten.');
	}
	
	if (!count($error_list)){
		$prefix = ($cat['id_parent']) ? $db->GetOne("SELECT name FROM categories WHERE id=" . $cat["id_parent"]) . ' - ' : '';
		$cat['fullname'] = $prefix . $cat['name'];
		$cat["fullname"] = ($cat['leafnote']) ? $db->GetOne("SELECT name FROM categories WHERE id=". (int) $cat["id_parent"]) . ' - ' : '';
		$cat['fullname'] .= $cat["name"];
		unset($cat['id']);
		if ($db->AutoExecute("categories", $cat, 'UPDATE', "id=$id"))
		{
			$alert->success('Categorie aangepast.');
			$db->Execute('UPDATE categories SET fullname = \'' . $cat['name'] . ' - \' || name WHERE id_parent = ' . $id);
			header('Location: overview.php');
			exit;
		}

		$alert->error('Categorie niet aangepast.');
	}
	else
	{
		$alert->error('Categorie niet aangepast.');
	}
}

include($rootpath."includes/inc_header.php");
echo '<h1>Categorie aanpassen : ' . $cat['name'] . '</h1>';

echo "<div class='border_b'><p>";
echo "<form action='edit.php?id=".$cat["id"]."' method='POST'>";
echo "<table class='data' cellspacing='0' cellpadding='0' border='0'>";
echo "<tr><td valign='top' align='right'>Naam </td><td>";
echo "<input type='text' name='name' size='60' ";
echo "value='". $cat["name"] . "'>";
echo "</td><td>";
if (isset($error_list["name"])){
	echo $error_list["name"];
}
echo "</td></tr>";

echo "<tr><td valign='top' align='right'>Hoofdcategorie of deelcategorie van";
echo "<td valign='top'>";
echo "<select name='id_parent'>";

$parent_cats = array(0 => '-- Hoofdcategorie --');
$parent_cats += $db->GetAssoc('SELECT id, name FROM categories WHERE leafnote = 0 ORDER BY name');
$id_parent = ($cat['id_parent']) ? $cat['id_parent'] : 0;

render_select_options($parent_cats, $id_parent);

echo "</select>";
echo "</td><td>";
echo "</td></tr>";

echo "<tr><td></td><td>";
echo "<input type='submit' value='Opslaan' name='zend'>";
echo "</td><td>&nbsp;</td></tr></table>";
echo "</form>";
echo "</p></div>";

include($rootpath."includes/inc_footer.php");
