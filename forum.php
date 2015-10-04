<?php
ob_start();
$rootpath = './';
$role = 'guest';
require_once $rootpath . 'includes/inc_default.php';

$fa = 'comments-o';

$elas_mongo->connect();

$topic = $_GET['t'];
$del = ($_GET['del']) ?: false;
$edit = ($_GET['edit']) ?: false;

$submit = ($_POST['zend']) ? true : false;

if ($del || $edit)
{
	$t = ($del) ? $del : $edit;

	$post = $elas_mongo->forum_posts->findOne(array('_id' => new MongoId($t)));

	if (!$post)
	{
		$alert->error('Post niet gevonden.');
		cancel();
	}

	$s_owner = ($post['uid'] == $s_id) ? true : false;

	if (!($s_admin || $s_owner))
	{
		$str = ($post['id_parent']) ? 'deze reactie' : 'dit onderwerp';

		if ($del)
		{
			$alert->error('Je hebt onvoldoende rechten om ' . $str . ' te verwijderen.');
		}
		else
		{
			$alert->error('Je hebt onvoldoende rechten om ' . $str . ' aan te passen.');
		}

		cancel(($post['id_parent']) ?: $t);
	} 
}

if ($submit)
{
	if ($del)
	{
		$elas_mongo->forum_posts->remove(
			array('_id' => new MongoId($del)),
			array('justOne'	=> true)
		);

		if (!$post['id_parent'])
		{
			$elas_mongo->forum_posts->remove(
				array('id_parent' => $del)
			);

			$alert->success('Het forum onderwerp is verwijderd.');
			cancel();
		}

		$alert->success('De reactie is verwijderd.');
		cancel($post['id_parent']);
	}
}

if ($del)
{
	$a = '<a href="forum.php?t=' . $post['_id'] . '">' . $post['subject'] . '</a>';
	$h1 = ($post['id_parent']) ? 'Reactie' : 'Forum onderwerp ' . $a;
	$h1 .= ' verwijderen?';

	$t = ($post['id_parent']) ?: $post['_id'];

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

if($submit)
{

	$post = array(
		'content'	=> $_POST['content'],
	);

	if ($topic)
	{
		$post['id_parent'] = $topic;
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
		$elas_mongo->forum_posts->update(array('_id' => new MongoId($edit)), $post);

		$alert->success((($topic) ? 'Reactie' : 'Onderwerp') . ' aangepast.');
		cancel($topic);
	}
	else
	{
		$elas_mongo->forum_posts->insert($post);

		$alert->success((($topic) ? 'Reactie' : 'Onderwerp') . ' toegevoegd.');
		cancel($topic);
	}
}

if ($topic)
{
	$find = array('$or'=> array(array('id_parent' => $topic), array('_id' => new MongoId($topic))));
}
else
{
	$find = array('id_parent' => array('$exists' => false));
}

if (!$edit)
{
	$posts = $elas_mongo->forum_posts->find($find);
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

	$includejs = '<script src="' . $cdn_ckeditor . '"></script>
		<script src="' . $rootpath . 'js/forum.js"></script>';

	require_once $rootpath . 'includes/inc_header.php';

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
	}
}

if (!$s_guest)
{
	echo '<div class="panel panel-info" id="add">';
	echo '<div class="panel-heading">';

	echo '<form method="post" class="form-horizontal">';

	if ($topic)
	{
		echo '<h4>Reactie';
		echo ($edit) ? ' aanpassen' : '';
		echo '</h4>';
	}
	else
	{
		echo '<h4>';
		echo ($edit) ? 'Onderwerp aanpassen' : 'Nieuw onderwerp';
		echo '</h4>';
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
		$acc_select = 0;

		if ($s_user)
		{
			unset($access_options[0]);
			$acc_select = 1;
		}

		echo '<div class="form-group">';
		echo '<label for="access" class="col-sm-2 control-label">Zichtbaarheid</label>';
		echo '<div class="col-sm-10">';
		echo '<select type="file" class="form-control" id="access" name="access" ';
		echo 'required>';
		render_select_options($access_options, $acc_select);
		echo '</select>';
		echo '</div>';
		echo '</div>';
	}

	echo '<input type="submit" name="zend" value="Verzenden" class="btn btn-default">';

	echo '</form>';

	echo '</div>';
	echo '</div>';
}

include $rootpath . 'includes/inc_footer.php';

function cancel($topic = null)
{
	$tl = ($topic) ? '?t=' . $topic : '';
	header('Location: ' . $rootpath . 'forum.php' . $tl);
	exit;
}
