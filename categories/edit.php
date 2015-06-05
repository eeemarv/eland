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

$cats = $db->GetAssoc('SELECT id, * FROM categories ORDER BY fullname');

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
		$error_list["name"]= 'Vul naam in!';	
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

$h1 = 'Categorie aanpassen : ' . $cat['name'];

include($rootpath."includes/inc_header.php");

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo '<form method="post" class="form-horizontal">';

echo '<div class="form-group">';
echo '<label for="name" class="col-sm-2 control-label">Naam</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="name" name="name" ';
echo 'value="'. $cat["name"] . '" required>';
echo '</div>';
if (isset($error_list["name"]))
{
	echo '<p class="danger">' .$error_list['name'] . '</p>';
}
echo '</div>';

echo '<div class="form-group">';
echo '<label for="id_parent" class="col-sm-2 control-label">Hoofdcategorie of deelcategorie van</label>';
echo '<div class="col-sm-10">';
echo '<select class="form-control" id="id_parent" name="id_parent">';
$parent_cats = array(0 => '-- Hoofdcategorie --');
$parent_cats += $db->GetAssoc('SELECT id, name FROM categories WHERE leafnote = 0 ORDER BY name');
$id_parent = ($cat['id_parent']) ? $cat['id_parent'] : 0;

render_select_options($parent_cats, $id_parent);
echo '</select>';
echo '</div>';
echo '</div>';

echo '<a href="' .$rootpath . 'categories/overview.php" class="btn btn-default">Annuleren</a>&nbsp;';
echo '<input type="submit" value="Opslaan" name="zend" class="btn btn-primary">';

echo '</form>';

echo '</div>';
echo '</div>';

include $rootpath . 'includes/inc_footer.php';
