<?php

$rootpath = './';
$page_access = 'admin';
require_once $rootpath . 'includes/inc_default.php';

$edit = (isset($_GET['edit'])) ? $_GET['edit'] : false;
$del = (isset($_GET['del'])) ? $_GET['del'] : false;
$add = (isset($_GET['add'])) ? $_GET['add'] : false;

if ($add)
{
	if (isset($_POST['zend']))
	{
		if ($error_token = get_error_form_token())
		{
			$alert->error($error_token);
			cancel();
		}

		$tc = [];
		$tc['name'] = $_POST['name'];
		$tc['abbrev'] = $_POST['abbrev'];
		
		$error = (empty($tc['name'])) ? 'Geen naam ingevuld! ' : '';
		$error .= (empty($tc['abbrev'])) ? 'Geen afkorting ingevuld! ' : $error;

		if (!$error)
		{
			if ($db->insert('type_contact', $tc))
			{
				$alert->success('Contact type toegevoegd.');
			}
			else
			{
				$alert->error('Fout bij het opslaan');
			}

			cancel();
		}

		$alert->error('Corrigeer één of meerdere velden.');
	}

	$h1 = 'Contact type toevoegen';
	$fa = 'circle-o-notch';

	include $rootpath . 'includes/inc_header.php';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';
	echo '<form method="post" class="form-horizontal">';

	echo '<div class="form-group">';
	echo '<label for="name" class="col-sm-2 control-label">Naam</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="name" name="name" maxlength="20" ';
	echo 'value="' . $ct['name'] . '" required>';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="abbrev" class="col-sm-2 control-label">Afkorting</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="abbrev" name="abbrev" maxlength="11" ';
	echo 'value="'. $ct['abbrev'] . '" required>';
	echo '</div>';
	echo '</div>';

	echo aphp('type_contact', [], 'Annuleren', 'btn btn-default') . '&nbsp;';
	echo '<input type="submit" name="zend" value="Opslaan" class="btn btn-success">';
	generate_form_token();

	echo '</form>';
	echo '</div>';
	echo '</div>';

	include $rootpath . 'includes/inc_footer.php';
	exit;
}

if ($edit)
{
	$tc_prefetch = $db->fetchAssoc('select * from type_contact where id = ?', [$edit]);

	if (in_array($tc_prefetch['abbrev'], ['mail', 'tel', 'gsm', 'adr', 'web']))
	{
		$alert->warning('Beschermd contact type.');

		cancel();
	}

	if(isset($_POST['zend']))
	{
		if ($error_token = get_error_form_token())
		{
			$alert->error($error_token);
			cancel();
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
			if ($db->update('type_contact', $tc, ['id' => $edit]))
			{
				$alert->success('Contact type aangepast.');

				cancel();
			}
			else
			{
				$alert->error('Fout bij het opslaan.');
			}
		}
		else
		{
			$alert->error('Fout in één of meer velden. ' . $error);
		}
	}
	else
	{
		$tc = $tc_prefetch;
	}

	$h1 = 'Contact type aanpassen';
	$fa = 'circle-o-notch';

	include $rootpath . 'includes/inc_header.php';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';
	echo '<form method="post" class="form-horizontal">';

	echo '<div class="form-group">';
	echo '<label for="name" class="col-sm-2 control-label">Naam</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="name" name="name" maxlength="20" ';
	echo 'value="' . $tc['name'] . '" required>';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="abbrev" class="col-sm-2 control-label">Afkorting</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="abbrev" name="abbrev" maxlength="11" ';
	echo 'value="'. $tc['abbrev'] . '" required>';
	echo '</div>';
	echo '</div>';

	echo aphp('type_contact', [], 'Annuleren', 'btn btn-default') . '&nbsp;';
	echo '<input type="submit" name="zend" value="Opslaan" class="btn btn-primary">';
	generate_form_token();

	echo '</form>';
	echo '</div>';
	echo '</div>';

	include $rootpath . 'includes/inc_footer.php';
	exit;
}

if ($del)
{
	$ct = $db->fetchAssoc('select * from type_contact where id = ?', [$del]);

	if (in_array($ct['abbrev'], ['mail', 'tel', 'gsm', 'adr', 'web']))
	{
		$alert->warning('Beschermd contact type.');
		cancel();
	}

	if ($db->fetchColumn('select id from contact where id_type_contact = ?', [$del]))
	{
		$alert->warning('Er is ten minste één contact van dit contact type, dus kan het conact type niet verwijderd worden.');
		cancel();
	}

	if(isset($_POST['zend']))
	{
		if ($error_token = get_error_form_token())
		{
			$alert->error($error_token);
			cancel();
		}

		if ($db->delete('type_contact', ['id' => $del]))
		{
			$alert->success('Contact type verwijderd.');
		}
		else
		{
			$db->error('Fout bij het verwijderen.');
		}

		cancel();
	}

	$h1 = 'Contact type verwijderen: ' . $ct['name'];
	$fa = 'circle-o-notch';

	include $rootpath . 'includes/inc_header.php';	

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';
	echo '<p>Ben je zeker dat dit contact type verwijderd mag worden?</p>';
	echo '<form method="post">';
	echo aphp('type_contact', [], 'Annuleren', 'btn btn-default') . '&nbsp;';
	echo '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger">';
	generate_form_token();

	echo '</form>';
	echo '</div>';
	echo '</div>';

	include $rootpath . 'includes/inc_footer.php';
	exit;
}

$types = $db->fetchAll('select * from type_contact tc');

$contact_count = [];

$rs = $db->prepare('select id_type_contact, count(id)
	from contact
	group by id_type_contact');
$rs->execute();

while($row = $rs->fetch())
{
	$contact_count[$row['id_type_contact']] = $row['count'];
}

$top_buttons .= aphp('type_contact', ['add' => 1], 'Toevoegen', 'btn btn-success', 'Contact type toevoegen', 'plus', true);

$h1 = 'Contact types';
$fa = 'circle-o-notch';

include $rootpath . 'includes/inc_header.php';

echo '<div class="panel panel-default printview">';

echo '<div class="table-responsive">';
echo '<table class="table table-striped table-hover table-bordered footable" data-sort="false">';
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
	$count = isset($contact_count[$t['id']]) ? $contact_count[$t['id']] : 0;

	$protected = (in_array($t['abbrev'], ['mail', 'gsm', 'tel', 'adr', 'web'])) ? true : false;

	echo '<tr>';

	echo '<td>';
	echo ($protected) ? htmlspecialchars($t['abbrev'],ENT_QUOTES) . '*' : aphp('type_contact', ['edit' => $t['id']], $t['abbrev']);
	echo '</td>';

	echo '<td>';
	echo ($protected) ? htmlspecialchars($t['name'],ENT_QUOTES) . '*' : aphp('type_contact', ['edit' => $t['id']], $t['name']);
	echo '</td>';

	echo '<td>';

	if ($protected || $count)
	{
		echo '&nbsp;';
	}
	else
	{
		echo aphp('type_contact', ['del' => $t['id']], 'Verwijderen', 'btn btn-danger btn-xs', false, 'times');
	}

	echo '</td>';

	echo '<td>';

	if ($count)
	{
		echo aphp('contacts', ['abbrev' => $t['abbrev']], $count);
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
echo 'contact types waarvan contacten bestaan en beschermde contact types (*).</p>';

include $rootpath . 'includes/inc_footer.php';

function cancel($id = '')
{
	$params = [];

	if ($id)
	{
		$params['id'] = $id;
	}
	
	header('Location: ' . generate_url('type_contact', $params));
	exit;
}
