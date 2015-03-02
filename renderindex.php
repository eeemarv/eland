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
$ptitle="home1";

// get the initial includes
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_hosting.php");

$s_id = $_SESSION["id"];
$s_name = $_SESSION["name"];
$s_letscode = $_SESSION["letscode"];
$s_accountrole = $_SESSION["accountrole"];
	
if(isset($s_id)){

	if($s_accountrole == "admin"){
		// Sanity check output
		echo "<table class='data' width='99%'><tr class='header'><td>eLAS Status (admin)</td></tr>";

		echo "<tr><td>";
		$schemacheck = schema_check();
		if($schemacheck != $schemaversion){
			echo "<font color='red'>";
			echo "Database update is nodig";
			//echo ", ga naar <a href='http://" .$baseurl ."/resources/system/update'>de update pagina</a> om de database bij te werken";
			echo "</font>";
		}
		echo "</td></tr>";
		$settingsstatus = checkdefaultsettings();
		if($settingsstatus != ""){
			echo "<tr><td>$settingsstatus</td></tr>";
		}

		echo "<tr><td>";
		if(check_contract() == 0){
			echo "<font color='red'>";
                        echo "Het hosting contract van deze installatie is vervallen, controleer het hosting contract onder het beheerdersmenu > <a href='hosting/contract.php'>hosting</a>";
		echo "</font>";
                }
                echo "</td></tr>";
		//Check for an internal interlets account with valid soap connection
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

}else{
	redirect_login($rootpath);
}



////////////////////////////////////////////////////////////////////////////
////////////////////////////////F U N C T I E S ////////////////////////////
////////////////////////////////////////////////////////////////////////////

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
				echo $value["idate"];
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
		echo htmlspecialchars($value["username"],ENT_QUOTES)." (".trim($value["letscode"]).")";
		echo "</td>";
		echo "</tr>";
	}
	//echo "<tr><td colspan='2'>&#160;</td></tr>";
	echo "</table></div>";
}



function get_all_newsitems(){
	global $db;
	$query = "SELECT *, ";
	$query .= "news.id AS nid, ";
	$query .= " news.cdate AS date, ";
	$query .= " news.itemdate AS idate ";
	$query .= " FROM news, users ";
	$query .= " WHERE news.id_user = users.id AND approved = True";
	$query .= " ORDER BY news.itemdate DESC ";
	$query .= " LIMIT 50 ";
	//print $query;
	$newsitems = $db->GetArray($query);
	if(!empty($newsitems)){
		return $newsitems;
	}
}

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
	
}

function get_all_msgs(){
	global $db;
	$query = "SELECT *, ";
	$query .= " messages.id AS msgid, ";
	$query .= " messages.validity AS valdate, ";
	$query .= " users.id AS userid, ";
	$query .= " categories.id AS catid, ";
	$query .= " categories.name AS catname, ";
	$query .= " users.name AS username, ";
	$query .= " messages.cdate AS date ";
	$query .= " FROM messages, users, categories ";
	$query .= " WHERE messages.id_user = users.id";
	$query .= " AND messages.id_category = categories.id";
	$query .= " AND (users.status = 1 OR users.status = 2 OR users.status = 3) ";
	$query .= " ORDER BY messages.cdate DESC ";
	$query .= " LIMIT 100 ";
	$messagerows = $db->GetArray($query);
	return $messagerows;
}

?>
