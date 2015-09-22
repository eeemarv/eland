<?php
ob_start();
$rootpath = '../';
$role = 'admin';
require_once $rootpath . 'includes/inc_default.php';
require_once $rootpath . 'includes/inc_form.php';

if (!$_GET['id'])
{
	header('Location: overview.php');
}

$id = $_GET['id'];

$cats = array();

$rs = $db->prepare('SELECT id, * FROM categories ORDER BY fullname');

$rs->execute();

while ($row = $rs->fetch())
{
	$cats[$row['id']] = $row;
}

$child_count_ary = array();

foreach ($cats as $cat)
{
	$child_count_ary[$cat['id_parent']]++;
}

$cat = $cats[$id];

if(isset($_POST["zend"])){

	$cat['name'] = $_POST['name'];
	$cat['id_parent'] = $_POST['id_parent'];
	$cat['leafnote'] = ($_POST['id_parent'] == 0) ? 0 : 1;

	if (!$cat['name'])
	{
		$alert->error('Vul naam in!');	
	}
	else if (($cat['stat_msgs_wanted'] + $cat['stat_msgs_offers']) && !$cat['leafnote'])
	{
		$alert->error('Hoofdcategoriën kunnen geen berichten bevatten.');
	}
	else if ($cat['leafnote'] && $child_count_ary[$id])
	{
		$alert->error('Subcategoriën kunnen geen categoriën bevatten.');
	}
	else
	{
		$prefix = ($cat['id_parent']) ? $db->fetchColumn('SELECT name FROM categories WHERE id = ?', array($cat['id_parent'])) . ' - ' : '';
		$cat['fullname'] = $prefix . $cat['name'];
		unset($cat['id']);

		if ($db->update('categories', $cat, array('id' => $id)))
		{
			$alert->success('Categorie aangepast.');
			$db->executeUpdate('UPDATE categories SET fullname = ? || \' - \' || name WHERE id_parent = ?', array($cat['name'], $id));
			header('Location: overview.php');
			exit;
		}

		$alert->error('Categorie niet aangepast.');
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


$h1 = 'Categorie aanpassen : ' . $cat['name'];
$fa = 'files-o';

include $rootpath . 'includes/inc_header.php';

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo '<form method="post" class="form-horizontal">';

echo '<div class="form-group">';
echo '<label for="name" class="col-sm-2 control-label">Naam</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="name" name="name" ';
echo 'value="'. $cat["name"] . '" required>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="id_parent" class="col-sm-2 control-label">Hoofdcategorie of deelcategorie van</label>';
echo '<div class="col-sm-10">';
echo '<select class="form-control" id="id_parent" name="id_parent">';
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
