<?php

$page_access = 'admin';
require_once __DIR__ . '/include/web.php';

$tschema = $app['this_group']->get_schema();

$edit = $_GET['edit'] ?? false;
$del = $_GET['del'] ?? false;
$add = $_GET['add'] ?? false;

if ($add)
{
	$cat = [];

	if (isset($_POST['zend']))
	{
		$cat['name'] = $_POST['name'];
		$cat['id_parent'] = $_POST['id_parent'];
		$cat['leafnote'] = ($_POST['id_parent'] == 0) ? 0 : 1;

		if (!isset($cat['name'])|| (trim($cat['name']) == ''))
		{
			$errors[] = 'Vul naam in!';
		}

		if (strlen($cat['name']) > 40)
		{
			$errors[] = 'De naam mag maximaal 40 tekens lang zijn.';
		}

		if (!isset($cat['id_parent'])|| (trim($cat['id_parent']) == ''))
		{
			$errors[] = 'Vul hoofdrubriek in!';
		}

		if ($token_error = $app['form_token']->get_error())
		{
			$errors[] = $token_error;
		}

		if (!count($errors))
		{
			$cat['cdate'] = date('Y-m-d H:i:s');
			$cat['id_creator'] = ($s_master) ? 0 : $s_id;
			$cat['fullname'] = '';

			if ($cat['leafnote'])
			{
				$cat['fullname'] .= $app['db']->fetchColumn('select name
					from ' . $tschema . '.categories
					where id = ?', [(int) $cat['id_parent']]);
				$cat['fullname'] .= ' - ';
			}

			$cat['fullname'] .= $cat['name'];

			if ($app['db']->insert($tschema . '.categories', $cat))
			{
				$app['alert']->success('Categorie toegevoegd.');
				cancel();
			}

			$app['alert']->error('Categorie niet toegevoegd.');
		}
		else
		{
			$app['alert']->error($errors);
		}
	}

	$parent_cats = [0 => '-- Hoofdcategorie --'];

	$rs = $app['db']->prepare('select id, name
		from ' . $tschema . '.categories
		where leafnote = 0 order by name');

	$rs->execute();

	while ($row = $rs->fetch())
	{
		$parent_cats[$row['id']] = $row['name'];
	}

	$id_parent = $cat['id_parent'] ?? 0;

	$h1 = 'Categorie toevoegen';
	$fa = 'clone';

	include __DIR__ . '/include/header.php';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<form  method="post">';

	echo '<div class="form-group">';
	echo '<label for="name" class="control-label">';
	echo 'Naam</label>';
	echo '<div class="input-group">';
	echo '<span class="input-group-addon">';
	echo '<span class="fa fa-clone"></span></span>';
	echo '<input type="text" class="form-control" ';
	echo 'id="name" name="name" ';
	echo 'value="';
	echo $cat['name'] ?? '';
	echo '" required maxlength="40">';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="id_parent" class="control-label">';
	echo 'Hoofdcategorie of deelcategorie van</label>';
	echo '<select name="id_parent" id="id_parent" class="form-control">';
	echo get_select_options($parent_cats, $id_parent ?? 0);
	echo '</select>';
	echo '</div>';

	echo aphp('categories', [], 'Annuleren', 'btn btn-default');
	echo '&nbsp;';
	echo '<input type="submit" name="zend" value="Toevoegen" ';
	echo 'class="btn btn-success">';
	echo $app['form_token']->get_hidden_input();

	echo '</form>';

	echo '</div>';
	echo '</div>';

	include __DIR__ . '/include/footer.php';
	exit;
}

if ($edit)
{
	$cats = [];

	$rs = $app['db']->prepare('select *
		from ' . $tschema . '.categories
		order by fullname');

	$rs->execute();

	while ($row = $rs->fetch())
	{
		$cats[$row['id']] = $row;
	}

	$child_count_ary = [];

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
			$app['alert']->error('Vul naam in!');
		}
		else if (($cat['stat_msgs_wanted'] + $cat['stat_msgs_offers']) && !$cat['leafnote'])
		{
			$app['alert']->error('Hoofdcategoriën kunnen
				geen berichten bevatten.');
		}
		else if ($cat['leafnote'] && $child_count_ary[$edit])
		{
			$app['alert']->error('Subcategoriën kunnen
				geen categoriën bevatten.');
		}
		else if ($token_error = $app['form_token']->get_error())
		{
			$app['alert']->error($token_error);
		}
		else
		{
			$prefix = '';

			if ($cat['id_parent'])
			{
				$prefix .= $app['db']->fetchColumn('select name
					from ' . $tschema . '.categories
					where id = ?', [$cat['id_parent']]) . ' - ';
			}

			$cat['fullname'] = $prefix . $cat['name'];
			unset($cat['id']);

			if ($app['db']->update($tschema . '.categories', $cat, ['id' => $edit]))
			{
				$app['alert']->success('Categorie aangepast.');
				$app['db']->executeUpdate('update ' . $tschema . '.categories
					set fullname = ? || \' - \' || name
					where id_parent = ?', [$cat['name'], $edit]);
				cancel();
			}

			$app['alert']->error('Categorie niet aangepast.');
		}
	}

	$parent_cats = [0 => '-- Hoofdcategorie --'];

	$rs = $app['db']->prepare('select id, name
		from ' . $tschema . '.categories
		where leafnote = 0
		order by name');

	$rs->execute();

	while ($row = $rs->fetch())
	{
		$parent_cats[$row['id']] = $row['name'];
	}

	$id_parent = $cat['id_parent'] ?? 0;


	$h1 = 'Categorie aanpassen : ' . $cat['name'];
	$fa = 'clone';

	include __DIR__ . '/include/header.php';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<form method="post">';

	echo '<div class="form-group">';
	echo '<label for="name" class="control-label">';
	echo 'Naam</label>';
	echo '<div class="input-group">';
	echo '<span class="input-group-addon">';
	echo '<span class="fa fa-clone"></span></span>';
	echo '<input type="text" class="form-control" ';
	echo 'id="name" name="name" ';
	echo 'value="';
	echo $cat["name"] ?? '';
	echo '" required>';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="id_parent" class="control-label">';
	echo 'Hoofdcategorie of deelcategorie van</label>';
	echo '<select class="form-control" id="id_parent" name="id_parent">';
	echo get_select_options($parent_cats, $id_parent);
	echo '</select>';
	echo '</div>';

	echo aphp('categories', [], 'Annuleren', 'btn btn-default');
	echo '&nbsp;';
	echo '<input type="submit" value="Opslaan" ';
	echo 'name="zend" class="btn btn-primary">';
	echo $app['form_token']->get_hidden_input();

	echo '</form>';

	echo '</div>';
	echo '</div>';

	include __DIR__ . '/include/footer.php';
	exit;
}

if ($del)
{
	if(isset($_POST['zend']))
	{
		if ($error_token = $app['form_token']->get_error())
		{
			$app['alert']->error($error_token);
			cancel();
		}

		if ($app['db']->delete($tschema . '.categories', ['id' => $del]))
		{
			$app['alert']->success('Categorie verwijderd.');
			cancel();
		}

		$app['alert']->error('Categorie niet verwijderd.');
	}

	$fullname = $app['db']->fetchColumn('select fullname
		from ' . $tschema . '.categories
		where id = ?', [$del]);

	$h1 = 'Categorie verwijderen : ' . $fullname;
	$fa = 'clone';

	include __DIR__ . '/include/header.php';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo "<p><font color='#F56DB5'><strong>Ben je zeker dat deze categorie";
	echo " moet verwijderd worden?</strong></font></p>";
	echo '<form method="post">';

	echo aphp('categories', [], 'Annuleren', 'btn btn-default');
	echo '&nbsp;';
	echo '<input type="submit" value="Verwijderen" ';
	echo 'name="zend" class="btn btn-danger">';
	echo $app['form_token']->get_hidden_input();
	echo '</form>';

	echo '</div>';
	echo '</div>';

	include __DIR__ . '/include/footer.php';
	exit;
}

$cats = $app['db']->fetchAll('select *
	from ' . $tschema . '.categories
	order by fullname');

$child_count_ary = [];

foreach ($cats as $cat)
{
	if (!isset($child_count_ary[$cat['id_parent']]))
	{
		$child_count_ary[$cat['id_parent']] = 0;
	}

	$child_count_ary[$cat['id_parent']]++;
}

$top_buttons .= aphp('categories', ['add' => 1], 'Toevoegen', 'btn btn-success', 'Categorie toevoegen', 'plus', true);

$h1 = 'Categorieën';
$fa = 'clone';

include __DIR__ . '/include/header.php';

echo '<div class="panel panel-default printview">';

echo '<div class="table-responsive">';
echo '<table class="table table-striped table-hover ';
echo 'table-bordered footable" data-sort="false">';
echo '<tr>';
echo '<thead>';
echo '<th>Categorie</th>';
echo '<th data-hide="phone">Vraag</th>';
echo '<th data-hide="phone">Aanbod</th>';
echo '<th data-hide="phone">Verwijderen</th>';
echo '</tr>';
echo '</thead>';

echo '<tbody>';

http://x.e.loc/messages.php?f%5Bq%5D=&f%5Bcid%5D=5&f%5Btype%5D%5Boffer%5D=on&f%5Bvalid%5D%5Byes%5D=on&f%5Bvalid%5D%5Bno%5D=on&f%5Bustatus%5D%5Bactive%5D=on&f%5Bustatus%5D%5Bnew%5D=on&f%5Bustatus%5D%5Bleaving%5D=on&f%5Bfcode%5D=&f%5Bs%5D=Toon&view=list&orderby=m.cdate&asc=0&limit=25&r=admin&u=1

$messages_param_ary = [
	'f'	=> [
		's'		=> '1',
		'valid'	=> ['yes' => 'on', 'no' => 'on'],
		'ustatus'	=> ['active' => 'on', 'new' => 'on', 'leaving' => 'on'],
	],
	'view'	=> $view_messages,
];

foreach($cats as $cat)
{
	$count_wanted = $cat['stat_msgs_wanted'];
	$count_offers = $cat['stat_msgs_offers'];
	$count = $count_wanted + $count_offers;

	if (isset($child_count_ary[$cat['id']]))
	{
		$count += $child_count_ary[$cat['id']];
	}

	if (!$cat['id_parent'])
	{
		echo '<tr class="info">';
		echo '<td><strong>';
		echo aphp('categories', ['edit' => $cat['id']], $cat['name']);
		echo '</strong></td>';
	}
	else
	{
		echo '<tr>';
		echo '<td>';
		echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
		echo aphp('categories', ['edit' => $cat['id']], $cat['name']);
		echo '</td>';
	}

	echo '<td>';

	if ($count_wanted)
	{
		$param_ary = array_merge_recursive($messages_param_ary, [
			'f'	=> [
				'cid'	=> $cat['id'],
				'type'	=> [
					'want'	=> 'on',
				],
			],
		]);

		echo aphp('messages', $param_ary, $count_wanted);
	}

	echo '</td>';
	echo '<td>';

	if ($count_offers)
	{
		$param_ary = array_merge_recursive($messages_param_ary, [
			'f'	=> [
				'cid'	=> $cat['id'],
				'type'	=> [
					'offer'	=> 'on',
				],
			],
		]);

		echo aphp('messages', $param_ary, $count_offers);
	}

	echo '</td>';

	echo '<td>';

	if (!$count)
	{
		echo aphp('categories', ['del' => $cat['id']], 'Verwijderen', 'btn btn-danger btn-xs', false, 'times');
	}

	echo '</td>';
	echo '</tr>';
}

echo '</tbody>';
echo '</table>';
echo '</div></div>';

echo '<p><ul><li>Categorieën met berichten ';
echo 'of hoofdcategorieën met subcategorieën kan je niet verwijderen.</li>';
echo '<li>Enkel subcategorieën kunnen berichten bevatten.</li></ul>';
echo '</p>';

include __DIR__ . '/include/footer.php';

function cancel():void
{
	header('Location: ' . generate_url('categories'));
	exit;
}
