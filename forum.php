<?php
ob_start();
$rootpath = './';
$role = 'guest';
require_once $rootpath . 'includes/inc_default.php';

$elas_mongo->connect();

$topic = $_GET['t'];

if(isset($_POST['zend']))
{
	$post = array(
		'subject' 	=> $_POST['subject'],
		'content'	=> $_POST['content'],
		'access'	=> $_POST['access'],
		'topic'		=> $topic,
		'ts'		=> gmdate('Y-m-d H:i:s'),
		'uid'		=> $s_id,
		'username'	=> $s_name,
	);

    $errors = array();

 	if (!($post['subject'] || $topic))
	{
		 $errors[] = 'Vul een onderwerp in.';
	}
 	if (strlen($post['content']) < 5)
	{
		 $errors[] = 'De inhoud van je bericht is te kort.';
	}
	if ($post['access'] < $access_level || $post['access'] > 2)
	{
		$errors[] = 'Ongeldige zichtbaarheid';
	}
	if (count($errors))
	{
		$alert->error(implode('<br>', $errors));
	}
	else
	{
		$elas_mongo->forum_posts->insert($post);

		$alert->success((($topic) ? 'Reactie' : 'Onderwerp') . ' toegevoegd.');
		$tl = ($topic) ? '?t=' . $topic : '';
		header('Location: ' . $rootpath . 'forum.php' . $tl);
		exit;
	}
}
else
{

}

$find = array();

if ($topic)
{
	$find['topic'] = $topic;
	$topic = $elas_mongo->forum_posts->findOne(array('_id' => new MongoId($topic)));
}
else
{
	$find['topic'] = null;
}

$posts = $elas_mongo->forum_posts->find($find);

$top_buttons = '<a href="' . $rootpath . 'forum.php" class="btn btn-default"';
$top_buttons .= ' title="Forum onderwerpen"><i class="fa fa-comments-o"></i>';
$top_buttons .= '<span class="hidden-xs hidden-sm"> Forum onderwerpen</span></a>';

$h1 = ($topic) ? $topic['subject'] : 'Forum';
$fa = 'comments-o';

$includejs = '<script src="' . $cdn_ckeditor . '"></script>
	<script src="' . $rootpath . 'js/forum.js"></script>';

require_once $rootpath . 'includes/inc_header.php';

if ($topic)
{
	echo '<div class="panel panel-info">';

	echo '<div class="panel-body">';
	echo $topic['content'];
	echo '</div>';

	foreach ($posts as $post)
	{
		echo '<div class="panel-body">';
		echo $post['content'];
		echo '</div>';
	}

	echo '</div>';
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
	echo '<th data-sort-initial="true">Onderwerp</th>';
	echo '<th data-hide="phone, tablet">Gebruiker</th>';
	echo '<th data-hide="phone, tablet">Tijdstip</th>';
	echo ($s_accountrole == 'guest') ? '' : '<th data-hide="phone, tablet">Toegang</th>';
	echo ($s_accountrole == 'admin') ? '<th data-hide="phone, tablet" data-sort-ignore="true">Verwijderen</th>' : '';
	echo '</tr>';

	echo '</thead>';
	echo '<tbody>';

	foreach($posts as $post)
	{
		echo '<tr>';

		echo '<td>';
		echo '<a href="' . $rootpath . 'forum.php?t=' . $post['_id'] . '">';
		echo $post['subject'];
		echo '</a>';
		echo '</td>';
		echo '<td>' . $post['username'] . '</td>';
		echo '<td>' . $post['ts'] . '</td>';

		if ($s_accountrole != 'guest')
		{
			echo '<td>' . $acc_lang[$val['access']] . '</td>';
		}

		if ($s_accountrole == 'admin')
		{
			echo '<td><a href="'. $rootpath . 'docs.php?del=' . $val['_id'] . '" class="btn btn-danger btn-xs">';
			echo '<i class="fa fa-times"></i> Verwijderen</a></td>';
		}
		echo '</tr>';

	}
	echo '</tbody>';
	echo '</table>';
}

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo '<form method="post" class="form-horizontal">';

if ($topic)
{
	echo '<h4>Reactie</h4>';
}
else
{
	echo '<h4>Nieuw onderwerp</h4>';
	echo '<div class="form-group">';
	echo '<div class="col-sm-12">';
	echo '<input type="text" class="form-control" id="subject" name="subject" ';
	echo 'placeholder="Onderwerp" ';
	echo 'value="' . $post['subject'] . '" required>';
	echo '</div>';
	echo '</div>';
}

echo '<div class="form-group">';
//echo '<label for="content" class="col-sm-2 control-label">Omschrijving</label>';
echo '<div class="col-sm-12">';
echo '<textarea name="content" class="form-control" id="content" rows="4" required>';
echo $post['content'];
echo '</textarea>';
echo '</div>';
echo '</div>';

if (!$topic)
{
	echo '<div class="form-group">';
	echo '<label for="access" class="col-sm-2 control-label">Zichtbaar</label>';
	echo '<div class="col-sm-10">';
	echo '<select type="file" class="form-control" id="access" name="access" ';
	echo 'required>';
	echo '<option value="0" selected="selected">admin</option>';
	echo '<option value="1">leden</option>';
	echo '<option value="2">interlets</option>';
	echo '</select>';
	echo '</div>';
	echo '</div>';
}

echo '<input type="submit" name="zend" value="Verzenden" class="btn btn-default">';

echo '</form>';

echo '</div>';
echo '</div>';

if (!$s_id)
{
	echo '<small><i>Opgelet: je kan vanuit het loginscherm zelf een nieuw paswoord aanvragen met je e-mail adres!</i></small>';
}

include $rootpath . 'includes/inc_footer.php';
