<?php

// Copyright(C) 2009 Guy Van Sanden <guy@vsbnet.be>
//
//    This program is free software: you can redistribute it and/or modify
//    it under the terms of the GNU General Public License as published by
//    the Free Software Foundation, either version 3 of the License, or
//    (at your option) any later version.
//
//    This program is distributed in the hope that it will be useful,
//    but WITHOUT ANY WARRANTY; without even the implied warranty of
//    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//    GNU General Public License for more details.
//
//    You should have received a copy of the GNU General Public License
//    along with this program.  If not, see <http://www.gnu.org/licenses/>.

ob_start();
$rootpath = './';
$role = 'guest';
require_once $rootpath . 'includes/inc_default.php';

$news = $db->fetchAll('select * from news where approved = True order by cdate desc');

$newusertreshold = gmdate('Y-m-d H:i:s', time() - readconfigfromdb('newuserdays') * 86400);

$newusers = $db->fetchAll('select id, letscode, name
	from users
	where status = 1
		and adate > ?', array($newusertreshold));

$msgs = $db->fetchAll('SELECT m.*,
		u.postcode,
		c.fullname as cat,
		c.id as cid
	from messages m, users u, categories c
	where m.id_user = u.id
		and u.status in (1, 2)
		and m.id_category = c.id
	order by m.cdate DESC
	limit 100');

$h1 = 'Overzicht';
$fa = 'home';

include $rootpath . 'includes/inc_header.php';

if($s_admin)
{
	$version = $db->fetchColumn('select value from parameters where parameter = \'schemaversion\'');
	$db_update = ($version == $schemaversion) ? false : true;
	$default_config = $db->fetchColumn('select setting from config where "default" = True');
	$group_internal = $db->fetchColumn('select id from letsgroups where apimethod = \'internal\'');

	if ($db_update || $default_config || !$group_internal)
	{
		echo '<div class="panel panel-danger">';
		echo '<div class="panel-heading">';
		echo 'eLAS status (amdin)';
		echo '</div>';

		echo '<ul class="list-group">';
		if ($db_update)
		{
			echo '<li class="list-group-item">';
			echo 'Een database update is nodig.';
			echo '</li>';
		}
		if ($default_config)
		{
			echo '<li class="list-group-item">';
			echo 'Er zijn nog settings met standaardwaarden, ';
			echo 'klik op <a href="' . $rootpath . 'preferences/config.php">instellingen</a> ';
			echo 'om ze te wijzigen of bevestigen';
			echo '</li>';
		}
		if ($group_internal)
		{
			echo '<li class="list-group-item">';
			echo 'Er bestaat geen LETS groep met type intern voor je eigen groep.  ';
			echo 'Voeg die toe onder <a href="' . $rootpath . 'interlets/overview.php">LETS Groepen</a>.';
			echo '</li>';
		}
		echo '</ul>';
		echo '</div>';
	}
}

if($s_guest)
{
	$systemname = readconfigfromdb('systemname');

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';
	echo 'Welkom bij ' . $systemname;
	echo '</div>';
	echo '<div class="panel-body">';
	echo 'Je bent ingelogd als LETS-gast, je kan informatie ';
	echo 'raadplegen maar niets wijzigen of transacties invoeren. ';
	echo '</div>';
	echo '</div>';
}

if($news)
{
	echo '<h3><i class="fa fa-calendar"></i> ';
	echo '<a href="' . $rootpath . 'news.php">Nieuws</a></h3>';	

	echo '<div class="panel panel-warning">';

	echo '<div class="table-responsive">';
	echo '<table class="table table-striped table-hover table-bordered">';

	echo '<tbody>';
	foreach ($news as $value)
	{
		echo '<tr>';

		echo '<td>';
		echo '<a href="' . $rootpath . 'news.php?id=' . $value['id'] . '">';
		echo htmlspecialchars($value['headline'],ENT_QUOTES);
		echo '</a>';
		echo '</td>';

		echo '<td>';
		if(trim($value['itemdate']) != '00/00/00')
		{
			list($date) = explode(' ', $value['itemdate']);
			echo $date;
		}
		echo '</td>';

		if ($s_admin)
		{
			echo '<td>';
			echo ($value['approved'] == 't') ? 'Ja' : 'Nee';
			echo '</td>';
		}
		echo '</tr>';
	}
	echo '</tbody>';
	echo '</table></div>';
	echo '</div>';
}

if($newusers)
{
	echo '<h3><i class="fa fa-users"></i> ';
	echo '<a href="' . $rootpath . 'users.php">Nieuwe leden</a></h3>';

	echo '<div class="panel panel-success">';

	echo '<div class="table-responsive">';
	echo '<table class="table table-bordered table-striped table-hover">';

	echo '<tbody>';

	foreach($newusers as $u)
	{
		$id = $u['id'];

		echo '<tr class="success">';

		echo '<td>' . link_user($id) . '</td>';

		echo '</tr>';
	}
	echo '</tbody>';
	echo '</table>';
	echo '</div>';
	echo '</div>';
}

if($msgs)
{
	echo '<h3>';
	echo '<i class="fa fa-newspaper-o"></i> ';
	echo '<a href="' . $rootpath . 'messages.php">Recent vraag en aanbod</a>';
	echo '</h3>';

	echo '<div class="panel panel-info">';

	echo '<div class="table-responsive">';
	echo '<table class="table table-hover table-striped table-bordered footable">';
	echo '<thead>';
	echo '<tr>';
	echo '<th>V/A</th>';
	echo '<th>Wat</th>';
	echo '<th data-hide="phone, tablet">Geldig tot</th>';
	echo '<th data-hide="phone, tablet">Wie</th>';
	echo '<th data-hide="phone, tablet">Categorie</th>';
	echo '<th data-hide="phone">Plaats</th>';
	echo '</tr>';
	echo '</thead>';

	echo '<tbody>';

	foreach($msgs as $msg)
	{
		$del = (strtotime($msg['validity']) < time()) ? true : false;

		echo '<tr';
		echo ($del) ? ' class="danger"' : '';
		echo '>';
		echo '<td>';

		echo ($msg['msg_type']) ? 'Aanbod' : 'Vraag';
		echo '</td>';

		echo '<td>';
		echo '<a href="' .$rootpath . 'messages.php?id=' . $msg['id']. '">';
		echo htmlspecialchars($msg['content'],ENT_QUOTES);
		echo '</a>';
		echo '</td>';

		echo '<td>';
		echo $msg['validity'];
		echo '</td>';

		echo '<td>';
		echo link_user($msg['id_user']);
		echo '</td>';

		echo '<td>';
		echo '<a href="' . $rootpath . 'messages.php?cid=' . $msg['cid'] . '">';
		echo htmlspecialchars($msg['cat'],ENT_QUOTES);
		echo '</a>';
		echo '</td>';

		echo '<td>';
		echo $msg['postcode'];
		echo '</td>';

		echo '</tr>';
	}

	echo '</tbody>';
	echo '</table>';
	echo '</div>';
	echo '</div>';
}

include $rootpath . 'includes/inc_footer.php';
