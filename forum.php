<?php

$rootpath = './';
$page_access = 'guest';
require_once $rootpath . 'includes/inc_default.php';

$fa = 'comments-o';

$mdb->connect();

$topic = (isset($_GET['t'])) ? $_GET['t'] : false;
$del = (isset($_GET['del'])) ? $_GET['del'] : false;
$edit = (isset($_GET['edit'])) ? $_GET['edit'] : false;
$add = (isset($_GET['add'])) ? true : false;
$q = (isset($_GET['q'])) ? $_GET['q'] : '';

if (!($s_user || $s_admin))
{
	if ($del)
	{
		$alert->error('Je hebt geen rechten om te verwijderen.');
		cancel();
	}
	if ($add)
	{
		$alert->error('Je hebt geen rechten om te toe te voegen.');
		cancel();
	}
	if ($edit)
	{
		$alert->error('Je hebt geen rechten om aan te passen.');
		cancel();
	}
}

$submit = (isset($_POST['zend'])) ? true : false;

if (!readconfigfromdb('forum_en'))
{
	$alert->error('De forum pagina is niet ingeschakeld.');
	redirect_index();
}

if ($del || $edit)
{
	$t = ($del) ? $del : $edit;

	$row = $exdb->get('forum', $t);

	if ($row)
	{
		$forum_post = $row['data'];
		$agg_version = $row['agg_version'];
	}
	else
	{
		$forum_post = $mdb->forum->findOne(['_id' => new MongoId($t)]);

		set_forum_post($forum_post);
	}

	if (!isset($forum_post))
	{
		$alert->error('Post niet gevonden.');
		cancel();
	}

	$s_owner = ($forum_post['uid'] && $forum_post['uid'] == $s_id && $s_group_self && !$s_guest) ? true : false;

	if (!($s_admin || $s_owner))
	{
		$str = ($forum_post['parent_id']) ? 'deze reactie' : 'dit onderwerp';

		if ($del)
		{
			$alert->error('Je hebt onvoldoende rechten om ' . $str . ' te verwijderen.');
		}
		else
		{
			$alert->error('Je hebt onvoldoende rechten om ' . $str . ' aan te passen.');
		}

		cancel(($forum_post['parent_id']) ?: $t);
	}

	$topic = (isset($forum_post['parent_id'])) ? $forum_post['parent_id'] : false;
}

if ($submit)
{
	if ($del)
	{
		if ($error_token = get_error_form_token())
		{
			$alert->error($error_token);
			cancel();
		}

		$mdb->forum->remove(
			['_id' => new MongoId($del)],
			['justOne'	=> true]
		);

		$exdb->del('forum', $del);

		if (!$forum_post['parent_id'])
		{
			$mdb->forum->remove(
				['parent_id' => $del]
			);

			$rows = $exdb->get_many(['agg_type' => $forum, 'agg_schema' => $schema, 'data->>\'parent_id\'' => $del]);

			foreach ($rows as $row)
			{
				$exdb->del($row['agg_type'], $row['eland_id']); 
			}

			$alert->success('Het forum onderwerp is verwijderd.');
			cancel();
		}

		$alert->success('De reactie is verwijderd.');
		cancel($forum_post['parent_id']);
	}

	$content = $_POST['content'];

	$content = trim(preg_replace('/(<br>)+$/', '', $_POST['content']));

	$content = str_replace(["\n", "\r", '<p>&nbsp;</p>'], '', $content);

	$content = trim($content);

	$forum_post = ['content'	=> $content];

	if ($topic)
	{
		$forum_post['parent_id'] = $topic;
	}
	else
	{
		$forum_post['subject'] = $_POST['subject'];
		$forum_post['access']	= $access_control->get_post_value();
	}

	if ($edit)
	{
		$forum_post['modified'] = gmdate('Y-m-d H:i:s');
	}
	else
	{
		$forum_post['ts'] = gmdate('Y-m-d H:i:s');
		$forum_post['uid'] = ($s_master) ? 0 : $s_id;
	}

 	if (!($forum_post['subject'] || $topic))
	{
		 $errors[] = 'Vul een onderwerp in.';
	}

 	if (strlen($forum_post['content']) < 2)
	{
		 $errors[] = 'De inhoud van je bericht is te kort.';
	}

	if (!$topic)
	{
		$access_error = $access_control->get_post_error();

		if ($access_error)
		{
			$errors[] = $access_error;
		}
	}

	if (!$topic && ($forum_post['access'] < $access_level || $forum_post['access'] > 2))
	{
		$errors[] = 'Ongeldige zichtbaarheid';
	}

	if ($token_error = get_error_form_token())
	{
		$errors[] = $token_error;
	}

	if (count($errors))
	{
		$alert->error($errors);
	}
	else if ($edit)
	{
		$mdb->forum->update(['_id' => new MongoId($edit)],
			['$set'	=> $forum_post, '$inc' => ['edit_count' => 1]],
			['upsert'	=> true]);

		$forum_post['id'] = $edit;

		set_forum_post($forum_post);

		$alert->success((($topic) ? 'Reactie' : 'Onderwerp') . ' aangepast.');
		cancel($topic);
	}
	else
	{
		$forum_post['_id'] = new MongoId();

		$mdb->forum->insert($forum_post);

		set_forum_post($forum_post);

		$alert->success((($topic) ? 'Reactie' : 'Onderwerp') . ' toegevoegd.');
		cancel($topic);
	}
}

if ($del)
{
	if (isset($forum_post['parent_id']))
	{
		$h1 = 'Reactie';
		$t = $forum_post['parent_id'];
	}
	else
	{
		$t = $forum_post['id'];
		$h1 = 'Forum onderwerp ' . aphp('forum', ['t' => $t], $forum_post['subject']);
	}

	$h1 .= ' verwijderen?';

	require_once $rootpath . 'includes/inc_header.php';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<p>' . $forum_post['content'] . '</p>';

	echo '<form method="post">';

	echo aphp('forum', ['t' => $t], 'Annuleren', 'btn btn-default') . '&nbsp;';
	echo '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger">';
	generate_form_token();

	echo '</form>';

	echo '</div>';
	echo '</div>';
	require_once $rootpath . 'includes/inc_footer.php';
	exit;
}

/**
 * add / edit topic / reply
 */

if ($add || $edit)
{
	$includejs = '<script src="' . $cdn_ckeditor . '"></script>
		<script src="' . $rootpath . 'js/forum.js"></script>
		<script src="' . $rootpath . 'js/access_input_cache.js"></script>';

	if ($topic)
	{
		$row = $exdb->get('forum', $topic);

		if ($row)
		{
			$topic_post = $row['data'];
		}
		else
		{
			$topic_post = $mdb->forum->findOne(['_id' => new MongoId($topic)]);

			set_forum_post($topic_post);
		}

		if (!$access_control->is_visible($topic_post['access']))
		{
			$alert->error('Je hebt geen toegang tot dit forum onderwerp.');
			cancel();
		}
	}

	if ($edit)
	{
		$h1 = ($topic) ? 'Reactie aanpassen' : 'Forum onderwerp aanpassen';
	}
	else
	{
		$h1 = ($topic) ? 'Nieuwe reactie' : 'Nieuw forum onderwerp';
	}

	include $rootpath . 'includes/inc_header.php';

	echo '<div class="panel panel-info" id="add">';
	echo '<div class="panel-heading">';

	echo '<form method="post" class="form-horizontal">';

	if (!$topic)
	{
		echo '<div class="form-group">';
		echo '<div class="col-sm-12">';
		echo '<input type="text" class="form-control" id="subject" name="subject" ';
		echo 'placeholder="Onderwerp" ';
		echo 'value="' . $forum_post['subject'] . '" required>';
		echo '</div>';
		echo '</div>';
	}

	echo '<div class="form-group">';
	echo '<div class="col-sm-12">';
	echo '<textarea name="content" class="form-control" id="content" rows="4" required>';
	echo $forum_post['content'];
	echo '</textarea>';
	echo '</div>';
	echo '</div>';

	if (!$topic)
	{
		if (!$edit)
		{
			$forum_post['access'] = false;
		}

		if ($s_user)
		{
			$omit_access = 'admin';
			$forum_post['access'] = ($forum_post['access']) ?: 1;
		}
		else
		{
			$omit_access = false;
		}

		echo $access_control->get_radio_buttons('forum_topic', $forum_post['access'], $omit_access);
	}

	$str = ($topic) ? 'Reactie' : 'Onderwerp';
	$btn = ($edit) ? 'primary' : 'success';
	$action = ($edit) ? 'aanpassen' : 'toevoegen';
	$cancel_dest = ($topic) ? (($edit) ? ['t' => $topic] : []) : ['t' => $t];

	echo aphp('forum', $cancel_dest, 'Annuleren', 'btn btn-default') . '&nbsp;';
	echo '<input type="submit" name="zend" value="' . $str . ' ' . $action . '" class="btn btn-' . $btn . '">';
	generate_form_token();

	echo '</form>';

	echo '</div>';
	echo '</div>';

	include $rootpath . 'includes/inc_footer.php';
	exit;
}

/**
 * Show topic
 */
 
if ($topic)
{
	$forum_posts = [];

	$row = $exdb->get('forum', $topic);

	if ($row)
	{
		$topic_post = $row['data'];
		$topic_post['ts'] = $row['event_time'];

		if ($row['agg_version'] > 1)
		{
			$topic_post['edit_count'] = $row['agg_version'] - 1;
		}
	}
	else
	{
		$topic_post = $mdb->forum->findOne(['_id' => new MongoId($topic)]);

		set_forum_post($topic_post);
	}

	$topic_post['id'] = $topic;	

	$s_owner = ($topic_post['uid'] && $topic_post['uid'] == $s_id && $s_group_self && !$s_guest) ? true : false;

	if (!$access_control->is_visible($topic_post['access']) && !$s_owner)
	{
		$alert->error('Je hebt geen toegang tot dit forum onderwerp.');
		cancel();
	}

	$forum_post[] = $topic_post;

	$rows = $exdb->get_many(['agg_schema' => $schema,
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

	/* else
	{
	
		$find = ['$or'=> [['parent_id' => $topic], ['_id' => new MongoId($topic)]]];

		$forum_posts = $mdb->forum->find($find);
		$forum_posts->sort(['ts' => (($topic) ? 1 : -1)]);

		$forum_posts = iterator_to_array($forum_posts);
	}

	$find = [
		'parent_id' => ['$exists' => false],
		'access'	=> ['$gte'	=> (string) $access_level],
		'ts' 		=> ['$lt' => $topic_post['ts']],
	];

	$prev = $mdb->forum->findOne($find);

	$prev = ($prev) ? $prev['_id']->__toString() : false;

	$find = [
		'parent_id' => ['$exists' => false],
		'access' 	=> ['$gte'	=> (string) $access_level],
		'ts' 		=> ['$gt' => $topic_post['ts']],
	];

	$next = $mdb->forum->findOne($find);

	$next = ($next) ? $next['_id']->__toString() : false;

*/


	if ($s_admin || $s_owner)
	{
		$top_buttons .= aphp('forum', ['edit' => $topic], 'Onderwerp aanpassen', 'btn btn-primary', 'Onderwerp aanpassen', 'pencil', true);

		$top_buttons .= aphp('forum', ['del' => $topic], 'Onderwerp verwijderen', 'btn btn-danger', 'Onderwerp verwijderen', 'times', true);
	}

	if ($prev)
	{
		$top_buttons .= aphp('forum', ['t' => $prev], 'Vorige', 'btn btn-default', 'Vorige', 'chevron-down', true);
	}

	if ($next)
	{
		$top_buttons .= aphp('forum', ['t' => $next], 'Volgende', 'btn btn-default', 'Volgende', 'chevron-up', true);
	}

	$top_buttons .= aphp('forum', [], 'Forum onderwerpen', 'btn btn-default', 'Forum onderwerpen', 'comments', true);

	$includejs = '<script src="' . $cdn_ckeditor . '"></script>
		<script src="' . $rootpath . 'js/forum.js"></script>';

	$h1 = $topic_post['subject'];

	require_once $rootpath . 'includes/inc_header.php';

	if (!$s_guest)
	{
		echo '<p>Zichtbaarheid: ';
		echo $access_control->get_label($topic_post['access']);
		echo '</p>';
	}

	foreach ($forum_posts as $p)
	{
		$s_owner = ($p['uid'] && $p['uid'] == $s_id && $s_group_self && !$s_guest) ? true : false;

		$pid = $p['id'];

		echo '<div class="panel panel-default printview">';

		echo '<div class="panel-body">';
		echo $p['content'];
		echo '</div>';

		$time = strtotime($p['ts'] . ' UTC');
		$time = date('Y-m-d H:i:s', $time);

		echo '<div class="panel-footer">';
		echo '<p>' . link_user((int) $p['uid']) . ' @' . $time;
		echo (isset($p['edit_count'])) ? ' Aangepast: ' . $p['edit_count'] : '';

		if ($s_admin || $s_owner)
		{
			echo '<span class="inline-buttons pull-right">';
			echo aphp('forum', ['edit' => $pid], 'Aanpassen', 'btn btn-primary btn-xs', false, 'pencil');
			echo aphp('forum', ['del' => $pid], 'Verwijderen', 'btn btn-danger btn-xs', false, 'times');
			echo '</span>';
		}

		echo '</p>';
		echo '</div>';

		echo '</div>';
	}

	if ($s_user || $s_admin)
	{
		echo '<h3>Reactie toevoegen</h3>';

		echo '<div class="panel panel-info" id="add">';
		echo '<div class="panel-heading">';

		echo '<form method="post" class="form-horizontal">';

		echo '<div class="form-group">';
		echo '<div class="col-sm-12">';
		echo '<textarea name="content" class="form-control" id="content" rows="4" required>';
		echo isset($forum_post['content']) ? $forum_post['content'] : '';
		echo '</textarea>';
		echo '</div>';
		echo '</div>';

		$str = ($topic) ? 'Reactie' : 'Onderwerp';
		$btn = ($edit) ? 'primary' : 'success';
		$action = ($edit) ? 'aanpassen' : 'toevoegen';

		echo '<input type="submit" name="zend" value="Reactie toevoegen" class="btn btn-success">';
		generate_form_token();

		echo '</form>';

		echo '</div>';
		echo '</div>';
	}

	include $rootpath . 'includes/inc_footer.php';
	exit;
}

/*
 * show topic list
 */

$rows = $exdb->get_many(['agg_schema' => $schema,
	'agg_type' => 'forum',
	'data->>\'access\'' => ['is not null']], 'order by event_time');

if (count($rows))
{
	$forum_posts = [];

	foreach ($rows as $row)
	{
		$forum_posts[] = $row['data'] + ['id' => $row['eland_id']];
	}
}
else
{
	$find = ['parent_id' => ['$exists' => false]];

	$forum_posts = $mdb->forum->find($find);
	$forum_posts->sort(['ts' => (($topic) ? 1 : -1)]);

	$forum_posts = iterator_to_array($forum_posts);

	foreach ($forum_posts as $key => $forum_post)
	{
		set_forum_post($forum_post);

		$forum_posts[$key]['id'] = $forum_post['_id']->__toString();
	}
}

if ($s_admin || $s_user)
{
	$top_buttons .= aphp('forum', ['add' => 1], 'Onderwerp Toevoegen', 'btn btn-success', 'Onderwerp toevoegen', 'plus', true);
}

$h1 = 'Forum';

require_once $rootpath . 'includes/inc_header.php';

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
	if ($access_control->is_visible($p['access']))
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

	include $rootpath . 'includes/inc_footer.php';
	exit;	
}

echo '<div class="panel panel-default printview">';

echo '<div class="table-responsive">';
echo '<table class="table table-bordered table-striped table-hover footable"';
echo ' data-filter="#q" data-filter-minimum="1">';
echo '<thead>';

echo '<tr>';
echo '<th>Onderwerp</th>';
echo '<th data-hide="phone, tablet">Gebruiker</th>';
echo '<th data-hide="phone, tablet" data-sort-initial="descending" ';
echo 'data-type="numeric">Tijdstip</th>';
echo ($s_guest) ? '' : '<th data-hide="phone">Zichtbaarheid</th>';
echo ($s_admin) ? '<th data-hide="phone,tablet">Acties</th>' : '';
echo '</tr>';

echo '</thead>';
echo '<tbody>';

foreach($forum_posts as $p)
{
	if (!$access_control->is_visible($p['access']))
	{
		continue;
	}

	$s_owner = ($p['uid'] && $s_id == $p['uid'] && $s_group_self && !$s_guest) ? true : false;

	$pid = $p['id'];

	echo '<tr>';

	echo '<td>';
	echo aphp('forum', ['t' => $pid], $p['subject']);
	echo '</td>';
	echo '<td>' . link_user($p['uid']) . '</td>';

	$time_unix = strtotime($p['ts'] . ' UTC');
	$time = date('Y-m-d H:i:s', $time_unix);

	echo '<td data-value="' . $time_unix . '">' . $time . '</td>';

	if (!$s_guest)
	{
		echo '<td>' . $access_control->get_label($p['access']) . '</td>';
	}

	if ($s_admin)
	{
		echo '<td>';
		echo aphp('forum', ['edit' => $pid], 'Aanpassen', 'btn btn-primary btn-xs', false, 'pencil');
		echo '&nbsp;';
		echo aphp('forum', ['del' => $pid], 'Verwijderen', 'btn btn-danger btn-xs', false, 'times');
		echo '</td>';
	}
	echo '</tr>';

}

echo '</tbody>';
echo '</table>';
echo '</div>';
echo '</div>';

include $rootpath . 'includes/inc_footer.php';

function cancel($topic = null)
{
	$params = [];

	if ($topic)
	{
		$params['t'] = $topic;
	}

	header('Location: ' . generate_url('forum', $params));
	exit;
}

