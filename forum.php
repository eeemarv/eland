<?php

$rootpath = './';
$role = 'guest';
require_once $rootpath . 'includes/inc_default.php';

$fa = 'comments-o';

$mdb->connect();

$topic = (isset($_GET['t'])) ? $_GET['t'] : false;
$del = (isset($_GET['del'])) ? $_GET['del'] : false;
$edit = (isset($_GET['edit'])) ? $_GET['edit'] : false;
$add = (isset($_GET['add'])) ? true : false;

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

$submit = ($_POST['zend']) ? true : false;

if (!readconfigfromdb('forum_en'))
{
	$alert->error('De forum pagina is niet ingeschakeld.');
	redirect_index();
}

if ($del || $edit)
{
	$t = ($del) ? $del : $edit;

	$forum_post = $mdb->forum->findOne(array('_id' => new MongoId($t)));

	if (!$forum_post)
	{
		$alert->error('Post niet gevonden.');
		cancel();
	}

	$s_owner = ($forum_post['uid'] == $s_id) ? true : false;

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

	$topic = ($forum_post['parent_id']) ?: false;
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
			array('_id' => new MongoId($del)),
			array('justOne'	=> true)
		);

		if (!$forum_post['parent_id'])
		{
			$mdb->forum->remove(
				array('parent_id' => $del)
			);

			$alert->success('Het forum onderwerp is verwijderd.');
			cancel();
		}

		$alert->success('De reactie is verwijderd.');
		cancel($forum_post['parent_id']);
	}

	$content = $_POST['content'];

	$content = trim(preg_replace('/(<br>)+$/', '', $_POST['content']));

	$content = str_replace(array("\n", "\r", '<p>&nbsp;</p>'), '', $content);

	$content = trim($content);

	$forum_post = array(
		'content'	=> $content,
	);

	if ($topic)
	{
		$forum_post['parent_id'] = $topic;
	}
	else
	{
		$forum_post['subject'] = $_POST['subject'];
		$forum_post['access']	= $_POST['access'];
	}

	if ($edit)
	{
		$forum_post['modified'] = gmdate('Y-m-d H:i:s');
	}
	else
	{
		$forum_post['ts'] = gmdate('Y-m-d H:i:s');
		$forum_post['uid'] = $s_id;
	}

    $errors = array();

 	if (!($forum_post['subject'] || $topic))
	{
		 $errors[] = 'Vul een onderwerp in.';
	}

 	if (strlen($forum_post['content']) < 2)
	{
		 $errors[] = 'De inhoud van je bericht is te kort.';
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
		$mdb->forum->update(array('_id' => new MongoId($edit)),
			array('$set'	=> $forum_post, '$inc' => array('edit_count' => 1)),
			array('upsert'	=> true));

		$alert->success((($topic) ? 'Reactie' : 'Onderwerp') . ' aangepast.');
		cancel($topic);
	}
	else
	{
		$mdb->forum->insert($forum_post);

		$alert->success((($topic) ? 'Reactie' : 'Onderwerp') . ' toegevoegd.');
		cancel($topic);
	}
}

if ($del)
{
	$h1 = ($forum_post['parent_id']) ? 'Reactie' : 'Forum onderwerp ' . aphp('forum', 't=' . $forum_post['id'], $forum_post['subject']);
	$h1 .= ' verwijderen?';

	$t = ($forum_post['parent_id']) ?: $forum_post['_id'];

	require_once $rootpath . 'includes/inc_header.php';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<p>' . $forum_post['content'] . '</p>';

	echo '<form method="post">';

	echo aphp('forum', 't=' . $t, 'Annuleren', 'btn btn-default') . '&nbsp;';
	echo '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger">';
	generate_form_token();

	echo '</form>';

	echo '</div>';
	echo '</div>';
	require_once $rootpath . 'includes/inc_footer.php';
	exit;
}

if ($add || $edit)
{
	$includejs = '<script src="' . $cdn_ckeditor . '"></script>
		<script src="' . $rootpath . 'js/forum.js"></script>';

	if ($topic)
	{
		$topic_id = new MongoId($topic);
		$topic_post = $mdb->forum->findOne(array('_id' => $topic_id));

		if ($topic_post['access'] < $access_level)
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
			$forum_post['access'] = 0;
		}

		if ($s_user)
		{
			unset($access_options[0]);
			$forum_post['access'] = ($forum_post['access']) ?: 1;
		}

		echo '<div class="form-group">';
		echo '<label for="access" class="col-sm-2 control-label">Zichtbaarheid</label>';
		echo '<div class="col-sm-10">';
		echo '<select type="file" class="form-control" id="access" name="access" ';
		echo 'required>';
		render_select_options($access_options, $forum_post['access']);
		echo '</select>';
		echo '</div>';
		echo '</div>';
	}

	$str = ($topic) ? 'Reactie' : 'Onderwerp';
	$btn = ($edit) ? 'primary' : 'success';
	$action = ($edit) ? 'aanpassen' : 'toevoegen';
	$cancel_dest = ($topic) ? 't=' . $topic : '';

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
	$topic_id = new MongoId($topic);
	$topic_post = $mdb->forum->findOne(array('_id' => $topic_id));

	if ($topic_post['access'] < $access_level)
	{
		$alert->error('Je hebt geen toegang tot dit forum onderwerp.');
		cancel();
	}

	$find = array('$or'=> array(array('parent_id' => $topic), array('_id' => $topic_id)));

	$forum_posts = $mdb->forum->find($find);
	$forum_posts->sort(array('ts' => (($topic) ? 1 : -1)));

	$forum_posts = iterator_to_array($forum_posts);

	$s_owner = ($s_id && $forum_posts[$topic]['uid'] == $s_id) ? true : false;

	$find = array(
		'parent_id' => array('$exists' => false),
		'access'	=> array('$gte'	=> (string) $access_level),
		'ts' 		=> array('$lt' => $topic_post['ts']),
	);

	$prev = $mdb->forum->findOne($find);

	$prev = ($prev) ? $prev['_id'] : false;

	$find = array(
		'parent_id' => array('$exists' => false),
		'access' 	=> array('$gte'	=> (string) $access_level),
		'ts' 		=> array('$gt' => $topic_post['ts']),
	);

	$next = $mdb->forum->findOne($find);

	$next = ($next) ? $next['_id'] : false;

	if ($s_admin || $s_owner)
	{
		$top_buttons .= aphp('forum', 'del=' . $topic, 'Onderwerp verwijderen', 'btn btn-danger', 'Onderwerp verwijderen', 'times', true);
	}

	if ($prev)
	{
		$top_buttons .= aphp('forum', 't=' . $prev, 'Vorige', 'btn btn-default', 'Vorige', 'chevron-down', true);
	}

	if ($next)
	{
		$top_buttons .= aphp('forum', 't=' . $next, 'Volgende', 'btn btn-default', 'Volgende', 'chevron-up', true);
	}


	$top_buttons .= aphp('forum', '', 'Forum onderwerpen', 'btn btn-default', 'Forum onderwerpen', 'comments', true);

	$includejs = '<script src="' . $cdn_ckeditor . '"></script>
		<script src="' . $rootpath . 'js/forum.js"></script>';

	$h1 = $forum_posts[$topic]['subject'];

	require_once $rootpath . 'includes/inc_header.php';

	foreach ($forum_posts as $p)
	{
		$s_owner = (($p['uid'] == $s_id) && $s_id) ? true : false;

		echo '<div class="panel panel-default printview">';

		echo '<div class="panel-body">';
		echo $p['content'];
		link_user($forum_post['uid']);
		echo '</div>';

		echo '<div class="panel-footer">';
		echo '<p>' . link_user((int) $p['uid']) . ' @' . $p['ts'];
		echo ($p['edit_count']) ? ' Aangepast: ' . $p['edit_count'] : '';

		if ($s_admin || $s_owner)
		{
			echo '<span class="inline-buttons pull-right">';
			echo aphp('forum', 'edit=' . $p['_id'], 'Aanpassen', 'btn btn-primary btn-xs', false, 'pencil');
			echo aphp('forum', 'del=' . $p['_id'], 'Verwijderen', 'btn btn-danger btn-xs', false, 'times');
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
		echo $forum_post['content'];
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

$find = array('parent_id' => array('$exists' => false));

$forum_posts = $mdb->forum->find($find);
$forum_posts->sort(array('ts' => (($topic) ? 1 : -1)));

$forum_posts = iterator_to_array($forum_posts);

$s_owner = ($s_id && $forum_posts[$topic]['uid'] == $s_id) ? true : false;

if ($s_admin || $s_user)
{
	$top_buttons .= aphp('forum', 'add=1', 'Onderwerp Toevoegen', 'btn btn-success', 'Onderwerp toevoegen', 'plus', true);
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
	if ($p['access'] < $access_level)
	{
		continue;
	}

	$s_owner = ($s_id == $p['uid']) ? true : false;

	echo '<tr>';

	echo '<td>';
	echo aphp('forum', 't=' . $p['_id'], $p['subject']);
	echo '</td>';
	echo '<td>' . link_user($p['uid']) . '</td>';
	echo '<td data-value="' . strtotime($p['ts']) . '">' . $p['ts'] . '</td>';

	if (!$s_guest)
	{
		$access = $acc_ary[$p['access']];
		echo '<td><span class="label label-' . $access[1] . '">' . $access[0] . '</span></td>';
	}

	if ($s_admin)
	{
		echo '<td>';
		echo aphp('forum', 'edit=' . $p['_id'], 'Aanpassen', 'btn btn-primary btn-xs', false, 'pencil');
		echo '&nbsp;';
		echo aphp('forum', 'del=' . $p['_id'], 'Verwijderen', 'btn btn-danger btn-xs', false, 'times');
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
	$topic = ($topic) ? 't=' . $topic : '';
	header('Location: ' . generate_url('forum', $topic));
	exit;
}
