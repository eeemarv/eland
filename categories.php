<?php
ob_start();
$rootpath = './';
$role = 'admin';
require_once $rootpath . 'includes/inc_default.php';

$edit = ($_GET['edit']) ?: false;
$del = ($_GET['del']) ?: false;
$add = ($_GET['add']) ?: false;

if ($add)
{
	$cat = array();

	if (isset($_POST['zend']))
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
				header('Location: ' . $rootpath . 'categories.php');
				exit;
			}

			$alert->error('Categorie niet toegevoegd.');
		}
		else
		{
			$alert->error(implode('<br>', $errors));
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
	$fa = 'clone';

	include $rootpath . 'includes/inc_header.php';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<form  method="post" class="form-horizontal">';

	echo '<div class="form-group">';
	echo '<label for="name" class="col-sm-2 control-label">Naam</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="name" name="name" ';
	echo 'value="' . $cat['name'] . '" required>';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="id_parent" class="col-sm-2 control-label">Hoofdcategorie of deelcategorie van</label>';
	echo '<div class="col-sm-10">';
	echo '<select name="id_parent" id="id_parent" class="form-control">';
	render_select_options($parent_cats, $id_parent);
	echo '</select>';
	echo '</div>';
	echo '</div>';

	echo '<a href="' . $rootpath . 'categories.php" class="btn btn-default">Annuleren</a>&nbsp;';
	echo '<input type="submit" name="zend" value="Toevoegen" class="btn btn-success">';
	echo '</form>';

	echo '</div>';
	echo '</div>';

	include $rootpath . 'includes/inc_footer.php';
	exit;
}

if ($edit)
{
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

	$cat = $cats[$edit];

	if(isset($_POST['zend'])){

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
		else if ($cat['leafnote'] && $child_count_ary[$edit])
		{
			$alert->error('Subcategoriën kunnen geen categoriën bevatten.');
		}
		else
		{
			$prefix = ($cat['id_parent']) ? $db->fetchColumn('SELECT name FROM categories WHERE id = ?', array($cat['id_parent'])) . ' - ' : '';
			$cat['fullname'] = $prefix . $cat['name'];
			unset($cat['id']);

			if ($db->update('categories', $cat, array('id' => $edit)))
			{
				$alert->success('Categorie aangepast.');
				$db->executeUpdate('UPDATE categories SET fullname = ? || \' - \' || name WHERE id_parent = ?', array($cat['name'], $edit));
				header('Location: ' . $rootpath . 'categories.php');
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
	$fa = 'clone';

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

	echo '<a href="' .$rootpath . 'categories.php" class="btn btn-default">Annuleren</a>&nbsp;';
	echo '<input type="submit" value="Opslaan" name="zend" class="btn btn-primary">';

	echo '</form>';

	echo '</div>';
	echo '</div>';

	include $rootpath . 'includes/inc_footer.php';
	exit;
}

if ($del)
{
	if(isset($_POST['zend']))
	{
		if ($db->delete('categories', array('id' => $del)))
		{
			$alert->success('Categorie verwijderd.');
			header('Location: ' . $rootpath . 'categories.php');
			exit;
		}

		$alert->error('Categorie niet verwijderd.');
	}

	$fullname = $db->fetchColumn('SELECT fullname FROM categories WHERE id = ?', array($del));

	$h1 = 'Categorie verwijderen : ' . $fullname;
	$fa = 'clone';

	include $rootpath . 'includes/inc_header.php';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo "<p><font color='#F56DB5'><strong>Ben je zeker dat deze categorie";
	echo " moet verwijderd worden?</strong></font></p>";
	echo '<form method="POST">';
	echo '<a href="' . $rootpath . 'categories.php" class="btn btn-default">Annuleren</a>&nbsp;';

	echo '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger">';
	echo '</form>';

	echo '</div>';
	echo '</div>';

	include $rootpath . 'includes/inc_footer.php';
	exit;
}

$cats = $db->fetchAll('SELECT * FROM categories ORDER BY fullname');

$child_count_ary = array();

foreach ($cats as $cat)
{
	$child_count_ary[$cat['id_parent']]++;
}

$top_buttons = '<a href="' . $rootpath . 'categories.php?add=1" class="btn btn-success"';
$top_buttons .= ' title="Categorie toevoegen"><i class="fa fa-plus"></i>';
$top_buttons .= '<span class="hidden-xs hidden-sm"> Toevoegen</span></a>';

$h1 = 'Categorieën';
$fa = 'clone';

include $rootpath . 'includes/inc_header.php';

echo '<div class="table-responsive">';
echo '<table class="table table-striped table-hover table-bordered footable" data-sort="false">';
echo '<tr>';
echo '<thead>';
echo '<th>Categorie</th>';
echo '<th data-hide="phone">Vraag</th>';
echo '<th data-hide="phone">Aanbod</th>';
echo '<th data-hide="phone">Verwijderen</th>';
echo '</tr>';
echo '</thead>';

echo '<tbody>';

foreach($cats as $cat)
{
	$count_wanted = $cat['stat_msgs_wanted'];
	$count_offers = $cat['stat_msgs_offers'];
	$count = $count_wanted + $count_offers;
	$count += $child_count_ary[$cat['id']];

	if (!$cat['id_parent'])
	{
		echo '<tr class="info">';
		echo '<td><strong><a href="' . $rootpath . 'categories.php?edit=' . $cat['id'] . '">';
		echo htmlspecialchars($cat['name'],ENT_QUOTES);
		echo '</a></strong></td>';
	}
	else
	{
		echo '<tr>';
		echo '<td>';
		echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
		echo '<a href="' . $rootpath . 'categories.php?edit=' . $cat['id'] . '">';
		echo htmlspecialchars($cat['name'],ENT_QUOTES);
		echo '</a></td>';
	}

	echo '<td>' . (($count_wanted) ?: '') . '</td>';
	echo '<td>' . (($count_offers) ?: '') . '</td>';

	echo '<td>';
	if (!$count)
	{
		echo '<a href="' . $rootpath . 'categories.php?del=' . $cat['id'] . '" class="btn btn-danger btn-xs">';
		echo '<i class="fa fa-times"></i> Verwijderen</a>';
	}
	echo '</td>';
	echo '</tr>';
}

echo '</tbody>';
echo '</table>';
echo '</div>';

echo '<p>Categorieën met berichten of hoofdcategorieën met subcategorieën kan je niet verwijderen.</p>';

include $rootpath . 'includes/inc_footer.php';

