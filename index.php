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
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

include($rootpath."includes/inc_header.php");

if(!isset($s_id)){
	header("Location: ".$rootpath."login.php");
	exit;
}

if($s_accountrole == "admin"){
	echo "<table class='data' width='99%'><tr class='header'><td>eLAS Status (admin)</td></tr>";
	echo "<tr><td>";
	$schemacheck = schema_check();
	if($schemacheck != $schemaversion){
		echo "<font color='red'>";
		echo "Database update is nodig";
		echo "</font>";
	}
	echo "</td></tr>";
	$settingsstatus = checkdefaultsettings();
	if($settingsstatus != ""){
		echo "<tr><td>$settingsstatus</td></tr>";
	}

	echo "<tr><td>";
	echo "</td></tr>";
	$interletsstatus = checkinterlets();
	if($interletsstatus  != ""){
		echo "<tr><td>$interletsstatus</td></tr>";
	}
	echo "</table>";
}

if($s_accountrole == "guest"){
	$mygroup = readconfigfromdb("systemname");
	echo "<table class='data' width='99%'><tr class='header'><td><strong>Interlets login<strong></td></tr>";
	echo "<tr><td>";
	echo "Welkom bij de eLAS installatie van $mygroup.";
	echo "<br>Je bent ingelogd als LETS-gast, je kan informatie raadplegen maar niets wijzigen of transacties invoeren.  Als guest kan je ook niet rechtstreeks reageren op V/A of andere mails versturen uit eLAS";
	echo "</td></tr>";
	echo "</table>";
}

$newsitems = get_all_newsitems();
if($newsitems){
	show_all_newsitems($newsitems);
}

/*  postgres error: LIKE is wrong operator for date type
$birthdays = get_all_birthdays();
if($birthdays){
	show_all_birthdays($birthdays);
}
*/

$newusers = get_all_newusers();
if($newusers){
	show_all_newusers($newusers);
}

$messagerows = get_all_msgs();
	if($messagerows){
			show_all_msgs($messagerows);
}

include($rootpath."includes/inc_footer.php");

//////////////////////////////////////////

function checkdefaultsettings(){
	global $db;
	$query = "SELECT * FROM config WHERE \"default\" = True";
	$result = $db->Execute($query) ;
	$numrows = $result->RecordCount();
	if($numrows > 0){
		$status = "<font color='red'>Er zijn nog settings met standaardwaarden, klik op instellingen om ze te wijzigen of bevestigen</font>";
	} else {
		$status = "";
	}
	return $status;
}

function checkinterlets(){
	global $db;
	$query = "SELECT * FROM letsgroups WHERE apimethod = 'internal'";
	$result = $db->Execute($query) ;
	$numrows = $result->RecordCount();
	if($numrows == 0){
			$status = "<font color='red'>Er bestaat geen LETS groep met type intern voor je eigen groep.  Voeg die toe onder beheer > LETS Groepen.</font>";
	} else {
			$status = "";
	}
	return $status;
}

function schema_check(){
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

function get_all_birthdays(){
	global $db;
	$mymonth = date("m");
	$query = "SELECT * FROM users WHERE status = 1 AND birthday LIKE '%-$mymonth-%'";
	$birthdays = $db->GetArray($query);
	return $birthdays;
}

function get_all_newusers(){
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
	echo "<td colspan='4'><strong>Laatste nieuwe Vraag & Aanbod</strong></td>";
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

		echo '<td>' . $value['postcode'] . '</td>';
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
			u.postcode,
			m.cdate AS date
		FROM messages m, users u
		WHERE m.id_user = u.id
			AND (u.status = 1 OR u.status = 2 OR u.status = 3)
		ORDER BY m.cdate DESC
		LIMIT 100';
	return $db->GetArray($query);
}

