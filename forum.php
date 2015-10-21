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

	$post = $elas_mongo->forum->findOne(array('_id' => new MongoId($t)));

	if (!$post)
	{
		$alert->error('Post niet gevonden.');
		cancel();
	}

	$s_owner = ($post['uid'] == $s_id) ? true : false;

	if (!($s_admin || $s_owner))
	{
		$str = ($post['parent_id']) ? 'deze reactie' : 'dit onderwerp';

		if ($del)
		{
			$alert->error('Je hebt onvoldoende rechten om ' . $str . ' te verwijderen.');
		}
		else
		{
			$alert->error('Je hebt onvoldoende rechten om ' . $str . ' aan te passen.');
		}

		cancel(($post['parent_id']) ?: $t);
	}

	$topic = ($post['parent_id']) ?: false;
}

if ($submit)
{
	if ($del)
	{
		$elas_mongo->forum->remove(
			array('_id' => new MongoId($del)),
			array('justOne'	=> true)
		);

		if (!$post['parent_id'])
		{
			$elas_mongo->forum->remove(
				array('parent_id' => $del)
			);

			$alert->success('Het forum onderwerp is verwijderd.');
			cancel();
		}

		$alert->success('De reactie is verwijderd.');
		cancel($post['parent_id']);
	}

	$content = trim(preg_replace('/(<br>)+$/', '', $_POST['content']));

	$content = str_replace(array("\n", "\r"), '', $content);

	while ($content != ($c = chop($content, '<p>&nbsp;</p>')))
	{
		$content = $c;
	}

	$post = array(
		'content'	=> $content,
	);

	if ($topic)
	{
		$post['parent_id'] = $topic;
	}
	else
	{
		$post['subject'] = $_POST['subject'];
		$post['access']	= $_POST['access'];
	}

	if ($edit)
	{
		$post['modified'] = gmdate('Y-m-d H:i:s');
	}
	else
	{
		$post['ts'] = gmdate('Y-m-d H:i:s');
		$post['uid'] = $s_id;
//		$post['edit_count'] = 0;
	}

    $errors = array();

 	if (!($post['subject'] || $topic))
	{
		 $errors[] = 'Vul een onderwerp in.';
	}

 	if (strlen($post['content']) < 2)
	{
		 $errors[] = 'De inhoud van je bericht is te kort.';
	}

	if (!$topic && ($post['access'] < $access_level || $post['access'] > 2))
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
			array('$set'	=> $post, '$inc' => array('edit_count' => 1)),
			array('upsert'	=> true));

		$alert->success((($topic) ? 'Reactie' : 'Onderwerp') . ' aangepast.');
		cancel($topic);
	}
	else
	{
		$elas_mongo->forum->insert($post);

		$alert->success((($topic) ? 'Reactie' : 'Onderwerp') . ' toegevoegd.');
		cancel($topic);
	}
}

if ($del)
{
	$a = '<a href="forum.php?t=' . $post['_id'] . '">' . $post['subject'] . '</a>';
	$h1 = ($post['parent_id']) ? 'Reactie' : 'Forum onderwerp ' . $a;
	$h1 .= ' verwijderen?';

	$t = ($post['parent_id']) ?: $post['_id'];

	require_once $rootpath . 'includes/inc_header.php';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<p>' . $post['content'] . '</p>';

	echo '<form method="post">';
	echo '<a href="' . $rootpath . 'forum.php?t=' . $t . '" class="btn btn-default">Annuleren</a>&nbsp;';
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

	$posts = $elas_mongo->forum->find($find);
	$posts->sort(array('ts' => (($topic) ? 1 : -1)));

	$posts = iterator_to_array($posts);

	$s_owner = ($posts && is_array($posts[0]) && $posts[0]['iud'] == $s_id) ? true : false;

	if ($s_admin || $s_user)
	{
		$str = ($topic) ? 'Reactie' : 'Onderwerp';

		$top_buttons .= '<a href="#add" class="btn btn-success"';
		$top_buttons .= ' title="' . $str . ' toevoegen"><i class="fa fa-plus"></i>';
		$top_buttons .= '<span class="hidden-xs hidden-sm"> ' . $str . ' Toevoegen</span></a>';
	}

	if (($s_admin || $s_owner) && $topic)
	{
		$top_buttons .= '<a href="' . $rootpath . 'forum.php?del=' . $topic . '" class="btn btn-danger"';
		$top_buttons .= ' title="Onderwerp verwijderen"><i class="fa fa-times"></i>';
		$top_buttons .= '<span class="hidden-xs hidden-sm"> Onderwerp verwijderen</span></a>';
	}

	if ($topic)
	{
		$top_buttons .= '<a href="' . $rootpath . 'forum.php" class="btn btn-default"';
		$top_buttons .= ' title="Forum onderwerpen"><i class="fa fa-comments-o"></i>';
		$top_buttons .= '<span class="hidden-xs hidden-sm"> Forum onderwerpen</span></a>';
	}

	$h1 = ($topic) ? $posts[$topic]['subject'] : 'Forum';
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
		foreach ($posts as $p)
		{
			$s_owner = (($p['uid'] == $s_id) && $s_id) ? true : false;

			echo '<div class="panel panel-default">';

			echo '<div class="panel-body">';
			echo $p['content'];
			link_user($post['uid']);
			echo '</div>';

			echo '<div class="panel-footer">';
			echo '<p>' . link_user((int) $p['uid']) . ' @' . $p['ts'];
			echo ($p['edit_count']) ? ' Aangepast: ' . $p['edit_count'] : '';

			if ($s_admin || $s_owner)
			{
				echo '<span class="inline-buttons pull-right">';
				echo '<a href="' . $rootpath . 'forum.php?edit=' . $p['_id'] . '" ';
				echo 'class="btn btn-primary btn-xs">Aanpassen</a>';

				echo '<a href="' . $rootpath . 'forum.php?del=' . $p['_id'] . '" ';
				echo 'class="btn btn-danger btn-xs">Verwijderen</a>';
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

		foreach($posts as $p)
		{
			$s_owner = ($s_id == $p['uid']) ? true : false;

			echo '<tr>';

			echo '<td>';
			echo '<a href="' . $rootpath . 'forum.php?t=' . $p['_id'] . '">';
			echo $p['subject'];
			echo '</a>';
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
				echo '<td><a href="'. $rootpath . 'forum.php?del=' . $p['_id'] . '" class="btn btn-danger btn-xs">';
				echo '<i class="fa fa-times"></i> Verwijderen</a></td>';
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
		echo 'value="' . $post['subject'] . '" required>';
		echo '</div>';
		echo '</div>';
	}

	echo '<div class="form-group">';
	echo '<div class="col-sm-12">';
	echo '<textarea name="content" class="form-control" id="content" rows="4" required>';
	echo $post['content'];
	echo '</textarea>';
	echo '</div>';
	echo '</div>';

	if (!$topic)
	{
		if (!$edit)
		{
			$post['access'] = 0;
		}

		if ($s_user)
		{
			unset($access_options[0]);
			$post['access'] = ($post['access']) ?: 1;
		}

		echo '<div class="form-group">';
		echo '<label for="access" class="col-sm-2 control-label">Zichtbaarheid</label>';
		echo '<div class="col-sm-10">';
		echo '<select type="file" class="form-control" id="access" name="access" ';
		echo 'required>';
		render_select_options($access_options, $post['access']);
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
	$topic = ($topic) ? '?t=' . $topic : '';
	header('Location: ' . $rootpath . 'forum.php' . $topic);
	exit;
}
