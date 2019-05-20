<?php

$app['page_access'] = 'admin';
require_once __DIR__ . '/include/web.php';

$edit = $_GET['edit'] ?? false;
$del = $_GET['del'] ?? false;
$add = isset($_GET['add']) ? true : false;

if ($add)
{
	if (isset($_POST['zend']))
	{
		if ($error_token = $app['form_token']->get_error())
		{
			$app['alert']->error($error_token);

			$app['link']->redirect('type_contact', $app['pp_ary'], []);
		}

		$tc = [];
		$tc['name'] = $_POST['name'];
		$tc['abbrev'] = $_POST['abbrev'];

		$error = (empty($tc['name'])) ? 'Geen naam ingevuld! ' : '';
		$error .= (empty($tc['abbrev'])) ? 'Geen afkorting ingevuld! ' : $error;

		if (!$error)
		{
			if ($app['db']->insert($app['tschema'] . '.type_contact', $tc))
			{
				$app['alert']->success('Contact type toegevoegd.');
			}
			else
			{
				$app['alert']->error('Fout bij het opslaan');
			}

			$app['link']->redirect('contact_types', $app['pp_ary'], []);
		}

		$app['alert']->error('Corrigeer één of meerdere velden.');
	}

	$app['h1']->add('Contact type toevoegen');
	$app['h1']->fa('circle-o-notch');

	include __DIR__ . '/include/header.php';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';
	echo '<form method="post">';

	echo '<div class="form-group">';
	echo '<label for="name" class="control-label">';
	echo 'Naam</label>';
	echo '<div class="input-group">';
	echo '<span class="input-group-addon" id="name_addon">';
	echo '<span class="fa fa-circle-o-notch"></span></span>';
	echo '<input type="text" class="form-control" ';
	echo 'id="name" name="name" maxlength="20" ';
	echo 'value="';
	echo $tc['name'] ?? '';
	echo '" required>';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="abbrev" class="control-label">';
	echo 'Afkorting</label>';
	echo '<input type="text" class="form-control" ';
	echo 'id="abbrev" name="abbrev" maxlength="11" ';
	echo 'value="';
	echo $tc['abbrev'] ?? '';
	echo '" required>';
	echo '</div>';

	echo $app['link']->btn_cancel('contact_types', $app['pp_ary'], []);

	echo '&nbsp;';
	echo '<input type="submit" name="zend" ';
	echo 'value="Opslaan" class="btn btn-success">';
	echo $app['form_token']->get_hidden_input();

	echo '</form>';
	echo '</div>';
	echo '</div>';

	include __DIR__ . '/include/footer.php';
	exit;
}

if ($edit)
{
	$tc_prefetch = $app['db']->fetchAssoc('select *
		from ' . $app['tschema'] . '.type_contact
		where id = ?', [$edit]);

	if (in_array($tc_prefetch['abbrev'], ['mail', 'tel', 'gsm', 'adr', 'web']))
	{
		$app['alert']->warning('Beschermd contact type.');
		$app['link']->redirect('contact_types', $app['pp_ary'], []);
	}

	if(isset($_POST['zend']))
	{
		if ($error_token = $app['form_token']->get_error())
		{
			$app['alert']->error($error_token);
			$app['link']->redirect('contact_types', $app['pp_ary'], []);
		}

		$tc = [
			'name'		=> $_POST['name'],
			'abbrev'	=> $_POST['abbrev'],
			'id'		=> $edit,
		];

		$error = (empty($tc['name'])) ? 'Geen naam ingevuld! ' : '';
		$error .= (empty($tc['abbrev'])) ? 'Geen afkorting ingevuld! ' : $error;

		if (!$error)
		{
			if ($app['db']->update($app['tschema'] . '.type_contact',
				$tc,
				['id' => $edit]))
			{
				$app['alert']->success('Contact type aangepast.');
				$app['link']->redirect('contact_types', $app['pp_ary'], []);
			}
			else
			{
				$app['alert']->error('Fout bij het opslaan.');
			}
		}
		else
		{
			$app['alert']->error('Fout in één of meer velden. ' . $error);
		}
	}
	else
	{
		$tc = $tc_prefetch;
	}

	$app['h1']->add('Contact type aanpassen');
	$app['h1']->fa('circle-o-notch');

	include __DIR__ . '/include/header.php';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';
	echo '<form method="post">';

	echo '<div class="form-group">';
	echo '<label for="name" class="control-label">Naam</label>';
	echo '<div class="input-group">';
	echo '<span class="input-group-addon" id="name_addon">';
	echo '<span class="fa fa-circle-o-notch"></span></span>';
	echo '<input type="text" class="form-control" id="name" ';
	echo 'name="name" maxlength="20" ';
	echo 'value="';
	echo $tc['name'];
	echo '" required>';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="abbrev" class="control-label">Afkorting</label>';
	echo '<input type="text" class="form-control" id="abbrev" ';
	echo 'name="abbrev" maxlength="11" ';
	echo 'value="';
	echo $tc['abbrev'];
	echo '" required>';
	echo '</div>';

	echo $app['link']->btn_cancel('contact_types', $app['pp_ary'], []);

	echo '&nbsp;';
	echo '<input type="submit" name="zend" ';
	echo 'value="Opslaan" class="btn btn-primary">';
	echo $app['form_token']->get_hidden_input();

	echo '</form>';
	echo '</div>';
	echo '</div>';

	include __DIR__ . '/include/footer.php';
	exit;
}

if ($del)
{
	$ct = $app['db']->fetchAssoc('select *
		from ' . $app['tschema'] . '.type_contact
		where id = ?', [$del]);

	if (in_array($ct['abbrev'], ['mail', 'tel', 'gsm', 'adr', 'web']))
	{
		$app['alert']->warning('Beschermd contact type.');
		$app['link']->redirect('contact_types', $app['pp_ary'], []);
	}

	if ($app['db']->fetchColumn('select id
		from ' . $app['tschema'] . '.contact
		where id_type_contact = ?', [$del]))
	{
		$app['alert']->warning('Er is ten minste één contact
			van dit contact type, dus kan het contact type
			niet verwijderd worden.');
		$app['link']->redirect('contact_types', $app['pp_ary'], []);
	}

	if(isset($_POST['zend']))
	{
		if ($error_token = $app['form_token']->get_error())
		{
			$app['alert']->error($error_token);
			$app['link']->redirect('contact_types', $app['pp_ary'], []);
		}

		if ($app['db']->delete($app['tschema'] . '.type_contact', ['id' => $del]))
		{
			$app['alert']->success('Contact type verwijderd.');
		}
		else
		{
			$app['alert']->error('Fout bij het verwijderen.');
		}

		$app['link']->redirect('contact_types', $app['pp_ary'], []);
	}

	$app['h1']->add('Contact type verwijderen: ' . $ct['name']);
	$app['h1']->fa('circle-o-notch');

	include __DIR__ . '/include/header.php';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';
	echo '<p>Ben je zeker dat dit contact type verwijderd mag worden?</p>';
	echo '<form method="post">';

	echo $app['link']->btn_cancel('contact_types', $app['pp_ary'], []);

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

$types = $app['db']->fetchAll('select *
	from ' . $app['tschema'] . '.type_contact tc');

$contact_count = [];

$rs = $app['db']->prepare('select distinct id_type_contact, count(id)
	from ' . $app['tschema'] . '.contact
	group by id_type_contact');
$rs->execute();

while($row = $rs->fetch())
{
	$contact_count[$row['id_type_contact']] = $row['count'];
}

$app['btn_top']->add('type_contact', $app['pp_ary'],
	['add' => 1], 'Contact type toevoegen');

$app['h1']->add('Contact types');
$app['h1']->fa('circle-o-notch');

include __DIR__ . '/include/header.php';

echo '<div class="panel panel-default printview">';

echo '<div class="table-responsive">';
echo '<table class="table table-striped table-hover ';
echo 'table-bordered footable" data-sort="false">';
echo '<tr>';
echo '<thead>';
echo '<th>Naam</th>';
echo '<th>Afkorting</th>';
echo '<th data-hide="phone">Verwijderen</th>';
echo '<th data-hide="phone">Contacten</th>';
echo '</tr>';
echo '</thead>';

echo '<tbody>';

foreach($types as $t)
{
	$count = $contact_count[$t['id']] ?? 0;

	$protected = (in_array($t['abbrev'], ['mail', 'gsm', 'tel', 'adr', 'web'])) ? true : false;

	echo '<tr>';

	echo '<td>';

	if ($protected)
	{
		echo htmlspecialchars($t['abbrev'],ENT_QUOTES) . '*';
	}
	else
	{
		echo $app['link']->link_no_attr('type_contact', $app['pp_ary'],
			['edit' => $t['id']], $t['abbrev']);
	}

	echo '</td>';

	echo '<td>';

	if ($protected)
	{
		echo htmlspecialchars($t['name'],ENT_QUOTES) . '*';
	}
	else
	{
		echo $app['link']->link_no_attr('type_contact', $app['pp_ary'],
			['edit' => $t['id']], $t['name']);
	}

	echo '</td>';

	echo '<td>';

	if ($protected || $count)
	{
		echo '&nbsp;';
	}
	else
	{
		echo $app['link']->link_fa('type_contact', $app['pp_ary'],
			['del' => $t['id']], 'Verwijderen',
			['class' => 'btn btn-danger btn-xs'],
			'times');
	}

	echo '</td>';

	echo '<td>';

	if ($count)
	{
		echo $app['link']->link_no_attr('contacts', $app['pp_ary'],
			['f' => ['abbrev' => $t['abbrev']]], $count);
	}
	else
	{
		echo '&nbsp;';
	}

	echo '</td>';

	echo '</tr>';
}

echo '</tbody>';
echo '</table>';
echo '</div></div>';

echo '<p>Kunnen niet verwijderd worden: ';
echo 'contact types waarvan contacten ';
echo 'bestaan en beschermde contact types (*).</p>';

include __DIR__ . '/include/footer.php';
