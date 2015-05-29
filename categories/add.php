<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_form.php");

$posted_list = array();

if (isset($_POST["zend"]))
{
	$posted_list["name"] = $_POST["name"];
	$posted_list["id_parent"] = $_POST["id_parent"];
	$posted_list["leafnote"] = ($_POST["id_parent"] == 0) ? 0 : 1;

	$error_list = array();
	if (!isset($posted_list["name"])|| (trim($posted_list["name"])==""))
	{
		$error_list["name"]="<font color='#F56DB5'>Vul <strong>naam</strong> in!</font>";
	}
	if (!isset($posted_list["id_parent"])|| (trim($posted_list["id_parent"])==""))
	{
		$error_list["id_parent"]="<font color='#F56DB5'>Vul <strong>hoofdrubriek</strong> in!</font>";
	}

	if (!count($error_list))
	{
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

$parent_cats = array(0 => '-- Hoofdcategorie --');
$parent_cats += $db->GetAssoc('SELECT id, name FROM categories WHERE leafnote = 0 ORDER BY name');
$id_parent = ($posted_list['id_parent']) ? $posted_list['id_parent'] : 0;

$h1 = 'Categorie toevoegen';

include $rootpath . 'includes/inc_header.php';

echo '<form  method="post" class="form-horizontal">';

echo '<div class="form-group">';
echo '<label for="name" class="col-sm-2 control-label">Van letscode</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="name" name="name" ';
echo 'value="' . $posted_list['name'] . '" required>';
if(isset($error_list["name"])){
	echo $error_list["name"];
}
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="id_parent" class="col-sm-2 control-label">Hoofdcategorie of deelcategorie van</label>';
echo '<div class="col-sm-10">';
echo '<select name="id_parent" id="id_parent" class="form-control">';
render_select_options($parent_cats, $id_parent);
echo '</select>';
if(isset($error_list["id_parent"])){
	echo $error_list["id_parent"];
}
echo '</div>';
echo '</div>';

echo '<a href="' . $rootpath . 'categories/overview.php" class="btn btn-default">Annuleren</a>&nbsp;';
echo '<input type="submit" name="zend" value="Toevoegen" class="btn btn-primary">';
echo '</form>';

echo '</div></div>';

include($rootpath."includes/inc_footer.php");
