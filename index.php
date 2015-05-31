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
$rootpath = "./";
$role = 'guest';
require_once $rootpath . 'includes/inc_default.php';
require_once $rootpath . 'includes/inc_adoconnection.php';

$news = $db->GetArray('select * from news where approved = True order by cdate desc');

$newusertreshold = gmdate('Y-m-d H:i:s', time() - readconfigfromdb('newuserdays') * 86400);

$newusers = $db->GetArray('select id, letscode, fullname
	from users
	where status = 1
		and adate > \'' . $newusertreshold . '\'');

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
	echo '<div class="panel panel-default">';
	echo '<div class="panel-heading">Nieuws</div>';

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
	echo '<div class="panel panel-default">';
	echo '<div class="panel-heading">Nieuwe leden</div>';

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

$messagerows = get_all_msgs();
	if($messagerows){
			show_all_msgs($messagerows);
}

include $rootpath . 'includes/inc_footer.php';

//////////////////////////////////////////

function schema_check()
{
        //echo $version;
    global $db;
	$query = "SELECT * FROM parameters WHERE parameter= 'schemaversion'";
    $result = $db->GetRow($query) ;
	return $result["value"];
}

function show_all_newusers($newusers){

	echo "<div class='border_b'>";
	echo "<table class='data' cellpadding='0' cellspacing='0' border='1' width='99%'>";
	echo "<tr class='header'>";
	echo "<td colspan='3'><strong>Instappers</strong></td>";
	echo "</tr>";
	$rownumb=0;
	foreach($newusers as $value){
		$rownumb=$rownumb+1;
		if($rownumb % 2 == 1){
			echo "<tr class='uneven_row'>";
		}else{
	        	echo "<tr class='even_row'>";
		}

		echo "<td valign='top'>";
		echo trim($value["letscode"]);
		echo " </td><td valign='top'>";
		echo "<a href='memberlist_view.php?id=".$value["id"]."'>".htmlspecialchars($value["name"],ENT_QUOTES)."</a>";
		echo "</td>";
		echo "<td valign='top'>";
		echo $value["postcode"];
		echo " </td>";
		echo "</tr>";

	}
	echo "</table></div>";
}

function show_all_birthdays($birthdays){
	echo "<div class='border_b'>";
	echo "<table class='data' cellpadding='0' cellspacing='0' border='1' width='99%'>";
	echo "<tr class='header'>";
	echo "<td colspan='2'><strong>Verjaardagen deze maand</strong></td>";
	echo "</tr>";
	$rownumb=0;
	foreach($birthdays as $value){
	$rownumb=$rownumb+1;
	if($rownumb % 2 == 1){
			echo "<tr class='uneven_row'>";
	}else{
			echo "<tr class='even_row'>";
	}

	echo "<td valign='top' width='15%'>";
	echo $value['birthday'];
	echo " </td>";

	echo "<td valign='top'>";
	echo "<a href='memberlist_view.php?id=".$value["id"]."'>".htmlspecialchars($value["name"],ENT_QUOTES)."</a>";
	echo " </td>";

	echo "</tr>";

	}
	echo "</table></div>";
}

function get_all_newusers()
{
	global $db;
	$query = "SELECT * FROM users WHERE status = 3 ORDER by letscode ";
	$newusers = $db->GetArray($query);
	return $newusers;
}

function show_all_newsitems($newsitems){
	echo "<table class='data' cellpadding='0' cellspacing='0' border='1' width='99%'>";
	echo "<tr class='header'>";
	echo "<td colspan='2'><strong>Nieuws</strong></td>";
	echo "</tr>";
	$rownumb=0;
	foreach($newsitems as $value){
	$rownumb=$rownumb+1;
		if($rownumb % 2 == 1){
			echo "<tr class='uneven_row'>";
		}else{
	        	echo "<tr class='even_row'>";
		}

		echo "<td valign='top' width='15%'>";
		if(trim($value["idate"]) != "00/00/00"){
			list($date) = explode(' ', $value['idate']); 
			echo $date;
		}
		echo " </td>";
		echo "<td valign='top'>";
		echo " <a href='news/view.php?id=".$value["nid"]."'>";
		echo htmlspecialchars($value["headline"],ENT_QUOTES);
		echo "</a>";
		echo "</td></tr>";
	}

	echo "</table>";
}

function chop_string($content, $maxsize){
$strlength = strlen($content);
    //geef substr van kar 0 tot aan 1ste spatie na 30ste kar
    //dit moet enkel indien de lengte van de string groter is dan 30
    if ($strlength >= $maxsize){
        $spacechar = strpos($content," ", 60);
        if($spacechar == 0){
            return $content;
        }else{
            return substr($content,0,$spacechar);
        }
    }else{
        return $content;
    }
}

function show_all_msgs($messagerows){

	global $rootpath;

	echo "<table class='data' cellpadding='0' cellspacing='0' border='1' width='99%'>";
	echo "<tr class='header'>";
	echo "<td colspan='3'><strong>Laatste nieuwe Vraag & Aanbod</strong></td>";
	echo "</tr>";
	$rownumb=0;
	foreach($messagerows as $key => $value){
		$rownumb=$rownumb+1;
		if($rownumb % 2 == 1){
			echo "<tr class='uneven_row'>";
		}else{
	        	echo "<tr class='even_row'>";
		}
		echo "<td valign='top'>";
		if($value["msg_type"]==0){
			echo "V";
		}elseif ($value["msg_type"]==1){
			echo "A";
		}
		echo "</td>";
		echo "<td valign='top'>";
		echo "<a href='messages/view.php?id=".$value["msgid"]."'>";
		if(strtotime($value["valdate"]) < time()) {
                        echo "<del>";
                }
		$content = htmlspecialchars($value["content"],ENT_QUOTES);
		echo chop_string($content, 60);
		if(strlen($content)>60){
			echo "...";
		}
		if(strtotime($value["valdate"]) < time()) {
                        echo "</del>";
                }
		echo "</a>";
		echo "</td><td valign='top'>";
		echo '<a href="' . $rootpath . 'memberlist_view.php?id=' . $value['uid'] . '">';
		echo htmlspecialchars($value["username"],ENT_QUOTES)." (".trim($value["letscode"]).")";
		echo "</a></td>";
		echo "</tr>"; 
	}
	echo "</table>";
}

function get_all_newsitems(){
	global $db;
	$query = 'SELECT n.headline, 
			n.id AS nid,
			n.cdate AS date,
			n.itemdate AS idate
		FROM news n
		WHERE n.approved = True
		ORDER BY n.itemdate DESC
		LIMIT 50';

	return $db->GetArray($query);
}

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");

}

function get_all_msgs(){
	global $db;
	$query = 'SELECT m.id AS msgid,
			m.validity AS valdate,
			m.content,
			m.msg_type,
			u.id AS uid,
			u.name AS username,
			u.letscode,
			m.cdate AS date
		FROM messages m, users u
		WHERE m.id_user = u.id
			AND (u.status = 1 OR u.status = 2 OR u.status = 3)
		ORDER BY m.cdate DESC
		LIMIT 100';
	return $db->GetArray($query);
}

