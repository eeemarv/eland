<?php
$rootpath = './';

$approve = ($_GET['approve']) ?: false;
$edit = ($_GET['edit']) ?: false;
$add = ($_GET['add']) ?: false;
$del = ($_GET['del']) ?: false;
$id = ($_GET['id']) ?: false;
$submit = ($_POST['zend']) ? true : false;

/**
 * approve a newsitem
 */

if ($approve)
{
	$page_access = 'admin';
	require_once $rootpath . 'includes/inc_default.php';

	if ($db->update('news', array('approved' => 't', 'published' => 't'), array('id' => $approve)))
	{
		$alert->success('Nieuwsbericht goedgekeurd');
	}
	else
	{
		$alert->error('Goedkeuren nieuwsbericht mislukt.');
	}
	cancel($approve);
}

/**
 * add or edit a newsitem
 */

if ($add || $edit)
{
	$page_access = 'user';
	require_once $rootpath . 'includes/inc_default.php';

	$news = array();

	if ($submit)
	{
		$news = array(
			'itemdate'		=> $_POST['itemdate'],
			'location'		=> $_POST['location'],
			'sticky'		=> ($_POST['sticky']) ? 't' : 'f',
			'newsitem'		=> $_POST['newsitem'],
			'headline'		=> $_POST['headline'],
		);

		if (!isset($news['headline']) || (trim($news['headline']) == ''))
		{
			$errors[] = 'Titel is niet ingevuld';
		}

		if (strlen($news['headline']) > 200)
		{
			$errors[] = 'De titel mag maximaal 200 tekens lang zijn.';
		}

		if (strlen($news['location']) > 128)
		{
			$errors[] = 'De locatie mag maximaal 128 tekens lang zijn.';
		}

		if ($token_error = get_error_form_token())
		{
			$errors[] = $token_error;
		}
	}

	if (count($errors))
	{
		$alert->error($errors);
	}
}

if ($add && $submit && !count($errors))
{
	$news['approved'] = ($s_admin) ? 't' : 'f';
	$news['published'] = ($s_admin) ? 't' : 'f';
	$news['id_user'] = $s_id;
	$news['cdate'] = date('Y-m-d H:i:s');
	
	if ($db->insert('news', $news))
	{
		$alert->success('Nieuwsbericht opgeslagen.');

		$id = $db->lastInsertId('news_id_seq');
		if(!$s_admin)
		{
			// Send a notice to ask for approval
			$url = $base_url . '/news.php?id=' . $id;

			$subject = 'Nieuwsbericht wacht op goedkeuring';
			$text .= "-- Dit is een automatische mail, niet beantwoorden aub --\r\n";
			$text .= "\nEen lid gaf een nieuwsbericht met titel '";
			$text .= $news['headline'];
			$text .= "' in. Dat bericht wacht op goedkeuring.  Log in als beheerder en ga naar nieuws om het bericht goed te keuren.\n";
			$text .= 'link: ' .  $url . "\n";
			mail_q(array('to' => 'newsadmin', 'subject' => $subject, 'text' => $text));
			echo '<br><strong>Bericht wacht op goedkeuring van een beheerder</strong>';
			$alert->success('Nieuwsbericht wacht op goedkeuring van een beheerder');
			cancel();
		}
		cancel($id);
	}
	else
	{
		$alert->error('Nieuwsbericht niet opgeslagen.');
	}
}

if ($edit && $submit && !count($errors))
{
	if($db->update('news', $news, array('id' => $edit)))
	{
		$alert->success('Nieuwsbericht aangepast.');
		cancel($edit);
	}
	else
	{
		$alert->error('Nieuwsbericht niet aangepast.');
	}
}

if ($edit)
{
	$news = $db->fetchAssoc('SELECT * FROM news WHERE id = ?', array($edit));
	list($news['itemdate']) = explode(' ', $news['itemdate']);
}

if ($add)
{
	$news['itemdate'] = date('Y-m-d');
}

if ($add || $edit)
{
	$includejs = '
		<script src="' . $cdn_jquery . '"></script>
		<script src="' . $cdn_datepicker . '"></script>
		<script src="' . $cdn_datepicker_nl . '"></script>';

	$includecss = '<link rel="stylesheet" type="text/css" href="' . $cdn_datepicker_css . '" />';

	$h1 = 'Nieuwsbericht ';
	$h1 .= ($add) ? 'toevoegen' : 'aanpassen';
	$fa = 'calendar';

	include $rootpath . 'includes/inc_header.php';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<form method="post" class="form-horizontal">';

	echo '<div class="form-group">';
	echo '<label for="itemdate" class="col-sm-2 control-label">Agendadatum (wanneer gaat dit door?)</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="itemdate" name="itemdate" ';
	echo 'data-provide="datepicker" data-date-format="yyyy-mm-dd" ';
	echo 'data-date-language="nl" ';
	echo 'data-date-today-highlight="true" ';
	echo 'data-date-autoclose="true" ';
	echo ' value="' . $news['itemdate'] . '" required>';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="location" class="col-sm-2 control-label">Locatie</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="location" name="location" ';
	echo 'value="' . $news['location'] . '" maxlength="128">';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="headline" class="col-sm-2 control-label">Titel</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="headline" name="headline" ';
	echo 'value="' . $news['headline'] . '" required maxlength="200">';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="newsitem" class="col-sm-2 control-label">Bericht</label>';
	echo '<div class="col-sm-10">';
	echo '<textarea name="newsitem" id="newsitem" class="form-control" rows="10" required>';
	echo $news['newsitem'];
	echo '</textarea>';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="sticky" class="col-sm-2 control-label">Behoud na datum</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="checkbox" id="sticky" name="sticky" ';
	echo 'value="1"';
	echo  ($news['sticky'] == 't') ? ' checked="checked"' : '';
	echo '>';
	echo '</div>';
	echo '</div>';

	$btn = ($add) ? 'success' : 'primary';
	echo aphp('news', ($edit) ? 'id=' . $edit : '', 'Annuleren', 'btn btn-default') . '&nbsp;';
	echo '<input type="submit" name="zend" value="Opslaan" class="btn btn-' . $btn . '">';
	generate_form_token();

	echo '</form>';

	echo '</div>';
	echo '</div>';

	include $rootpath . 'includes/inc_footer.php';
	exit;
}

/**
 * delete a newsitem
 */

if ($del)
{
	$page_access = 'admin';
	require_once $rootpath . 'includes/inc_default.php';

	if(isset($_POST['zend']))
	{
		if ($error_token = get_error_form_token())
		{
			$alert->error($error_token);
			cancel();
		}

		if($db->delete('news', array('id' => $del)))
		{
			$alert->success('Nieuwsbericht verwijderd.');
			cancel();
		}
		$alert->error('Nieuwsbericht niet verwijderd.');
	}

	$news = $db->fetchAssoc('SELECT n.*
		FROM news n  
		WHERE n.id = ?', array($del));

	$h1 = 'Nieuwsbericht ' . $news['headline'] . ' verwijderen?';
	$fa = 'calendar';

	include $rootpath . 'includes/inc_header.php';

	echo '<div >';
	echo '<strong>Agendadatum: ';
	list($itemdate) = explode(' ', $news['itemdate']);
	if(trim($itemdate) != '00/00/00')
	{
		echo $itemdate;
	}
	echo '<br>Locatie: ' .$news['location'];
	echo '</strong>';
	echo '<br><i>Ingegeven door : ';
	echo link_user($news['id_user']);
	echo '</i>';
	echo ($news['approved'] == 't') ? '<br><i>Goedgekeurd.</i>' : '<br><i>Nog niet goedgekeurd.</i>';
	echo ($news['sticky'] == 't') ? '<br><i>Behoud na datum.</i>' : '<br><i>Wordt verwijderd na datum.</i>';

	echo '<p>';
	echo nl2br(htmlspecialchars($news['newsitem'],ENT_QUOTES));
	echo '</p>';

	echo '<table width="100%"><tr><td>';
	echo '<div id="navcontainer">';
	echo '</div>';
	echo '</td></tr></table>';

	echo '</p>';
	echo '</div>';

	echo '<p><font color="red"><strong>Ben je zeker dat dit nieuwsbericht ';
	echo 'moet verwijderd worden?</strong></font></p>';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<form method="post">';
	echo aphp('news', '', 'Annuleren', 'btn btn-default') . '&nbsp;';
	echo '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger">';
	generate_form_token();
	echo '</form>';

	echo '</div>';
	echo '</div>';

	include $rootpath. 'includes/inc_footer.php';
	exit;
}

/**
 * show a newsitem
 */

if ($id)
{
	$page_access = 'guest';

	require_once $rootpath . 'includes/inc_default.php';

	$news = $db->fetchAssoc('SELECT n.*
		FROM news n  
		WHERE n.id = ?', array($id));

	if (!$s_admin && !$news['approved'])
	{
		$alert->error('Je hebt geen toegang tot dit nieuwsbericht.');
		cancel();
	}

	$and_approved_sql = ($s_admin) ? '' : ' and approved = \'t\' ';

	$next = $db->fetchColumn('select id
	from news
	where id > ?
		' . $and_approved_sql . '
	order by id asc
	limit 1', array($id));

	$prev = $db->fetchColumn('select id
		from news
		where id < ?
		' . $and_approved_sql . '
		order by id desc
		limit 1', array($id));

	$top_buttons = '';

	if($s_user || $s_admin)
	{
		$top_buttons .= aphp('news', 'add=1', 'Toevoegen', 'btn btn-success', 'Nieuws toevoegen', 'plus', true);

		if($s_admin)
		{
			$top_buttons .= aphp('news', 'edit=' . $id, 'Aanpassen', 'btn btn-primary', 'Nieuwsbericht aanpassen', 'pencil', true);
			$top_buttons .= aphp('news', 'del=' . $id, 'Verwijderen', 'btn btn-danger', 'Nieuwsbericht verwijderen', 'times', true);

			if (!$news['approved'])
			{
				$top_buttons .= aphp('news', 'approve=' . $id, 'Goedkeuren', 'btn btn-warning', 'Nieuwsbericht goedkeuren en publiceren', 'check', true);
			}
		}
	}

	if ($prev)
	{
		$top_buttons .= aphp('news', 'id=' . $prev, 'Vorige', 'btn btn-default', 'Vorige', 'chevron-down', true);
	}

	if ($next)
	{
		$top_buttons .= aphp('news', 'id=' . $next, 'Volgende', 'btn btn-default', 'Volgende', 'chevron-up', true);
	}

	$top_buttons .= aphp('news', 'view=' . $view_news, 'Lijst', 'btn btn-default', 'Lijst', 'calendar', true);

	$h1 = 'Nieuwsbericht: ' . htmlspecialchars($news['headline'], ENT_QUOTES);
	$fa = 'calendar';

	include $rootpath . 'includes/inc_header.php';

	$background = ($news['approved']) ? '' : ' bg-warning';

	echo '<div class="panel panel-default printview">';
	echo '<div class="panel-heading">';

	echo '<p>Bericht</p>';
	echo '</div>';
	echo '<div class="panel-body' . $background . '">';
	echo nl2br(htmlspecialchars($news['newsitem'],ENT_QUOTES));
	echo '</div></div>';

	echo '<div class="panel panel-default printview">';
	echo '<div class="panel-heading">';

	echo '<dl>';

	echo '<dt>Agendadatum</dt>';
	list($itemdate) = explode(' ', $news['itemdate']);
	echo '<dd>';
	echo ($itemdate) ? $itemdate : '<i class="fa fa-times"></i>';
	echo '</dd>';

	echo '<dt>Locatie</dt>';
	echo '<dd>';
	echo ($news['location']) ? htmlspecialchars($news['location'], ENT_QUOTES) : '<i class="fa fa-times"></i>';
	echo '</dd>';

	echo '<dt>Ingegeven door</dt>';
	echo '<dd>';
	echo link_user($news['id_user']);
	echo '</dd>';

	if ($s_admin)
	{
		echo '<dt>Goedgekeurd</dt>';
		echo '<dd>';
		echo ($news['approved']) ? 'Ja' : 'Nee';
		echo '</dd>';

		echo '<dt>Behoud na datum?</dt>';
		echo '<dd>';
		echo ($news['sticky']) ? 'Ja' : 'Nee';
		echo '</dd>';
	}
	echo '</dl>';

	echo '</div>';
	echo '</div>';

	include $rootpath . 'includes/inc_footer.php';
	exit;
}

/**
 * show all newsitems
 */

$page_access = 'guest';
require_once $rootpath . 'includes/inc_default.php';

if (!($view || $inline))
{
	cancel();
}

$v_list = ($view == 'list' || $inline) ? true : false;
$v_extended = ($view == 'extended' && !$inline) ? true : false;

$params = array(
	'view'	=> $view,
);

$query = 'SELECT *, to_char(itemdate, \'YYYY-MM-DD\') as idate FROM news';

if(!$s_admin)
{
	$query .= ' where approved = \'t\'';
}

$query .= ' ORDER BY itemdate DESC';

$news = $db->fetchAll($query);

if(($s_user || $s_admin) && !$inline)
{
	$top_buttons .= aphp('news', 'add=1', 'Toevoegen', 'btn btn-success', 'Nieuws toevoegen', 'plus', true);
}

if ($inline)
{
//	echo '<div class="row">';
//	echo '<div class="col-md-12">';

	echo '<h3>';
	echo aphp('news', 'view=' . $view_news, 'Nieuws', false, false, 'calendar');
	echo '</h3>';
}
else
{
	$h1 = 'Nieuws';

	$v_params = $params;
	$h1 .= '<span class="pull-right hidden-xs">';
	$h1 .= '<span class="btn-group" role="group">';

	$active = ($v_list) ? ' active' : '';
	$v_params['view'] = 'list';
	$h1 .= aphp('news', $v_params, '', 'btn btn-default' . $active, 'lijst', 'align-justify');

	$active = ($v_extended) ? ' active' : '';
	$v_params['view'] = 'extended';
	$h1 .= aphp('news', $v_params, '', 'btn btn-default' . $active, 'Lijst met omschrijvingen', 'th-list');

	$h1 .= '</span></span>';

	$fa = 'calendar';

	include $rootpath . 'includes/inc_header.php';
}

if (!count($news))
{
	echo '<div class="panel panel-warning">';
	echo '<div class="panel-heading">';
	echo '<p>Er zijn momenteel geen nieuwsberichten.</p>';
	echo '</div></div>';

	if (!$inline)
	{
		include $rootpath . 'includes/inc_footer.php';
	}
	exit;
}

if ($v_list)
{
	echo '<div class="panel panel-warning printview">';
	echo '<div class="table-responsive">';
	echo '<table class="table table-striped table-hover table-bordered footable">';

	if (!$inline)
	{
		echo '<thead>';
		echo '<tr>';
		echo '<th>Titel</th>';
		echo '<th data-hide="phone" data-sort-initial="descending">Agendadatum</th>';
		echo ($s_admin && !$inline) ? '<th data-hide="phone">Goedgekeurd</th>' : '';
		echo '</tr>';
		echo '</thead>';
	}

	echo '<tbody>';

	foreach ($news as $n)
	{
		echo '<tr';
		echo ($n['approved']) ? '' : ' class="warning"';
		echo '>';

		echo '<td>';
		echo aphp('news', 'id=' . $n['id'], $n['headline']);
		echo '</td>';

		echo '<td>';
		echo $n['idate'];
		echo '</td>';

		if ($s_admin && !$inline)
		{
			echo '<td>';
			echo ($n['approved']) ? 'Ja' : 'Nee';
			echo '</td>';
		}
		echo '</tr>';
	}
	echo '</tbody>';
	echo '</table></div></div>';
}
else if ($v_extended)
{
	foreach ($news as $n)
	{
		$background = ($n['approved']) ? '' : ' bg-warning';

		echo '<div class="panel panel-info printview">';
		echo '<div class="panel-body' . $background . '">';

		echo '<div class="media">';
		echo '<div class="media-body">';
		echo '<h3 class="media-heading">';
		echo aphp('news', 'id=' . $n['id'], $n['headline']);
		echo '</h3>';
		echo nl2br(htmlspecialchars($n['newsitem'],ENT_QUOTES));

		echo '<dl>';

		if ($n['location'])
		{
			echo '<dt>';
			echo 'Locatie';
			echo '</dt>';
			echo '<dd>';
			echo htmlspecialchars($n['location'], ENT_QUOTES);
			echo '</dd>';
		}

		if ($n['idate'])
		{
			echo '<dt>';
			echo 'Agendadatum';
			echo '</dt>';
			echo '<dd>';
			echo htmlspecialchars($n['idate'], ENT_QUOTES);

			if ($n['sticky'])
			{
				echo ' <i>(Nieuwsbericht blijft behouden na datum)</i>';
			}
			echo '</dd>';
		}

		echo '</dl>';

		echo '</div>';
		echo '</div>';
		echo '</div>';

		echo '<div class="panel-footer">';
		echo '<p><i class="fa fa-user"></i>' . link_user($n['id_user']);

		if ($s_admin)
		{
			echo '<span class="inline-buttons pull-right hidden-xs">';
			if (!$n['approved'])
			{
				echo aphp('news', 'approve=' . $n['id'], 'Goedkeuren en publiceren', 'btn btn-warning btn-xs', false, 'check');
			}
			echo aphp('news', 'edit=' . $n['id'], 'Aanpassen', 'btn btn-primary btn-xs', false, 'pencil');
			echo aphp('news', 'del=' . $n['id'], 'Verwijderen', 'btn btn-danger btn-xs', false, 'times');
			echo '</span>';
		}
		echo '</p>';
		echo '</div>';

		echo '</div>';
	}
}

if (!$inline)
{
	include $rootpath . 'includes/inc_footer.php';
}

function cancel($id = '')
{
	global $view_news;

	$param = ($id) ? 'id=' . $id . '&' : '';
	$param .= 'view=' . $view_news;

	header('Location: ' . generate_url('news', $param));
	exit;
}
