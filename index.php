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

$rootpath = './';
$role = 'guest';
require_once $rootpath . 'includes/inc_default.php';

$news_where = ($s_admin) ? '' : ' where approved = True ';
$news = $db->fetchAll('select * from news ' . $news_where . ' order by cdate desc');

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

if ($s_admin)
{
	$dup_letscode = $db->fetchColumn('select u1.letscode
		from users u1, users u2
		where u1.letscode = u2.letscode
			and u1.id <> u2.id');

	$dup_mail = $db->fetchColumn('select c1.value
		from contact c1, contact c2, type_contact tc
		where c1.id_type_contact = tc.id
			and c2.id_type_contact = tc.id
			and tc.abbrev = \'mail\'
			and c1.id <> c2.id
			and c1.value = c2.value');

	$dup_name = $db->fetchColumn('select u1.name
		from users u1, users u2
		where u1.name = u2.name
			and u1.id <> u2.id');

	$emp_letscode = $db->fetchColumn('select id
		from users
		where letscode = \'\'');

	$emp_name = $db->fetchColumn('select id
		from users
		where letscode = \'\'');

	$emp_mail = $db->fetchColumn('select c.id_user
		from contact c, type_contact tc
		where c.id_type_contact = tc.id
			and tc.abbrev = \'mail\'
			and c.value = \'\'');

	$version = $db->fetchColumn('select value from parameters where parameter = \'schemaversion\'');
	$db_update = ($version == $schemaversion) ? false : true;
	$default_config = $db->fetchColumn('select setting from config where "default" = True');
}

$h1 = 'Overzicht';
$fa = 'home';

include $rootpath . 'includes/inc_header.php';

if($s_admin)
{
	if ($db_update || $default_config || $dup_letscode || $dup_name || $dup_mail
		|| $emp_mail || $emp_name || $emp_letscode)
	{
		echo '<div class="panel panel-danger">';
		echo '<div class="panel-heading">';
		echo '<span class="label label-info">Admin</span> ';
		echo '<i class="fa fa-exclamation-triangle"></i> Status';
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
			echo 'Kijk in de ' . aphp('config', '', 'instellingen') . ' ';
			echo 'om ze te wijzigen of bevestigen';
			echo '</li>';
		}
		if ($dup_mail)
		{
			echo '<li class="list-group-item">';
			echo 'Er is een duplicaat mail adres onder de gebruikers: ' . $dup_mail;
			echo '</li>';
		}
		if ($dup_letscode)
		{
			echo '<li class="list-group-item">';
			echo 'Er is een duplicate letscode onder de gebruikers: ' . $dup_letscode;
			echo '</li>';
		}
		if ($dup_name)
		{
			echo '<li class="list-group-item">';
			echo 'Er is een duplicate gebruikersnaam onder de gebruikers: ' . $dup_name;
			echo '</li>';
		}
		if ($emp_mail)
		{
			echo '<li class="list-group-item">';
			echo 'Er is een duplicaat mailadres onder de gebruikers: ' . link_user($emp_mail);
			echo '</li>';
		}
		if ($emp_letscode)
		{
			echo '<li class="list-group-item">';
			echo 'Er is een duplicate letscode onder de gebruikers: ' . link_user($emp_letscode);
			echo '</li>';
		}
		if ($emp_name)
		{
			echo '<li class="list-group-item">';
			echo 'Er is een duplicate gebruikersnaam onder de gebruikers: ' . link_user($emp_name);
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
	echo 'raadplegen maar niets wijzigen. Transacties moet je ingeven in de installatie van je eigen groep.';
	echo '</div>';
	echo '</div>';
}

if($news)
{
	echo '<h3 class="printview">';
	echo aphp('news', '', 'Nieuws', false, false, 'calendar');
	echo '</h3>';

	echo '<div class="panel panel-warning printview">';

	echo '<div class="table-responsive">';
	echo '<table class="table table-striped table-hover table-bordered">';

	echo '<tbody>';
	foreach ($news as $value)
	{
		echo '<tr>';

		echo '<td>';
		echo aphp('news', 'id=' . $value['id'], $value['headline']);
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
	echo '<h3 class="printview">';
	echo aphp('users', 'status=new', 'Nieuwe leden', false, false, 'users');
	echo '</h3>';

	echo '<div class="panel panel-success printview">';

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
	echo '<h3 class="printview">';
	echo aphp('messages', '', 'Recent vraag en aanbod', false, false, 'newspaper-o');
	echo '</h3>';

	echo '<div class="panel panel-info printview">';

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
		echo aphp('messages', 'id=' . $msg['id'], $msg['content']);
		echo '</td>';

		echo '<td>';
		echo $msg['validity'];
		echo '</td>';

		echo '<td>';
		echo link_user($msg['id_user']);
		echo '</td>';

		echo '<td>';
		echo aphp('messages', 'cid=' . $msg['cid'], $msg['cat']);
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
