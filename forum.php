<?php

$rootpath = './';
$role = 'guest';
require_once $rootpath . 'includes/inc_default.php';

$fa = 'comments-o';

$elas_mongo->connect();

$topic = $_GET['t'];
$del = ($_GET['del']) ?: false;
$edit = ($_GET['edit']) ?: false;

$submit = ($_POST['zend']) ? true : false;

if (!readconfigfromdb('forum_en'))
{
	$alert->error('De forum pagina is niet ingeschakeld.');
	redirect_index();
}

if ($del || $edit)
{
	$t = ($del) ? $del : $edit;

	$forum_post = $elas_mongo->forum->findOne(array('_id' => new MongoId($t)));

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
		$elas_mongo->forum->remove(
			array('_id' => new MongoId($del)),
			array('justOne'	=> true)
		);

		if (!$forum_post['parent_id'])
		{
			$elas_mongo->forum->remove(
				array('parent_id' => $del)
			);

			$alert->success('Het forum onderwerp is verwijderd.');
			cancel();
		}

		$alert->success('De reactie is verwijderd.');
		cancel($forum_post['parent_id']);
	}

	$content = trim(preg_replace('/(<br>)+$/', '', $_POST['content']));

	$content = str_replace(array("\n", "\r"), '', $content);

	while ($content != ($c = chop($content, '<p>&nbsp;</p>')))
	{
		$content = $c;
	}

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
//		$forum_post['edit_count'] = 0;
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

	if (count($errors))
	{
		$alert->error(implode('<br>', $errors));
	}
	else if ($edit)
	{
		$elas_mongo->forum->update(array('_id' => new MongoId($edit)),
			array('$set'	=> $forum_post, '$inc' => array('edit_count' => 1)),
			array('upsert'	=> true));

		$alert->success((($topic) ? 'Reactie' : 'Onderwerp') . ' aangepast.');
		cancel($topic);
	}
	else
	{
		$elas_mongo->forum->insert($forum_post);

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
	echo '</form>';

	echo '</div>';
	echo '</div>';
	require_once $rootpath . 'includes/inc_footer.php';
	exit;
}

if (!$edit)
{
	if ($topic)
	{
		$find = array('$or'=> array(array('parent_id' => $topic), array('_id' => new MongoId($topic))));
	}
	else
	{
		$find = array('parent_id' => array('$exists' => false));
	}

	$forum_posts = $elas_mongo->forum->find($find);
	$forum_posts->sort(array('ts' => (($topic) ? 1 : -1)));

	$forum_posts = iterator_to_array($forum_posts);

	$s_owner = ($forum_posts && is_array($forum_posts[0]) && $forum_posts[0]['iud'] == $s_id) ? true : false;

	if ($s_admin || $s_user)
	{
		$str = ($topic) ? 'Reactie' : 'Onderwerp';

		$top_buttons .= '<a href="#add" class="btn btn-success"';
		$top_buttons .= ' title="' . $str . ' toevoegen"><i class="fa fa-plus"></i>';
		$top_buttons .= '<span class="hidden-xs hidden-sm"> ' . $str . ' Toevoegen</span></a>';
	}

	if (($s_admin || $s_owner) && $topic)
	{
		$top_buttons .= aphp('forum', 'del=' . $topic, 'Onderwerp verwijderen', 'btn btn-danger', 'Onderwerp verwijderen', 'times', true);
	}

	if ($topic)
	{
		$top_buttons .= aphp('forum', '', 'Forum onderwerpen', 'btn btn-default', 'Forum onderwerpen', 'comments', true);
	}

	$h1 = ($topic) ? $forum_posts[$topic]['subject'] : 'Forum';
}
else
{
	$h1 = ($topic) ? 'Reactie' : 'Onderwerp';
	$h1 .= ' aanpassen';
}

$includejs = '<script src="' . $cdn_ckeditor . '"></script>
	<script src="' . $rootpath . 'js/forum.js"></script>';

require_once $rootpath . 'includes/inc_header.php';

if (!$edit)
{
	if ($topic)
	{
		foreach ($forum_posts as $p)
		{
			$s_owner = (($p['uid'] == $s_id) && $s_id) ? true : false;

			echo '<div class="panel panel-default">';

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
	}
	else
	{
		echo '<div class="panel panel-info">';
		echo '<div class="panel-heading">';

		echo '<form method="get">';
		echo '<div class="row">';
		echo '<div class="col-xs-12">';
		echo '<div class="input-group">';
		echo '<span class="input-group-addon">';
		echo '<i class="fa fa-search"></i>';
		echo '</span>';
		echo '<input type="text" class="form-control" id="q" name="q" value="' . $q . '">';
		echo '</div>';
		echo '</div>';
		echo '</div>';
		echo '</form>';

		echo '</div>';
		echo '</div>';

		echo '<div class="panel panel-default">';

		echo '<div class="table-responsive">';
		echo '<table class="table table-bordered table-striped table-hover footable"';
		echo ' data-filter="#q" data-filter-minimum="1">';
		echo '<thead>';

		echo '<tr>';
		echo '<th>Onderwerp</th>';
		echo '<th data-hide="phone, tablet">Gebruiker</th>';
		echo '<th data-hide="phone, tablet" data-sort-initial="descending" ';
		echo 'data-type="numeric">Tijdstip</th>';
		echo ($s_guest) ? '' : '<th data-hide="phone, tablet">Zichtbaarheid</th>';
		echo ($s_admin) ? '<th data-hide="phone,tablet">Verwijderen</th>' : '';
		echo '</tr>';

		echo '</thead>';
		echo '<tbody>';

		foreach($forum_posts as $p)
		{
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
				echo aphp('forum', 'del=' . $p['_id'], 'Verwijderen', 'btn btn-danger btn-xs', false, 'times');
				echo '</td>';
			}
			echo '</tr>';

		}
		echo '</tbody>';
		echo '</table>';
		echo '</div>';
		echo '</div>';

	}
}

if (!$s_guest)
{
	$hh = 'Reactie' . (($edit) ? ' aanpassen' : '');

	if (!$edit)
	{
		if ($topic)
		{
			echo '<h3>Reactie</h3>';
		}
		else
		{
			echo '<h3>Nieuw onderwerp</h3>';
		}
	}

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

	echo '<input type="submit" name="zend" value="' . $str . ' ' . $action . '" class="btn btn-' . $btn . '">';

	echo '</form>';

	echo '</div>';
	echo '</div>';
}

include $rootpath . 'includes/inc_footer.php';

function cancel($topic = null)
{
	$topic = ($topic) ? 't=' . $topic : '';
	header('Location: ' . generate_url('forum', $topic));
	exit;
}
