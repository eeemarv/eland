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
require_once $rootpath . 'includes/inc_adoconnection.php';

$news = $db->GetArray('select * from news where approved = True order by cdate desc');

$newusertreshold = gmdate('Y-m-d H:i:s', time() - readconfigfromdb('newuserdays') * 86400);

$newusers = $db->GetArray('select id, letscode, fullname
	from users
	where status = 1
		and adate > \'' . $newusertreshold . '\'');

$msgs = $db->GetArray('SELECT m.*,
		u.id AS uid,
		u.fullname,
		u.letscode,
		c.fullname as cat,
		c.id as cid
	from messages m, users u, categories c
	where m.id_user = u.id
		and u.status in (1, 2)
		and m.id_category = c.id
	order by m.cdate DESC
	limit 100');

//$h1 = 'Home';

include $rootpath . 'includes/inc_header.php';

if($s_accountrole == 'admin')
{
	$version = $db->GetOne('select value from parameters where parameter = \'schemaversion\'');
	$db_update = ($version == $schemaversion) ? false : true;
	$default_config = $db->GetOne('select setting from config where "default" = True');
	$group_internal = $db->GetOne('select id from letsgroups where apimethod = \'internal\'');

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

if($s_accountrole == 'guest')
{
	$systemname = readconfigfromdb('systemname');

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';
	echo 'Welkom bij de eLAS installatie van ' . $systemname;
	echo '</div>';
	echo '<div class="panel-body">';
	echo 'Je bent ingelogd als LETS-gast, je kan informatie ';
	echo 'raadplegen maar niets wijzigen of transacties invoeren.  ';
	echo 'Als guest kan je ook niet rechtstreeks reageren op V/A of andere mails versturen uit eLAS';
	echo '</div>';
	echo '</div>';
}

if($news)
{
	echo '<div class="panel panel-warning">';
	echo '<div class="panel-heading"><i class="fa fa-calendar"></i> ';
	echo '<a href="' . $rootpath . 'news/overview.php">Nieuws</a></div>';

	echo '<div class="table-responsive">';
	echo '<table class="table table-striped table-hover table-bordered footable">';

	echo '<thead>';
	echo '<tr>';
	echo '<th>Titel</th>';
	echo '<th data-hide="phone" data-sort-initial="true">Agendadatum</th>';
	echo ($s_accountrole == 'admin') ? '<th data-hide="phone, tablet">Goedgekeurd</th>' : '';
	echo '</tr>';
	echo '</thead>';

	echo '<tbody>';
	foreach ($news as $value)
	{
		echo '<tr>';

		echo '<td>';
		echo '<a href="' . $rootpath . 'news/view.php?id=' . $value['id'] . '">';
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

		if ($s_accountrole == 'admin')
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
	echo '<div class="panel panel-success">';
	echo '<div class="panel-heading"><i class="fa fa-users"></i> ';
	echo '<a href="' . $rootpath . 'memberlist.php">Nieuwe leden</a></div>';

	echo '<div class="table-responsive">';
	echo '<table class="table table-bordered table-striped table-hover footable"';
	echo ' data-filter="#filter" data-filter-minimum="1">';
	echo '<thead>';

	echo '<tr>';
	echo '<th data-sort-initial="true">Code</th>';
	echo '<th data-filter="#filter">Naam</th>';
	echo '</tr>';

	echo '</thead>';
	echo '<tbody>';

	foreach($newusers as $value)
	{
		$id = $value['id'];

		echo '<tr class="success">';

		echo '<td>';
		echo '<a href="' . $rootpath . 'memberlist_view.php?id=' .$id .'">';
		echo $value['letscode'];
		echo '</a></td>';
		
		echo '<td>';
		echo '<a href="' . $rootpath . 'memberlist_view.php?id=' .$id .'">';
		echo htmlspecialchars($value['fullname'],ENT_QUOTES).'</a>';
		echo '</td>';
		echo '</tr>';

	}
	echo '</tbody>';
	echo '</table>';
	echo '</div>';
	echo '</div>';
}

if($msgs)
{
	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';
	echo '<i class="fa fa-newspaper-o"></i> ';
	echo '<a href="' . $rootpath . 'messages/overview.php">Recent vraag en aanbod</a>';
	echo '</div>';

	echo '<div class="table-responsive">';
	echo '<table class="table table-hover table-striped table-bordered footable"';
	echo ' data-filter="#filter" data-filter-minimum="1">';
	echo '<thead>';
	echo '<tr>';
	echo "<th>V/A</th>";
	echo "<th>Wat</th>";
	echo '<th data-hide="phone, tablet">Geldig tot</th>';
	echo '<th data-hide="phone, tablet">Wie</th>';
	echo '<th data-hide="phone, tablet">Categorie</th>';
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

		echo ($msg["msg_type"]) ? 'A' : 'V';
		echo '</td>';

		echo '<td>';
		echo '<a href="' .$rootpath . 'messages/view.php?id=' . $msg['id']. '">';
		echo htmlspecialchars($msg['content'],ENT_QUOTES);
		echo '</a>';
		echo '</td>';

		echo '<td>';
		echo $msg['validity'];
		echo '</td>';

		echo '<td>';
		echo '<a href="' . $rootpath . 'memberlist_view.php?id=' . $msg['uid'] . '">';
		echo htmlspecialchars($msg['letscode'] . ' ' . $msg['fullname'], ENT_QUOTES);
		echo '</a>';
		echo '</td>';

		echo '<td>';
		echo '<a href="' . $rootpath . 'searchcat_viewcat.php?id=' . $msg['cid'] . '">';
		echo htmlspecialchars($msg['cat'],ENT_QUOTES);
		echo '</a>';
		echo '</td>';

		echo '</tr>';
	}

	echo '</tbody>';
	echo '</table>';
	echo '</div>';
	echo '</div>';
}

include $rootpath . 'includes/inc_footer.php';
