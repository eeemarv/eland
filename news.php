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
	$app['page_access'] = 'admin';

	require_once __DIR__ . '/include/web.php';

	if ($app['db']->update($app['tschema'] . '.news', ['approved' => 't', 'published' => 't'], ['id' => $approve]))
	{
		$app['alert']->success('Nieuwsbericht goedgekeurd en gepubliceerd.');
	}
	else
	{
		$app['alert']->error('Goedkeuren en publiceren nieuwsbericht mislukt.');
	}
	cancel($approve);
}

/**
 * add or edit a newsitem
 */

if ($add || $edit)
{
	$app['page_access'] = 'user';
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
			$news['itemdate'] = $app['date_format']->reverse($news['itemdate'], $app['tschema']);

			if ($news['itemdate'] === '')
			{
				$errors[] = 'Fout formaat in agendadatum.';

				$news['itemdate'] = '';
			}
		}
		else
		{
			$errors[] = 'Geef een agendadatum op.';
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
	$news['approved'] = $app['s_admin'] ? 't' : 'f';
	$news['published'] = $app['s_admin'] ? 't' : 'f';
	$news['id_user'] = $app['s_master'] ? 0 : $app['s_id'];
	$news['cdate'] = gmdate('Y-m-d H:i:s');

	if ($app['db']->insert($app['tschema'] . '.news', $news))
	{
		$id = $app['db']->lastInsertId($app['tschema'] . '.news_id_seq');

		$app['xdb']->set('news_access', $id, [
			'access' => $_POST['access']
		], $app['tschema']);

		$app['alert']->success('Nieuwsbericht opgeslagen.');

		$news['id'] = $id;

		if(!$app['s_admin'])
		{
			$vars = [
				'news'			=> $news,
			];

			$app['queue.mail']->queue([
				'schema'	=> $app['tschema'],
				'to' 		=> $app['mail_addr_system']->get_newsadmin($app['tschema']),
				'template'	=> 'news/review_admin',
				'vars'		=> $vars,
			], 7000);

			$app['alert']->success('Nieuwsbericht wacht op goedkeuring en publicatie door een beheerder');
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
	if($app['db']->update($app['tschema'] . '.news', $news, ['id' => $edit]))
	{
		$app['xdb']->set('news_access', $edit, [
			'access' => $_POST['access']
		], $app['tschema']);

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
	$news = $app['db']->fetchAssoc('select *
		from ' . $app['tschema'] . '.news
		where id = ?', [$edit]);

	$news_access = $app['xdb']->get('news_access', $edit,
		$app['tschema'])['data']['access'];
}

if ($add && !$submit)
{
	$news['itemdate'] = gmdate('Y-m-d');
}

if ($add || $edit)
{
	$app['assets']->add([
		'datepicker',
	]);

	$h1 = 'Nieuwsbericht ';
	$h1 .= $add ? 'toevoegen' : 'aanpassen';
	$fa = 'calendar-o';

	include __DIR__ . '/include/header.php';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<form method="post">';

	echo '<div class="form-group">';
	echo '<label for="headline" class="control-label">';
	echo 'Titel</label>';
	echo '<input type="text" class="form-control" ';
	echo 'id="headline" name="headline" ';
	echo 'value="';
	echo $news['headline'];
	echo '" required maxlength="200">';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="itemdate" class="control-label">';
	echo 'Agenda datum</label>';
	echo '<div class="input-group">';
	echo '<span class="input-group-addon">';
	echo '<i class="fa fa-calendar"></i>';
	echo '</span>';
	echo '<input type="text" class="form-control" id="itemdate" name="itemdate" ';
	echo 'data-provide="datepicker" ';
	echo 'data-date-format="';
	echo $app['date_format']->datepicker_format($app['tschema']);
	echo '" ';
	echo 'data-date-language="nl" ';
	echo 'data-date-today-highlight="true" ';
	echo 'data-date-autoclose="true" ';
	echo 'data-date-orientation="bottom" ';
	echo 'value="';
	echo $app['date_format']->get($news['itemdate'], 'day', $app['tschema']);
	echo '" ';
	echo 'placeholder="';
	echo $app['date_format']->datepicker_placeholder($app['tschema']);
	echo '" ';
	echo 'required>';
	echo '</div>';
	echo '<p>Wanneer gaat dit door?</p>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="sticky" class="control-label">';
	echo '<input type="checkbox" id="sticky" name="sticky" ';
	echo 'value="1"';
	echo  $news['sticky'] ? ' checked="checked"' : '';
	echo '>';
	echo ' Behoud na datum</label>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="location" class="control-label">';
	echo 'Locatie</label>';
	echo '<div class="input-group">';
	echo '<span class="input-group-addon">';
	echo '<i class="fa fa-map-marker"></i>';
	echo '</span>';
	echo '<input type="text" class="form-control" ';
	echo 'id="location" name="location" ';
	echo 'value="';
	echo $news['location'];
	echo '" maxlength="128">';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="newsitem" class="control-label">';
	echo 'Bericht</label>';
	echo '<textarea name="newsitem" id="newsitem" ';
	echo 'class="form-control" rows="10" required>';
	echo $news['newsitem'];
	echo '</textarea>';
	echo '</div>';

	if ($app['s_user'])
	{
		$omit_access = 'admin';
	}
	else
	{
		$omit_access = false;
	}

	echo $app['access_control']->get_radio_buttons('news', $news_access, $omit_access);

	$btn = $add ? 'success' : 'primary';
	echo aphp('news', ($edit) ? ['id' => $edit] : [], 'Annuleren', 'btn btn-default');
	echo '&nbsp;';
	echo '<input type="submit" name="zend" ';
	echo 'value="Opslaan" class="btn btn-' . $btn . '">';
	echo $app['form_token']->get_hidden_input();

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
	$app['page_access'] = 'admin';
	require_once __DIR__ . '/include/web.php';

	if ($submit)
	{
		if ($error_token = $app['form_token']->get_error())
		{
			$app['alert']->error($error_token);
			cancel();
		}

		if($app['db']->delete($app['tschema'] . '.news', ['id' => $del]))
		{
			$app['xdb']->del('news_access', $del, $app['tschema']);

			$app['alert']->success('Nieuwsbericht verwijderd.');
			cancel();
		}

		$app['alert']->error('Nieuwsbericht niet verwijderd.');
	}

	$news = $app['db']->fetchAssoc('select n.*
		from ' . $app['tschema'] . '.news n
		where n.id = ?', [$del]);

	$news_access = $app['xdb']->get('news_access', $del,
		$app['tschema'])['data']['access'];

	$h1 = 'Nieuwsbericht ' . $news['headline'] . ' verwijderen?';
	$fa = 'calendar-o';

	include __DIR__ . '/include/header.php';

	$background = $news['approved'] ? '' : ' bg-warning';

	echo '<div class="panel panel-default printview">';
	echo '<div class="panel-heading">';

	echo '<dl>';

	echo '<dt>Goedgekeurd en gepubliceerd door Admin</dt>';
	echo '<dd>';
	echo $news['approved'] ? 'Ja' : 'Nee';
	echo '</dd>';

	echo '<dt>Agendadatum</dt>';

	echo '<dd>';

	if ($news['itemdate'])
	{
		echo $app['date_format']->get($news['itemdate'], 'day', $app['tschema']);
	}
	else
	{
		echo '<i class="fa fa-times"></i>';
	}

	echo '</dd>';

	echo '<dt>Behoud na Datum?</dt>';
	echo '<dd>';
	echo $news['sticky'] ? 'Ja' : 'Nee';
	echo '</dd>';

	echo '<dt>Locatie</dt>';
	echo '<dd>';

	if ($new['location'])
	{
		echo htmlspecialchars($news['location'], ENT_QUOTES);
	}
	else
	{
		echo '<i class="fa fa-times"></i>';
	}

	echo '</dd>';

	echo '<dt>Bericht/Details</dt>';
	echo '<dd>';
	echo nl2br(htmlspecialchars($news['newsitem'],ENT_QUOTES));
	echo '</dd>';

	echo '<dt>Zichtbaarheid</dt>';
	echo '<dd>';
	echo $app['access_control']->get_label($news_access);
	echo '</dd>';

	echo '<dt>Ingegeven door</dt>';
	echo '<dd>';
	echo link_user($news['id_user'], $app['tschema']);
	echo '</dd>';

	echo '</dl>';

	echo '</div></div>';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<p class="text-danger"><strong>';
	echo 'Ben je zeker dat dit nieuwsbericht ';
	echo 'moet verwijderd worden?</strong></p>';

	echo '<form method="post">';
	echo aphp('news', ['id' => $del], 'Annuleren', 'btn btn-default');
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

/**
 * Show newsitem/List all newsitems
 * Fetch all newsitems
 */

$app['page_access'] = 'guest';
require_once __DIR__ . '/include/web.php';

$show_visibility = ($app['s_user']
	&& $app['config']->get('template_lets', $app['tschema'])
	&& $app['config']->get('interlets_en', $app['tschema']))
	|| $app['s_admin'];

$news_access_ary = $no_access_ary = [];

$rows = $app['xdb']->get_many([
	'agg_schema' => $app['tschema'],
	'agg_type' => 'news_access',
]);

foreach ($rows as $row)
{
	$access = $row['data']['access'];
	$news_access_ary[$row['eland_id']] = $access;
}

$query = 'select * from ' . $app['tschema'] . '.news';

if(!$app['s_admin'])
{
	$query .= ' where approved = \'t\'';
}

$query .= ' order by itemdate ';
$query .= $app['config']->get('news_order_asc', $app['tschema']) === '1' ? 'asc' : 'desc';

$st = $app['db']->prepare($query);
$st->execute();

while ($row = $st->fetch())
{
	$news_id = $row['id'];
	$news[$news_id] = $row;

	if (!isset($news_access_ary[$news_id]))
	{
		$app['xdb']->set('news_access', $news_id, [
			'access' => 'interlets',
		], $app['tschema']);
		$news[$k]['access'] = 'interlets';
	}
	else
	{
		$news[$news_id]['access'] = $news_access_ary[$news_id];
	}

	if (!$app['access_control']->is_visible($news[$news_id]['access']))
	{
		unset($news[$news_id]);
		$no_access_ary[$news_id] = true;
	}
}

/**
 * show a newsitem
 */

if ($id)
{
	if (!isset($news[$id]))
	{
		$app['alert']->error('Dit nieuwsbericht bestaat niet.');
		cancel();
	}

	$news_item = $news[$id];

	if (!$app['s_admin'] && !$news_item['approved'])
	{
		$app['alert']->error('Je hebt geen toegang tot dit nieuwsbericht.');
		cancel();
	}

	if (isset($no_access_ary[$id]))
	{
		$app['alert']->error('Je hebt geen toegang tot dit nieuwsbericht.');
		cancel();
	}

	$next = $prev = $current_news = false;

	foreach($news as $nid => $ndata)
	{
		if ($current_news)
		{
			$next = $nid;
			break;
		}

		if ($id == $nid)
		{
			$current_news = true;
			continue;
		}

		$prev = $nid;
	}

	$top_buttons = '';

	if($app['s_admin'])
	{
		$top_buttons .= aphp('news',
			['edit' => $id],
			'Aanpassen',
			'btn btn-primary',
			'Nieuwsbericht aanpassen',
			'pencil',
			true
		);
		$top_buttons .= aphp('news',
			['del' => $id],
			'Verwijderen',
			'btn btn-danger',
			'Nieuwsbericht verwijderen',
			'times',
			true
		);

		if (!$news_item['approved'])
		{
			$top_buttons .= aphp('news', ['approve' => $id], 'Goedkeuren', 'btn btn-warning', 'Nieuwsbericht goedkeuren en publiceren', 'check', true);
		}
	}

	$top_buttons_right = '<span class="btn-group" role="group">';

	$prev_url = $prev ? generate_url('news', ['id' => $prev]) : '';
	$next_url = $next ? generate_url('news', ['id' => $next]) : '';

	$top_buttons_right .= btn_item_nav($prev_url, false, false);
	$top_buttons_right .= btn_item_nav($next_url, true, true);
	$top_buttons_right .= aphp('news', [], '', 'btn btn-default', 'Lijst', 'calendar-o');
	$top_buttons_right .= '</span>';

	$h1 = 'Nieuwsbericht: ' . htmlspecialchars($news_item['headline'], ENT_QUOTES);
	$fa = 'calendar-o';

	include __DIR__ . '/include/header.php';

	$background = $news_item['approved'] ? '' : ' bg-warning';

	echo '<div class="panel panel-default printview">';
	echo '<div class="panel-body' . $background . '">';

	echo '<dl>';

	if ($app['s_admin'])
	{
		echo '<dt>Goedgekeurd en gepubliceerd door Admin</dt>';
		echo '<dd>';
		echo $news_item['approved'] ? 'Ja' : 'Nee';
		echo '</dd>';
	}

	echo '<dt>Agendadatum</dt>';

	echo '<dd>';

	if ($news_item['itemdate'])
	{
		echo $app['date_format']->get($news_item['itemdate'], 'day', $app['tschema']);
	}
	else
	{
		echo '<i class="fa fa-times"></i>';
	}

	echo '</dd>';

	echo '<dt>Behoud na datum?</dt>';
	echo '<dd>';
	echo $news_item['sticky'] ? 'Ja' : 'Nee';
	echo '</dd>';

	echo '<dt>Locatie</dt>';
	echo '<dd>';

	if ($news_item['location'])
	{
		echo htmlspecialchars($news_item['location'], ENT_QUOTES);
	}
	else
	{
		echo '<i class="fa fa-times"></i>';
	}

	echo '</dd>';

	echo '<dt>Bericht/Details</dt>';
	echo '<dd>';
	echo nl2br(htmlspecialchars($news_item['newsitem'],ENT_QUOTES));
	echo '</dd>';

	if ($show_visibility)
	{
		echo '<dt>Zichtbaarheid</dt>';
		echo '<dd>';
		echo $app['access_control']->get_label($news_item['access']);
		echo '</dd>';
	}

	echo '<dt>Ingegeven door</dt>';
	echo '<dd>';
	echo link_user($news_item['id_user'], $app['tschema']);
	echo '</dd>';

	echo '</dl>';

	echo '</div>';
	echo '</div>';

	include __DIR__ . '/include/footer.php';
	exit;
}

/**
 * show all newsitems
 */

if (!($app['p_view'] || $app['p_inline']))
{
	cancel();
}

$v_list = $app['p_view'] === 'list' || $app['p_inline'];
$v_extended = $app['p_view'] === 'extended' && !$app['p_inline'];

$params = [];

if(($app['s_user'] || $app['s_admin']) && !$app['p_inline'])
{
	$top_buttons .= aphp('news',
		['add' => 1],
		'Toevoegen',
		'btn btn-success',
		'Nieuws toevoegen',
		'plus',
		true);
}

if ($app['p_inline'])
{
	echo '<h3>';
	echo aphp('news',
		[],
		'Nieuws',
		false,
		false,
		'calendar-o');
	echo '</h3>';
}
else
{
	$h1 = 'Nieuws';

	$v_params = $params;

	$csv_en = $app['s_admin'] && $v_list;

	$top_buttons_right = '<span class="btn-group" role="group">';

	$active = $v_list ? ' active' : '';
	$v_params['view'] = 'list';
	$top_buttons_right .= aphp(
		'news',
		$v_params,
		'',
		'btn btn-default' . $active,
		'lijst',
		'align-justify'
	);

	$active = $v_extended ? ' active' : '';
	$v_params['view'] = 'extended';
	$top_buttons_right .= aphp(
		'news',
		$v_params,
		'',
		'btn btn-default' . $active,
		'Lijst met omschrijvingen',
		'th-list'
	);

	$top_buttons_right .= '</span>';

	$fa = 'calendar-o';

	include __DIR__ . '/include/header.php';
}

if (!count($news))
{
	echo '<div class="panel panel-default">';
	echo '<div class="panel-heading">';
	echo '<p>Er zijn momenteel geen nieuwsberichten.</p>';
	echo '</div></div>';

	if (!$app['p_inline'])
	{
		include __DIR__ . '/include/footer.php';
	}
	exit;
}

if ($v_list)
{
	echo '<div class="panel panel-warning printview">';
	echo '<div class="table-responsive">';
	echo '<table class="table table-striped ';
	echo 'table-hover table-bordered footable csv">';

	if (!$app['p_inline'])
	{
		echo '<thead>';
		echo '<tr>';
		echo '<th>Titel</th>';
		echo '<th data-hide="phone" ';
		echo 'data-sort-initial="descending">Agendadatum</th>';
		echo $app['s_admin'] ? '<th data-hide="phone">Goedgekeurd</th>' : '';
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

		echo $app['date_format']->get_td($n['itemdate'], 'day', $app['tschema']);

		if ($app['s_admin'] && !$app['p_inline'])
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
		echo '<h2 class="media-heading">';
		echo aphp('news', ['id' => $n['id']], $n['headline']);
		echo '</h2>';

		if (!$n['approved'])
		{
			echo '<p class="text-warning">';
			echo '<strong>';
			echo 'Dit nieuwsbericht wacht op goedkeuring en publicatie door een admin';
			echo '</strong>';
			echo '</p>';
		}

		echo '<dl>';

		echo '<dt>';
		echo 'Agendadatum';
		echo '</dt>';
		echo '<dd>';

		if ($n['itemdate'])
		{
			echo $app['date_format']->get($n['itemdate'], 'day', $app['tschema']);

			echo '<br><i>';

			if ($n['sticky'])
			{
				echo 'Dit nieuwsbericht blijft behouden na deze datum.';
			}
			else
			{
				echo 'Dit nieuwsbericht wordt automatisch gewist na deze datum.';
			}

			echo '</i>';

		}
		else
		{
			echo '<i class="fa fa-times></i>';
		}

		echo '</dd>';

		echo '<dt>';
		echo 'Locatie';
		echo '</dt>';
		echo '<dd>';

		if ($n['location'])
		{
			echo htmlspecialchars($n['location'], ENT_QUOTES);
		}
		else
		{
			echo '<i class="fa fa-times"></i>';
		}

		echo '</dd>';

		echo '</dl>';

		echo '<h4>Bericht/Details</h4>';
		echo '<p>';
		echo nl2br(htmlspecialchars($n['newsitem'],ENT_QUOTES));
		echo '</p>';

		echo '<dl>';

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
		echo '<p><i class="fa fa-user"></i> ';
		echo link_user($n['id_user'], $app['tschema']);

		if ($app['s_admin'])
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

if (!$app['p_inline'])
{
	include __DIR__ . '/include/footer.php';
}

function cancel(int $id = 0):void
{
	$params = [];

	if ($id)
	{
		$params['id'] = $id;
	}

	header('Location: ' . generate_url('news', $params));
	exit;
}
