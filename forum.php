<?php

$app['page_access'] = 'guest';
require_once __DIR__ . '/include/web.php';

$app['h1']->fa('comments-o');

$topic = $_GET['t'] ?? false;
$del = $_GET['del'] ?? false;
$edit = $_GET['edit'] ?? false;
$add = isset($_GET['add']) ? true : false;
$q = $_GET['q'] ?? '';
$submit = isset($_POST['zend']) ? true : false;

if (!($app['s_user'] || $app['s_admin']))
{
	if ($del)
	{
		$app['alert']->error('Je hebt geen rechten om te verwijderen.');
		$app['link']->redirect('forum', $app['pp_ary'], []);
	}

	if ($add)
	{
		$app['alert']->error('Je hebt geen rechten om te toe te voegen.');
		$app['link']->redirect('forum', $app['pp_ary'], []);
	}

	if ($edit)
	{
		$app['alert']->error('Je hebt geen rechten om aan te passen.');
		$app['link']->redirect('forum', $app['pp_ary'], []);
	}
}

if (!$app['config']->get('forum_en', $app['tschema']))
{
	$app['alert']->warning('De forum pagina is niet ingeschakeld.');
	redirect_default_page();
}

if ($del || $edit)
{
	$t = ($del) ? $del : $edit;

	$row = $app['xdb']->get('forum', $t, $app['tschema']);

	if ($row)
	{
		$forum_post = $row['data'];
		$forum_post['id'] = $row['eland_id'];
		$agg_version = $row['agg_version'];
	}

	if (!isset($forum_post))
	{
		$app['alert']->error('Post niet gevonden.');
		$app['link']->redirect('forum', $app['pp_ary'], []);
	}

	$s_owner = $forum_post['uid']
		&& $forum_post['uid'] == $app['s_id']
		&& $app['s_system_self']
		&& !$app['s_guest'];

	if (!($app['s_admin'] || $s_owner))
	{
		$str = ($forum_post['parent_id']) ? 'deze reactie' : 'dit onderwerp';

		if ($del)
		{
			$app['alert']->error('Je hebt onvoldoende rechten om ' . $str . ' te verwijderen.');
		}
		else
		{
			$app['alert']->error('Je hebt onvoldoende rechten om ' . $str . ' aan te passen.');
		}

		$app['link']->redirect('forum', $app['pp_ary'],
			['t' => $forum_post['parent_id'] ?: $t]);
	}

	$topic = $forum_post['parent_id'] ?? false;
}

if ($submit)
{
	if ($del)
	{
		if ($error_token = $app['form_token']->get_error())
		{
			$app['alert']->error($error_token);
			$app['link']->redirect('forum', $app['pp_ary'], []);
		}

		$app['xdb']->del('forum', $del, $app['tschema']);

		if (!isset($forum_post['parent_id']))
		{
			$rows = $app['xdb']->get_many(['agg_type' => 'forum',
				'agg_schema' => $app['tschema'],
				'data->>\'parent_id\'' => $del]);

			foreach ($rows as $row)
			{
				$app['xdb']->del('forum', $row['eland_id'], $app['tschema']);
			}

			$app['alert']->success('Het forum onderwerp is verwijderd.');
			$app['link']->redirect('forum', $app['pp_ary'], []);
		}

		$app['alert']->success('De reactie is verwijderd.');

		$app['link']->redirect('forum', $app['pp_ary'],
			['t' => $forum_post['parent_id']]);
	}

	$content = $_POST['content'];

	$content = trim(preg_replace('/(<br>)+$/', '', $_POST['content']));

	$content = str_replace(["\n", "\r", '<p>&nbsp;</p>', '<p><br></p>'], '', $content);

	$content = trim($content);

	$config_htmlpurifier = HTMLPurifier_Config::createDefault();
	$config_htmlpurifier->set('Cache.DefinitionImpl', null);
	$htmlpurifier = new HTMLPurifier($config_htmlpurifier);
	$content = $htmlpurifier->purify($content);

	$forum_post = ['content'	=> $content];

	if ($topic)
	{
		$forum_post['parent_id'] = $topic;
	}
	else
	{
		$forum_post['subject'] = $_POST['subject'];
		$forum_post['access']	= $_POST['access'];
	}

	if (!$edit)
	{
		$forum_post['uid'] = $app['s_master'] ? 0 : $app['s_id'];
	}

 	if (!($topic || $forum_post['subject']))
	{
		 $errors[] = 'Vul een onderwerp in.';
	}

 	if (strlen($forum_post['content']) < 2)
	{
		 $errors[] = 'De inhoud van je bericht is te kort.';
	}

	if (!$topic)
	{
		$access_error = $app['access_control']->get_post_error();

		if ($access_error)
		{
			$errors[] = $access_error;
		}
	}

	if ($token_error = $app['form_token']->get_error())
	{
		$errors[] = $token_error;
	}

	if (count($errors))
	{
		$app['alert']->error($errors);
	}
	else if ($edit)
	{
		$app['xdb']->set('forum', $edit, $forum_post, $app['tschema']);

		$app['alert']->success((($topic) ? 'Reactie' : 'Onderwerp') . ' aangepast.');

		$app['link']->redirect('forum', $app['pp_ary'],
			['t' => $topic]);
	}
	else
	{
		$new_id = substr(sha1(microtime() . $app['tschema']), 0, 24);

		$app['xdb']->set('forum', $new_id, $forum_post, $app['tschema']);

		$app['alert']->success(($topic ? 'Reactie' : 'Onderwerp') . ' toegevoegd.');

		$app['link']->redirect('forum', $app['pp_ary'],
			['t' => $topic]);
	}
}

if ($del)
{
	if (isset($forum_post['parent_id']))
	{
		$app['h1']->add('Reactie');
		$t = $forum_post['parent_id'];
	}
	else
	{
		$t = $forum_post['id'];
		$app['h1']->add('Forum onderwerp ');
		$app['h1']->add($app['link']->link_no_attr('forum',
			$app['pp_ary'], ['t' => $t], $forum_post['subject']));
	}

	$h1 .= ' verwijderen?';

	require_once __DIR__ . '/include/header.php';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<p>' . $forum_post['content'] . '</p>';

	echo '<form method="post">';
	echo $app['link']->btn_cancel('forum', $app['pp_ary'], ['f' => $t]);

	echo '&nbsp;';
	echo '<input type="submit" value="Verwijderen" ';
	echo 'name="zend" class="btn btn-danger">';
	echo $app['form_token']->get_hidden_input();

	echo '</form>';

	echo '</div>';
	echo '</div>';
	require_once __DIR__ . '/include/footer.php';
	exit;
}

/**
 * add / edit topic / reply
 */

if ($add || $edit)
{
	$app['assets']->add(['summernote', 'rich_edit.js', 'access_input_cache.js']);

	if ($topic)
	{
		$row = $app['xdb']->get('forum', $topic, $app['tschema']);

		if ($row)
		{
			$topic_post = $row['data'];
		}

		if (!$app['access_control']->is_visible($topic_post['access']))
		{
			$app['alert']->error('Je hebt geen toegang tot dit forum onderwerp.');
			$app['link']->redirect('forum', $app['pp_ary'], []);
		}
	}

	if ($edit)
	{
		$app['h1']->add($topic ? 'Reactie aanpassen' : 'Forum onderwerp aanpassen');
	}
	else
	{
		$app['h1']->add($topic ? 'Nieuwe reactie' : 'Nieuw forum onderwerp');
	}

	include __DIR__ . '/include/header.php';

	echo '<div class="panel panel-info" id="add">';
	echo '<div class="panel-heading">';

	echo '<form method="post">';

	if (!$topic)
	{
		echo '<div class="form-group">';
		echo '<input type="text" class="form-control" ';
		echo 'id="subject" name="subject" ';
		echo 'placeholder="Onderwerp" ';
		echo 'value="';
		echo $forum_post['subject'];
		echo '" required>';
		echo '</div>';
	}

	echo '<div class="form-group">';
	echo '<textarea name="content" ';
	echo 'class="form-control rich-edit" ';
	echo 'id="content" rows="4" required>';
	echo $forum_post['content'];
	echo '</textarea>';
	echo '</div>';

	if (!$topic)
	{
		if (!$edit)
		{
			$forum_post['access'] = false;
		}

		if ($app['s_user'])
		{
			$omit_access = 'admin';
			$forum_post['access'] = ($forum_post['access']) ?: 1;
		}
		else
		{
			$omit_access = false;
		}

		echo $app['access_control']->get_radio_buttons('forum_topic', $forum_post['access'], $omit_access);
	}

	$str = $topic ? 'Reactie' : 'Onderwerp';
	$btn = $edit ? 'primary' : 'success';
	$action = $edit ? 'aanpassen' : 'toevoegen';

	$cancel_dest = $topic ? (($edit) ? ['t' => $topic] : []) : ['t' => $t];
	echo $app['link']->btn_cancel('forum', $app['pp_ary'], $cancel_dest);

	echo '&nbsp;';
	echo '<input type="submit" name="zend" value="';
	echo $str . ' ' . $action . '" ';
	echo 'class="btn btn-' . $btn . '">';
	echo $app['form_token']->get_hidden_input();

	echo '</form>';

	echo '</div>';
	echo '</div>';

	include __DIR__ . '/include/footer.php';
	exit;
}

/**
 * Show topic
 */

if ($topic)
{
	$show_visibility = ($app['s_user']
		&& $app['config']->get('template_lets', $app['tschema'])
		&& $app['config']->get('interlets_en', $app['tschema']))
		|| $app['s_admin'] ? true : false;

	$forum_posts = [];

	$row = $app['xdb']->get('forum', $topic, $app['tschema']);

	if ($row)
	{
		$topic_post = $row['data'];
		$topic_post['ts'] = $row['event_time'];

		if ($row['agg_version'] > 1)
		{
			$topic_post['edit_count'] = $row['agg_version'] - 1;
		}
	}

	$topic_post['id'] = $topic;

	$s_owner = $topic_post['uid']
		&& $topic_post['uid'] == $app['s_id']
		&& $app['s_system_self']
		&& !$app['s_guest'];

	if (!$app['access_control']->is_visible($topic_post['access']) && !$s_owner)
	{
		$app['alert']->error('Je hebt geen toegang tot dit forum onderwerp.');
		$app['link']->redirect('forum', $app['pp_ary'], []);
	}

	$forum_posts[] = $topic_post;

	$rows = $app['xdb']->get_many(['agg_schema' => $app['tschema'],
		'agg_type' => 'forum',
		'data->>\'parent_id\'' => $topic], 'order by event_time asc');

	if (count($rows))
	{
		foreach ($rows as $row)
		{
			$data = $row['data'] + ['ts' => $row['event_time'], 'id' => $row['eland_id']];

			if ($row['agg_version'] > 1)
			{
				$data['edit_count'] = $row['agg_version'] - 1;
			}

			$forum_posts[] = $data;
		}
	}

	$rows = $app['xdb']->get_many([
		'agg_schema' => $app['tschema'],
		'agg_type' => 'forum',
		'event_time' => ['>' => $topic_post['ts']],
		'access' => $app['access_control']->get_visible_ary(),
	], 'order by event_time asc limit 1');

	$prev = count($rows) ? reset($rows)['eland_id'] : false;

	$rows = $app['xdb']->get_many([
		'agg_schema' => $app['tschema'],
		'agg_type' => 'forum',
		'event_time' => ['<' => $topic_post['ts']],
		'access' => $app['access_control']->get_visible_ary(),
	], 'order by event_time desc limit 1');

	$next = count($rows) ? reset($rows)['eland_id'] : false;

	if ($app['s_admin'] || $s_owner)
	{
		$app['btn_top']->edit('forum', $app['pp_ary'],
			['edit' => $topic], 'Onderwerp aanpassen');
		$app['btn_top']->del('forum', $app['pp_ary'],
			['del' => $topic], 'Onderwerp verwijderen');
	}

	$prev_ary = $prev ? ['t' => $prev] : [];
	$next_ary = $next ? ['t' => $next] : [];

	$app['btn_nav']->nav('forum', $app['pp_ary'],
		$prev_ary, $next_ary, false);

	$app['btn_nav']->nav_list('forum', $app['pp_ary'],
		[], 'Forum onderwerpen', 'comments');

	$app['assets']->add(['summernote', 'rich_edit.js']);

	$app['h1']->add($topic_post['subject']);

	require_once __DIR__ . '/include/header.php';

	if ($show_visibility)
	{
		echo '<p>Zichtbaarheid: ';
		echo $app['access_control']->get_label($topic_post['access']);
		echo '</p>';
	}

	foreach ($forum_posts as $p)
	{
		$s_owner = $p['uid']
			&& $p['uid'] == $app['s_id']
			&& $app['s_system_self']
			&& !$app['s_guest'];

		$pid = $p['id'];

		echo '<div class="panel panel-default printview">';

		echo '<div class="panel-body">';
		echo $p['content'];
		echo '</div>';

		echo '<div class="panel-footer">';
		echo '<p>';
		echo link_user((int) $p['uid'], $app['tschema']);
		echo ' @';
		echo $app['date_format']->get($p['ts'], 'min', $app['tschema']);
		echo (isset($p['edit_count'])) ? ' Aangepast: ' . $p['edit_count'] : '';

		if ($app['s_admin'] || $s_owner)
		{
			echo '<span class="inline-buttons pull-right">';
			echo $app['link']->link_fa('forum', $app['pp_ary'],
				['edit' => $pid], 'Aanpassen',
				['class' => 'btn btn-primary btn-xs'], 'pencil');
			echo $app['link']->link_fa('forum', $app['pp_ary'],
				['del' => $pid], 'Verwijderen',
				['class' => 'btn btn-danger btn-xs'], 'times');
			echo '</span>';
		}

		echo '</p>';
		echo '</div>';

		echo '</div>';
	}

	if ($app['s_user'] || $app['s_admin'])
	{
		echo '<h3>Reactie toevoegen</h3>';

		echo '<div class="panel panel-info" id="add">';
		echo '<div class="panel-heading">';

		echo '<form method="post">';

		echo '<div class="form-group">';
		echo '<textarea name="content" ';
		echo 'class="form-control rich-edit" ';
		echo 'id="content" rows="4" required>';
		echo $forum_post['content'] ?? '';
		echo '</textarea>';
		echo '</div>';

		$str = $topic ? 'Reactie' : 'Onderwerp';
		$btn = $edit ? 'primary' : 'success';
		$action = $edit ? 'aanpassen' : 'toevoegen';

		echo '<input type="submit" name="zend" ';
		echo 'value="Reactie toevoegen" ';
		echo 'class="btn btn-success">';
		echo $app['form_token']->get_hidden_input();

		echo '</form>';

		echo '</div>';
		echo '</div>';
	}

	include __DIR__ . '/include/footer.php';
	exit;
}

/*
 * show topic list
 */

$rows = $app['xdb']->get_many([
	'agg_schema' => $app['tschema'],
	'agg_type' => 'forum',
	'access' => $app['access_control']->get_visible_ary()], 'order by event_time desc');

if (count($rows))
{
	$forum_posts = [];

	foreach ($rows as $row)
	{
		$replies = $app['xdb']->get_many(['agg_schema' => $app['tschema'],
			'agg_type' => 'forum',
			'data->>\'parent_id\'' => $row['eland_id']]);

		$forum_posts[] = $row['data'] + [
			'id' 		=> $row['eland_id'],
			'ts' 		=> $row['event_time'],
			'replies'	=> count($replies),
		];
	}
}

if ($app['s_admin'] || $app['s_user'])
{
	$app['btn_top']->add('forum', $app['pp_ary'],
		['add' => 1], 'Onderwerp toevoegen');
}

if ($app['s_admin'])
{
	$app['btn_nav']->csv();
}

$show_visibility = (!$app['s_guest']
	&& $app['config']->get('template_lets', $app['tschema'])
	&& $app['config']->get('interlets_en', $app['tschema']))
	|| $app['s_admin'];

$app['h1']->add('Forum');

require_once __DIR__ . '/include/header.php';

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo '<form method="get">';
echo '<div class="row">';
echo '<div class="col-xs-12">';
echo '<div class="input-group">';
echo '<span class="input-group-addon">';
echo '<i class="fa fa-search"></i>';
echo '</span>';
echo '<input type="text" class="form-control" id="q" name="q" value="' . $q . '" ';
echo 'placeholder="Zoeken">';
echo '</div>';
echo '</div>';
echo '</div>';
echo '</form>';

echo '</div>';
echo '</div>';

$forum_empty = true;

foreach($forum_posts as $p)
{
	if ($app['access_control']->is_visible($p['access']))
	{
		$forum_empty = false;
		break;
	}
}

if ($forum_empty)
{
	echo '<div class="panel panel-default">';
	echo '<div class="panel-heading">';
	echo '<p>Er zijn nog geen forum onderwerpen.</p>';
	echo '</div></div>';

	include __DIR__ . '/include/footer.php';
	exit;
}

echo '<div class="panel panel-default printview">';

echo '<div class="table-responsive">';
echo '<table class="table table-bordered table-striped table-hover footable csv"';
echo ' data-filter="#q" data-filter-minimum="1">';
echo '<thead>';

echo '<tr>';
echo '<th>Onderwerp</th>';
echo '<th>Reacties</th>';
echo '<th data-hide="phone, tablet">Gebruiker</th>';
echo '<th data-hide="phone, tablet" data-sort-initial="descending" ';
echo 'data-type="numeric">Tijdstip</th>';
echo $show_visibility ? '<th data-hide="phone, tablet">Zichtbaarheid</th>' : '';
echo '</tr>';

echo '</thead>';
echo '<tbody>';

foreach($forum_posts as $p)
{
	if (!$app['access_control']->is_visible($p['access']))
	{
		continue;
	}

	$s_owner = $p['uid']
		&& $app['s_id'] == $p['uid']
		&& $app['s_system_self']
		&& !$app['s_guest'];

	$pid = $p['id'];

	echo '<tr>';

	echo '<td>';
	echo $app['link']->link_no_attr('forum', $app['pp_ary'],
		['t' => $pid], $p['subject']);
	echo '</td>';

	echo '<td>';
	echo $p['replies'];
	echo '</td>';

	echo '<td>';
	echo link_user($p['uid'], $app['tschema']);
	echo '</td>';

	echo $app['date_format']->get_td($p['ts'], 'min', $app['tschema']);

	if ($show_visibility)
	{
		echo '<td>';
		echo $app['access_control']->get_label($p['access']);
		echo '</td>';
	}

	echo '</tr>';
}

echo '</tbody>';
echo '</table>';
echo '</div>';
echo '</div>';

include __DIR__ . '/include/footer.php';
