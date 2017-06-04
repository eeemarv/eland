<?php

$approve = $_GET['approve'] ?? false;
$edit = $_GET['edit'] ?? false;
$add = $_GET['add'] ?? false;
$del = $_GET['del'] ?? false;
$id = $_GET['id'] ?? false;
$submit = isset($_POST['zend']) ? true : false;

/**
 * approve a newsitem
 */

if ($approve)
{
	$page_access = 'admin';

	require_once __DIR__ . '/include/web.php';

	if ($app['db']->update('news', ['approved' => 't', 'published' => 't'], ['id' => $approve]))
	{
		$app['alert']->success('Nieuwsbericht goedgekeurd');
	}
	else
	{
		$app['alert']->error('Goedkeuren nieuwsbericht mislukt.');
	}
	cancel($approve);
}

/**
 * add or edit a newsitem
 */

if ($add || $edit)
{
	$page_access = 'user';
	require_once __DIR__ . '/include/web.php';

	$news = [];

	if ($submit)
	{
		$news = [
			'itemdate'		=> trim($_POST['itemdate'] ?? ''),
			'location'		=> trim($_POST['location'] ?? ''),
			'sticky'		=> isset($_POST['sticky']) ? 't' : 'f',
			'newsitem'		=> trim($_POST['newsitem'] ?? ''),
			'headline'		=> trim($_POST['headline'] ?? ''),
		];

		$access_error = $app['access_control']->get_post_error();

		if ($access_error)
		{
			$errors[] = $access_error;
		}

		if ($news['itemdate'])
		{
			$news['itemdate'] = $app['date_format']->reverse($news['itemdate']);

			if ($news['itemdate'] === false)
			{
				$errors[] = 'Fout formaat in agendadatum.';

				$news['itemdate'] = '';
			}
		}

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

		if ($token_error = $app['form_token']->get_error())
		{
			$errors[] = $token_error;
		}
	}

	if (count($errors))
	{
		$app['alert']->error($errors);
	}
}

if ($add && $submit && !count($errors))
{
	$news['approved'] = ($s_admin) ? 't' : 'f';
	$news['published'] = ($s_admin) ? 't' : 'f';
	$news['id_user'] = ($s_master) ? 0 : $s_id;
	$news['cdate'] = gmdate('Y-m-d H:i:s');

	if ($app['db']->insert('news', $news))
	{
		$id = $app['db']->lastInsertId('news_id_seq');

		$app['xdb']->set('news_access', $id, ['access' => $_POST['access']]);

		$app['alert']->success('Nieuwsbericht opgeslagen.');

		if(!$s_admin)
		{
			$vars = [
				'group'		=> [
					'name'	=> $app['config']->get('systemname'),
					'tag'	=> $app['config']->get('systemtag'),
				],
				'news'	=> $news,
				'news_url'	=> $app['base_url'] . '/news.php?id=' . $id,
			];

			$app['queue.mail']->queue([
				'to' 		=> 'newsadmin',
				'template'	=> 'admin_news_approve',
				'vars'		=> $vars,
			]);

			$app['alert']->success('Nieuwsbericht wacht op goedkeuring van een beheerder');
			cancel();
		}
		cancel($id);
	}
	else
	{
		$app['alert']->error('Nieuwsbericht niet opgeslagen.');
	}
}

if ($edit && $submit && !count($errors))
{
	if($app['db']->update('news', $news, ['id' => $edit]))
	{
		$app['xdb']->set('news_access', $edit, ['access' => $_POST['access']]);

		$app['alert']->success('Nieuwsbericht aangepast.');
		cancel($edit);
	}
	else
	{
		$app['alert']->error('Nieuwsbericht niet aangepast.');
	}
}

if ($edit)
{
	$news = $app['db']->fetchAssoc('SELECT * FROM news WHERE id = ?', [$edit]);
	list($news['itemdate']) = explode(' ', $news['itemdate']);

	$news_access = $app['xdb']->get('news_access', $edit)['data']['access'];
}

if ($add)
{
	$news['itemdate'] = gmdate('Y-m-d');
}

if ($add || $edit)
{
	$app['assets']->add('datepicker');

	$h1 = 'Nieuwsbericht ';
	$h1 .= ($add) ? 'toevoegen' : 'aanpassen';
	$fa = 'calendar';

	include __DIR__ . '/include/header.php';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<form method="post" class="form-horizontal">';

	echo '<div class="form-group">';
	echo '<label for="itemdate" class="col-sm-2 control-label">Agendadatum</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="itemdate" name="itemdate" ';
	echo 'data-provide="datepicker" ';
	echo 'data-date-format="' . $app['date_format']->datepicker_format() . '" ';
	echo 'data-date-language="nl" ';
	echo 'data-date-today-highlight="true" ';
	echo 'data-date-autoclose="true" ';
	echo 'data-date-orientation="bottom" ';
	echo 'value="' . $app['date_format']->get($news['itemdate'], 'day') . '" ';
	echo 'placeholder="' . $app['date_format']->datepicker_placeholder() . '">';
	echo '<p><small>Wanneer gaat dit door?</small></p>';
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

	if ($s_user)
	{
		$omit_access = 'admin';
	}
	else
	{
		$omit_access = false;
	}

	echo $app['access_control']->get_radio_buttons('news', $news_access, $omit_access);

	$btn = ($add) ? 'success' : 'primary';
	echo aphp('news', ($edit) ? ['id' => $edit] : [], 'Annuleren', 'btn btn-default') . '&nbsp;';
	echo '<input type="submit" name="zend" value="Opslaan" class="btn btn-' . $btn . '">';
	$app['form_token']->generate();

	echo '</form>';

	echo '</div>';
	echo '</div>';

	include __DIR__ . '/include/footer.php';
	exit;
}

/**
 * delete a newsitem
 */

if ($del)
{
	$page_access = 'admin';
	require_once __DIR__ . '/include/web.php';

	if ($submit)
	{
		if ($error_token = $app['form_token']->get_error())
		{
			$app['alert']->error($error_token);
			cancel();
		}

		if($app['db']->delete('news', ['id' => $del]))
		{
			$app['xdb']->del('news_access', $del);

			$app['alert']->success('Nieuwsbericht verwijderd.');
			cancel();
		}

		$app['alert']->error('Nieuwsbericht niet verwijderd.');
	}

	$news = $app['db']->fetchAssoc('SELECT n.*
		FROM news n
		WHERE n.id = ?', [$del]);

	$news_access = $app['xdb']->get('news_access', $del)['data']['access'];

	$h1 = 'Nieuwsbericht ' . $news['headline'] . ' verwijderen?';
	$fa = 'calendar';

	include __DIR__ . '/include/header.php';

	$background = ($news['approved']) ? '' : ' bg-warning';


	echo '<div class="panel panel-default printview">';
	echo '<div class="panel-heading">';

	echo '<dl>';

	echo '<dt>Bericht</dt>';
	echo '<dd>';
	echo nl2br(htmlspecialchars($news['newsitem'],ENT_QUOTES));
	echo '</dd>';

	echo '<dt>Agendadatum</dt>';

	echo '<dd>';
	echo ($itemdate) ? $app['date_format']->get($itemdate, 'day') : '<i class="fa fa-times"></i>';
	echo '</dd>';

	echo '<dt>Locatie</dt>';
	echo '<dd>';
	echo ($news['location']) ? htmlspecialchars($news['location'], ENT_QUOTES) : '<i class="fa fa-times"></i>';
	echo '</dd>';

	echo '<dt>Ingegeven door</dt>';
	echo '<dd>';
	echo link_user($news['id_user']);
	echo '</dd>';

	echo '<dt>Goedgekeurd</dt>';
	echo '<dd>';
	echo ($news['approved']) ? 'Ja' : 'Nee';
	echo '</dd>';

	echo '<dt>Behoud na datum?</dt>';
	echo '<dd>';
	echo ($news['sticky']) ? 'Ja' : 'Nee';
	echo '</dd>';

	echo '<dt>Zichtbaarheid</dt>';
	echo '<dd>';
	echo $app['access_control']->get_label($news_access);
	echo '</dd>';

	echo '</dl>';

	echo '</div></div>';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<p class="text-danger"><strong>Ben je zeker dat dit nieuwsbericht ';
	echo 'moet verwijderd worden?</strong></p>';

	echo '<form method="post">';
	echo aphp('news', ['id' => $del], 'Annuleren', 'btn btn-default') . '&nbsp;';
	echo '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger">';
	$app['form_token']->generate();
	echo '</form>';

	echo '</div>';
	echo '</div>';

	include __DIR__ . '/include/footer.php';
	exit;
}

/**
 * show a newsitem
 */

if ($id)
{
	$page_access = 'guest';

	require_once __DIR__ . '/include/web.php';

	$show_visibility = ($s_user && $app['config']->get('template_lets')
		&& $app['config']->get('interlets_en')) || $s_admin ? true : false;

	$news = $app['db']->fetchAssoc('SELECT n.*
		FROM news n
		WHERE n.id = ?', [$id]);

	if (!$s_admin && !$news['approved'])
	{
		$app['alert']->error('Je hebt geen toegang tot dit nieuwsbericht.');
		cancel();
	}

	$news_access = $app['xdb']->get('news_access', $id)['data']['access'];

	if (!$app['access_control']->is_visible($news_access))
	{
		$app['alert']->error('Je hebt geen toegang tot dit nieuwsbericht.');
		cancel();
	}

	$and_approved_sql = ($s_admin) ? '' : ' and approved = \'t\' ';

	$rows = $app['xdb']->get_many(['agg_schema' => $app['this_group']->get_schema(),
		'agg_type' => 'news_access',
		'eland_id' => ['<' => $news['id']],
		'access' => $app['access_control']->get_visible_ary()], 'order by eland_id desc limit 1');

	$prev = (count($rows)) ? reset($rows)['eland_id'] : false;

	$rows = $app['xdb']->get_many(['agg_schema' => $app['this_group']->get_schema(),
		'agg_type' => 'news_access',
		'eland_id' => ['>' => $news['id']],
		'access' => $app['access_control']->get_visible_ary()], 'order by eland_id asc limit 1');

	$next = (count($rows)) ? reset($rows)['eland_id'] : false;

	$top_buttons = '';

	if($s_user || $s_admin)
	{
		$top_buttons .= aphp('news', ['add' => 1], 'Toevoegen', 'btn btn-success', 'Nieuws toevoegen', 'plus', true);

		if($s_admin)
		{
			$top_buttons .= aphp('news', ['edit' => $id], 'Aanpassen', 'btn btn-primary', 'Nieuwsbericht aanpassen', 'pencil', true);
			$top_buttons .= aphp('news', ['del' => $id], 'Verwijderen', 'btn btn-danger', 'Nieuwsbericht verwijderen', 'times', true);

			if (!$news['approved'])
			{
				$top_buttons .= aphp('news', ['approve' => $id], 'Goedkeuren', 'btn btn-warning', 'Nieuwsbericht goedkeuren en publiceren', 'check', true);
			}
		}
	}

	if ($prev)
	{
		$top_buttons .= aphp('news', ['id' => $prev], 'Vorige', 'btn btn-default', 'Vorige', 'chevron-down', true);
	}

	if ($next)
	{
		$top_buttons .= aphp('news', ['id' => $next], 'Volgende', 'btn btn-default', 'Volgende', 'chevron-up', true);
	}

	$top_buttons .= aphp('news', ['view' => $view_news], 'Lijst', 'btn btn-default', 'Lijst', 'calendar', true);

	$h1 = 'Nieuwsbericht: ' . htmlspecialchars($news['headline'], ENT_QUOTES);
	$fa = 'calendar';

	include __DIR__ . '/include/header.php';

	if ($show_visibility)
	{
		echo '<p>Zichtbaarheid: ';
		echo $app['access_control']->get_label($news_access);
		echo '</p>';
	}

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

	echo '<dd>';
	echo ($news['itemdate']) ? $app['date_format']->get($news['itemdate'], 'day') : '<i class="fa fa-times"></i>';
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

	include __DIR__ . '/include/footer.php';
	exit;
}

/**
 * show all newsitems
 */

$page_access = 'guest';
require_once __DIR__ . '/include/web.php';

$show_visibility = ($s_user && $app['config']->get('template_lets')
	&& $app['config']->get('interlets_en')) || $s_admin ? true : false;

if (!($view || $inline))
{
	cancel();
}

$v_list = ($view == 'list' || $inline) ? true : false;
$v_extended = ($view == 'extended' && !$inline) ? true : false;

$params = [
	'view'	=> $view,
];

$query = 'select * from news';

if(!$s_admin)
{
	$query .= ' where approved = \'t\'';
}

$query .= ' order by itemdate desc';

$news = $app['db']->fetchAll($query);

$news_access_ary = [];

$rows = $app['xdb']->get_many(['agg_schema' => $app['this_group']->get_schema(), 'agg_type' => 'news_access']);

foreach ($rows as $row)
{
	$access = $row['data']['access'];
	$news_access_ary[$row['eland_id']] = $access;
}

foreach ($news as $k => $n)
{
	$news_id = $n['id'];

	if (!isset($news_access_ary[$news_id]))
	{
		$app['xdb']->set('news_access', $news_id, ['access' => 'interlets']);
		$news[$k]['access'] = 'interlets';
		continue;
	}

	$news[$k]['access'] = $news_access_ary[$news_id];

	if (!$app['access_control']->is_visible($news[$k]['access']))
	{
		unset($news[$k]);
	}
}

if(($s_user || $s_admin) && !$inline)
{
	$top_buttons .= aphp('news', ['add' => 1], 'Toevoegen', 'btn btn-success', 'Nieuws toevoegen', 'plus', true);
}

if ($inline)
{
	echo '<h3>';
	echo aphp('news', ['view' => $view_news], 'Nieuws', false, false, 'calendar');
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

	include __DIR__ . '/include/header.php';
}

if (!count($news))
{
	echo '<div class="panel panel-warning">';
	echo '<div class="panel-heading">';
	echo '<p>Er zijn momenteel geen nieuwsberichten.</p>';
	echo '</div></div>';

	if (!$inline)
	{
		include __DIR__ . '/include/footer.php';
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
		echo $s_admin ? '<th data-hide="phone">Goedgekeurd</th>' : '';
		echo $show_visibility ? '<th data-hide="phone, tablet">Zichtbaar</th>' : '';
		echo '</tr>';
		echo '</thead>';
	}

	echo '<tbody>';

	foreach ($news as $n)
	{
		echo '<tr';
		echo $n['approved'] ? '' : ' class="warning"';
		echo '>';

		echo '<td>';
		echo aphp('news', ['id' => $n['id']], $n['headline']);
		echo '</td>';

		echo $app['date_format']->get_td($n['itemdate'], 'day');

		if ($s_admin && !$inline)
		{
			echo '<td>';
			echo $n['approved'] ? 'Ja' : 'Nee';
			echo '</td>';
		}

		if ($show_visibility)
		{
			echo '<td>';
			echo $app['access_control']->get_label($n['access']);
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
		$background = $n['approved'] ? '' : ' bg-warning';

		echo '<div class="panel panel-info printview">';
		echo '<div class="panel-body' . $background . '">';

		echo '<div class="media">';
		echo '<div class="media-body">';
		echo '<h3 class="media-heading">';
		echo aphp('news', ['id' => $n['id']], $n['headline']);
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

		if ($n['itemdate'])
		{
			echo '<dt>';
			echo 'Agendadatum';
			echo '</dt>';
			echo '<dd>';
			echo $app['date_format']->get($n['itemdate'], 'day');

			if ($n['sticky'])
			{
				echo ' <i>(Nieuwsbericht blijft behouden na datum)</i>';
			}
			echo '</dd>';
		}

		if ($show_visibility)
		{
			echo '<dt>';
			echo 'Zichtbaarheid';
			echo '</dt>';
			echo '<dd>';
			echo $app['access_control']->get_label($n['access']);
			echo '</dd>';
		}

		echo '</dl>';

		echo '</div>';
		echo '</div>';
		echo '</div>';

		echo '<div class="panel-footer">';
		echo '<p><i class="fa fa-user"></i> ' . link_user($n['id_user']);

		if ($s_admin)
		{
			echo '<span class="inline-buttons pull-right hidden-xs">';
			if (!$n['approved'])
			{
				echo aphp('news', ['approve' => $n['id']], 'Goedkeuren en publiceren', 'btn btn-warning btn-xs', false, 'check');
			}
			echo aphp('news', ['edit' => $n['id']], 'Aanpassen', 'btn btn-primary btn-xs', false, 'pencil');
			echo aphp('news', ['del' => $n['id']], 'Verwijderen', 'btn btn-danger btn-xs', false, 'times');
			echo '</span>';
		}
		echo '</p>';
		echo '</div>';

		echo '</div>';
	}
}

if (!$inline)
{
	include __DIR__ . '/include/footer.php';
}

function cancel($id = '')
{
	global $view_news;

	$params = ['view' => $view_news];

	if ($id)
	{
		$params['id'] = $id;
	}

	header('Location: ' . generate_url('news', $params));
	exit;
}
