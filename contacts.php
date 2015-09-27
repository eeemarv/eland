<?php
ob_start();
$rootpath = './';

$uid = ($_GET['uid']) ?: false;
$del = ($_GET['del']) ?: false;
$edit = ($_GET['edit']) ?: false;
$add = ($_GET['add']) ?: false;
$inline = ($_GET['inline']) ? true : false;
$submit = ($_POST['zend']) ? true : false;

$role = ($del || $add || $edit) ? 'user' : 'guest';

require_once $rootpath . 'includes/inc_default.php';

$acc_ary = array(
	0	=> array('privé', 'default'),
	1	=> array('leden', 'warning'),
	2	=> array('interlets', 'success'),
);


if ($del)
{
	if (!($uid = $db->fetchColumn('select c.id_user from contact c where c.id = ?', array($del))))
	{
		$alert->error('Het contact bestaat niet.');
		cancel();
	}

	$s_owner = ($uid == $s_id) ? true : false;

	if (!($s_admin || $s_owner))
	{
		$alert->error('Je hebt geen rechten om het contact te verwijderen.');
		cancel($uid);
	}

	if ($db->fetchColumn('select count(c.*)
		from contact c, type_contact tc
		where c.id_type_contact = tc.id
			and c.id_user = ?
			and tc.abbrev = \'mail\'
			and c.id = ?', array($uid, $del)) == 1)
	{
		$err = ($s_owner) ? 'je enige email adres' : 'het enige email adres van een gebruiker';
		$alert->error('Je kan niet ' . $err . ' verwijderen.');
		cancel($uid);
	}

	if ($submit)
	{
		if ($db->delete('contact', array('id' => $del)))
		{
			$alert->success('Contact verwijderd.');
		}
		else
		{
			$alert->error('Fout bij verwijderen van het contact.');
		}
		cancel($uid);
	}

	$contact = $db->fetchAssoc('SELECT tc.abbrev, c.value, c.comments, c.flag_public
		FROM type_contact tc, contact c
		WHERE c.id_type_contact = tc.id
			AND c.id = ?', array($del));

	$h1 = 'Contact verwijderen?';

	include $rootpath . 'includes/inc_header.php';

	echo '<br>';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	$acc = $acc_ary[$contact['flag_public']];

	echo '<dl>';
	if (!$s_owner)
	{
		echo '<dt>Gebruiker</dt>';
		echo '<dd>' . link_user($uid) . '</dd>';
	}
	echo '<dt>Type</dt>';
	echo '<dd>' . $contact['abbrev'] . '</dd>';
	echo '<dt>Waarde</dt>';
	echo '<dd>' . $contact['value'] . '</dd>';
	echo '<dt>Commentaar</dt>';
	echo '<dd>' . $contact['comments'] . '</dd>';
	echo '<dt>Zichtbaarheid</dt>';
	echo '<dd><span class="label label-' . $acc[1] . '">' . $acc[0] . '</span></dd>';
	echo '</dl>';

	echo '<form method="post" class="form-horizontal">';

	echo '<a href="' . $rootpath . 'users.php?id=' . $uid . '" class="btn btn-default">Annuleren</a>&nbsp;';
	echo '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger">';

	echo '</form>';

	echo '</div>';
	echo '</div>';

	include $rootpath . 'includes/inc_footer.php';
	exit;
}

if ($edit || $add)
{
	if ($edit)
	{
		if (!($uid = $db->fetchColumn('select id_user from contact where id = ?', array($edit))))
		{
			cancel();
		}
	}

	if (!($s_admin || $uid == $s_id))
	{
		$err = ($edit) ? 'dit contact aan te passen.' : 'een contact toe te voegen voor deze gebruiker.';
		$alert->error('Je hebt geen rechten om ' . $err);
		cancel($uid);
	}

	if($submit)
	{
		$contact = array(
			'id_type_contact'		=> $_POST['id_type_contact'],
			'value'					=> $_POST['value'],
			'comments' 				=> $_POST['comments'],
			'flag_public'			=> $_POST['flag_public'],
			'id_user'				=> $uid,
		);

		$errors = array();

		if (!$contact['value'])
		{
			$errors[] = 'Vul waarde in!';
		}

		if(!$db->fetchColumn('SELECT abbrev FROM type_contact WHERE id = ?', array($contact['id_type_contact'])))
		{
			$errors[] = 'Contacttype bestaat niet!';
		}

		if(!count($errors))
		{
			if ($edit)
			{
				if ($db->update('contact', $contact, array('id' => $edit)))
				{
					$alert->success('Contact aangepast.');
					cancel($uid);
				}
				else
				{
					$alert->error('Fout bij het opslaan');
				}
			}
			else
			{
				if ($db->insert('contact', $contact))
				{
					$alert->success('Contact opgeslagen.');
					cancel($uid);
				}
				else
				{
					$alert->error('Fout bij het opslaan');
				}
			}
		}
		else
		{
			$alert->error(implode('<br>', $errors));
		}
	}
	else if ($edit)
	{
		$contact = $db->fetchAssoc('select * from contact where id = ?', array($edit));
	}

	$access_options = array(
		'0'	=> 'privé',
		'1'	=> 'leden',
		'2' => 'interlets',
	); 

	$tc = array();

	$rs = $db->prepare('SELECT id, name FROM type_contact');

	$rs->execute();

	while ($row = $rs->fetch())
	{
		$tc[$row['id']] = $row['name'];
	}

	$h1 = ($edit) ? 'Contact aanpassen' : 'Contact toevoegen';
	$h1 .= ($s_owner) ? '' : ' voor ' . link_user($uid);

	include $rootpath . 'includes/inc_header.php';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<form method="post" class="form-horizontal">';

	echo '<div class="form-group">';
	echo '<label for="id_type_contact" class="col-sm-2 control-label">Type</label>';
	echo '<div class="col-sm-10">';
	echo '<select name="id_type_contact" id="id_type_contact" class="form-control" required>';
	render_select_options($tc, $contact['id_type_contact']);
	echo "</select>";
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="value" class="col-sm-2 control-label">Waarde</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="value" name="value" ';
	echo 'value="' . $contact['value'] . '" required>';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="comments" class="col-sm-2 control-label">Commentaar</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="comments" name="comments" ';
	echo 'value="' . $contact['comments'] . '">';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="flag_public" class="col-sm-2 control-label">';
	echo 'Zichtbaarheid</label>';
	echo '<div class="col-sm-10">';
	echo '<select name="flag_public" id="flag_public" class="form-control">';
	render_select_options($access_options, $contact['flag_public']);
	echo '</select>';
	echo '</div>';
	echo '</div>';

	echo '<a href="' . $rootpath . 'users.php?id=' . $uid . '" class="btn btn-default">Annuleren</a>&nbsp;';
	echo '<input type="submit" value="Opslaan" name="zend" class="btn btn-success">';

	echo '</form>';

	echo '</div>';
	echo '</div>';

	include $rootpath . 'includes/inc_footer.php';
	exit;
}

if ($uid)
{
	$s_owner = ($uid == $s_id) ? true : false;

	$contacts = $db->fetchAll('select c.*, tc.abbrev
		from contact c, type_contact tc
		where c.id_type_contact = tc.id
			and c.id_user = ?', array($uid));

	$user = readuser($uid);

	if ($s_admin || $s_owner)
	{
		$top_buttons = '<a href="' . $rootpath . 'contacts.php?add=1&uid=' . $uid . '" class="btn btn-success"';
		$top_buttons .= ' title="Contact toevoegen"><i class="fa fa-plus"></i>';
		$top_buttons .= '<span class="hidden-xs hidden-sm"> Toevoegen</span></a>';
	}
	else
	{
		$top_buttons = '';
	}

	if (!$inline)
	{
		$h1 = ($s_owner) ? 'Mijn contacten' : 'Contacten Gebruiker ' . $user['letscode'] . ' ' . $user['name'];
		$fa = 'map-marker';

		include $rootpath . 'includes/inc_header.php';
		echo '<br>';
	}
	else
	{
		echo '<div class="row">';
		echo '<div class="col-md-12">';

		echo '<h3><i class="fa fa-map-marker"></i> Contactinfo ';
		echo $top_buttons;
		echo '</h3>';
	}

	echo '<div class="table-responsive">';
	echo '<table class="table table-hover table-striped table-bordered footable">';

	echo '<thead>';
	echo '<tr>';
	echo '<th>Type</th>';
	echo '<th>Waarde</th>';
	echo '<th data-hide="phone, tablet">Commentaar</th>';

	if ($s_admin || $s_owner)
	{
		echo '<th data-hide="phone, tablet">Zichtbaarheid</th>';
		echo '<th data-sort-ignore="true" data-hide="phone, tablet">Verwijderen</th>';
	}

	echo '</tr>';
	echo '</thead>';

	echo '<tbody>';

	$a2 = ($s_admin || $s_owner) ? '</a>' : '';

	foreach ($contacts as $c)
	{
		$access = $acc_ary[$c['flag_public']];
		$a1 = ($s_admin || $s_owner) ? '<a href="' . $rootpath . 'contacts.php?edit=' . $c['id'] . '">' : '';
		echo '<tr>';
		echo '<td>' . $a1 . $c['abbrev'] . $a2 . '</td>';

		if ($c['flag_public'] < $access_level)
		{
			echo '<td><span class="label label-' . $access[1] . '">' . $access[0] . '</span></td>';
			echo '<td></td>';
		}
		else
		{
			echo '<td>' . $a1 . htmlspecialchars($c['value'], ENT_QUOTES) . $a2 . '</td>';
			echo '<td>' . $a1 . htmlspecialchars($c['comments'],ENT_QUOTES) . $a2 . '</td>';
		}

		if ($s_admin || $s_owner)
		{
			echo '<td>' . $a1;
			echo '<span class="label label-' . $access[1] . '">' . $access[0] . '</span>';
			echo $a2 . '</td>';

			echo '<td>';
			echo '<a href="' . $rootpath . 'contacts.php?del='.$c['id'];
			echo '" class="btn btn-danger btn-xs"><i class="fa fa-times"></i>';
			echo ' Verwijderen</a></td>';
			echo '</tr>';
		}
	}

	echo '</tbody>';

	echo '</table>';
	echo '</div>';

	echo '</div></div>';

	if ($inline)
	{
		exit;
	}

	include $rootpath . 'includes/inc_footer.php';
	exit;
}

echo 'parameter uid must be set.';
exit;

function cancel($uid = null)
{
	global $rootpath;
	header('Location: ' . $rootpath . 'users.php' . (($uid) ? '?id=' . $uid : ''));
	exit;
}
