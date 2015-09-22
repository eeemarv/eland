<?php
ob_start();
$rootpath = '../';
$role = 'admin';
require_once $rootpath . 'includes/inc_default.php';
require_once $rootpath . 'includes/inc_form.php';

$cat = array();

if (isset($_POST["zend"]))
{
	$cat['name'] = $_POST['name'];
	$cat['id_parent'] = $_POST['id_parent'];
	$cat['leafnote'] = ($_POST['id_parent'] == 0) ? 0 : 1;

	$errors = array();
	if (!isset($cat['name'])|| (trim($cat['name']) == ''))
	{
		$errors[] = 'Vul naam in!';
	}
	if (!isset($cat['id_parent'])|| (trim($cat['id_parent']) == ''))
	{
		$errors[] = 'Vul hoofdrubriek in!';
	}

	if (!count($errors))
	{
		$cat['cdate'] = date('Y-m-d H:i:s');
		$cat['id_creator'] = $s_id;
		$cat['fullname'] = ($cat['leafnote']) ? $db->fetchColumn('SELECT name FROM categories WHERE id = ?', array((int) $cat['id_parent'])) . ' - ' : '';
		$cat['fullname'] .= $cat['name'];

		if ($db->insert('categories', $cat))
		{
			$alert->success('Categorie toegevoegd.');
			header('Location: ' . $rootpath . 'categories/overview.php');
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

$rs = $db->prepare('SELECT id, name FROM categories WHERE leafnote = 0 ORDER BY name');

$rs->execute();

while ($row = $rs->fetch())
{
	$parent_cats[$row['id']] = $row['name'];
}

$id_parent = ($cat['id_parent']) ? $cat['id_parent'] : 0;

$h1 = 'Categorie toevoegen';
$fa = 'files-o';

include $rootpath . 'includes/inc_header.php';

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo '<form  method="post" class="form-horizontal">';

echo '<div class="form-group">';
echo '<label for="name" class="col-sm-2 control-label">Naam</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="name" name="name" ';
echo 'value="' . $cat['name'] . '" required>';
if(isset($errors["name"])){
	echo $errors["name"];
}
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="id_parent" class="col-sm-2 control-label">Hoofdcategorie of deelcategorie van</label>';
echo '<div class="col-sm-10">';
echo '<select name="id_parent" id="id_parent" class="form-control">';
render_select_options($parent_cats, $id_parent);
echo '</select>';
if(isset($errors["id_parent"])){
	echo $errors["id_parent"];
}
echo '</div>';
echo '</div>';

echo '<a href="' . $rootpath . 'categories/overview.php" class="btn btn-default">Annuleren</a>&nbsp;';
echo '<input type="submit" name="zend" value="Toevoegen" class="btn btn-success">';
echo '</form>';

echo '</div>';
echo '</div>';

include $rootpath . 'includes/inc_footer.php';
