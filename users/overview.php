<?php
ob_start();
$rootpath = "../";
$r
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

include($rootpath."includes/inc_header.php");

//status 0: inactief
//status 1: letser
//status 2: uitstapper
//status 3: instapper
//status 4: secretariaat
//status 5: infopakket
//status 6: stapin
//status 7: extern

$user_orderby = "letscode";
if (isset($_GET["user_orderby"])){
    $user_orderby = $_GET["user_orderby"];
}

if(isset($s_id) && ($s_accountrole == "admin")){
	show_addlink($rootpath);

	show_ptitle1();
	$active_userrows = get_active_users($user_orderby);
	show_active_legend();
	show_active_users($active_userrows, $configuration);
	show_ptitle2();
	$inactive_userrows = get_inactive_users($user_orderby);

show_inactive_legend();
	show_inactive_users($inactive_userrows, $balance);

}else{
	redirect_login($rootpath);
}

////////////////////////////////////////////////////////////////////////////


function show_printversion(){
	echo "<p><a href='../text_memberlist.php'>";
	echo "Tekstversie</a></p>";
}

function show_active_legend(){
	echo "<table>";
	echo "<tr>";
	echo "<td bgcolor='#B9DC2E'><font color='white'>";
	echo "<strong>Groen blokje:</strong></font></td><td> Instapper<br>";
	echo "</tr>";
	echo "<tr>";
	echo "<td bgcolor='#f56db5'><font color='white'>";
	echo "<strong>Rood blokje:</strong></font></td><td>Uitstapper<br>";
	echo "</tr>";

	echo "</tr></table>";
}

function show_inactive_legend(){
	echo "<table>";
	echo "<tr>";
	echo "<td bgcolor='#000000'><font color='white'>";
	echo "<strong>Zwart blokje:</strong></td><td> gedesactiveerd</td>";
	echo "</tr>";
	echo "<tr>";
	echo "<td bgcolor='orange'><font color='white'>";
	echo "<strong>Oranje blokje:</strong></td><td> Infopakket</td>";
	echo "</tr>";
	echo "<tr>";
	echo "<td bgcolor='blue'><font color='white'>";
	echo "<strong>Blauw blokje:</strong></td><td> Infoavond</td>";
	echo "</tr>";
	echo "<tr>";
	echo "<td bgcolor='#999999'><font color='white'>";
	echo "<strong>Grijs blokje:</strong></td><td> Extern</td>";

	echo "</tr>";
	echo "</table>";
}

function check_timestamp($cdate,$agelimit){
        // agelimit is the time after which it expired
        $now = time();
        // age should be converted to seconds
        $limit = $now - ($agelimit * 60 * 60 * 24);
        $timestamp = strtotime($cdate);

        if($limit < $timestamp) {
                return 1;
        } else {
                return 0;
        }
}

function show_addlink($rootpath){
	global $s_id;
        echo "<table width='100%' border=0><tr><td>";
        echo "<div id='navcontainer'>";
        echo "<ul class='hormenu'>";
        $myurl="edit.php?mode=new";
        echo "<li><a href='#' onclick=window.open('$myurl','details_edit','width=800,height=820,scrollbars=yes,toolbar=no,location=no,menubar=no')>Toevoegen</a></li>";
        echo "</ul>";
        echo "</div>";
        echo "</td></tr></table>";
}

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

function show_ptitle1(){
	echo "<h1>Overzicht actieve gebruikers</h1>";
}

function show_ptitle2(){
	echo "<h1>Overzicht inactieve gebruikers</h1>";
}
//SHOW INACTIVE USERS
function show_inactive_users($inactive_userrows){
	echo "<table class='data' cellpadding='0' cellspacing='0' border='1' width='99%'>";
	echo "<tr class='header'>";
	echo "<td valign='top'><strong>";
	echo "<a href='overview.php?user_orderby=letscode'>Nr.</a>";
	echo "</strong></td>";
	echo "<td valign='top'><strong>";
	echo "<a href='overview.php?user_orderby=fullname'>Naam</a>";
	echo "</strong></td>";
	//
	echo "<td><strong>Tel</strong></td>\n";
	echo "<td><strong>gsm</strong></td>\n";
//
	echo "<td valign='top'><strong>";
	echo "<a href='overview.php?user_orderby=postcode'>Postc</a>";
	echo "</strong></td>";
	echo "<td valign='top'><strong>";
	echo "Mail";
	echo "</strong></td>";
	echo "<td valign='top'><strong>";
	echo "Stand";
	echo "</strong></td>";
	echo "</tr>\n\n";
	$rownumb=0;
	foreach($inactive_userrows as $key => $value){

		$rownumb=$rownumb+1;
		echo "<tr";
		if($rownumb % 2 == 1){
			echo " class='uneven_row'";
		}else{
	        echo " class='even_row'";
		}
		echo ">\n";

		if($value["status"] == 5){
			echo "<td nowrap valign='top' bgcolor='orange'><font color='white'><strong>";
		}elseif($value["status"] == 6){
			echo "<td nowrap valign='top' bgcolor='blue'><font color='white'><strong>";
		}elseif($value["status"] == 0){
			echo "<td nowrap valign='top' bgcolor='#000000'><font color='white'><strong>";
		}elseif($value["status"] == 7){
			echo "<td nowrap valign='top' bgcolor='#999999'><font color='white'><strong>";
		}else{
			echo "<td nowrap valign='top'>";
		}
		echo "<a href='view.php?id=".$value["id"]."'>";
		echo trim($value["letscode"]);
		echo "</a>";
		if($value["status"] == 5 ){
			echo "</strong></font>";
		}
		if($value["status"] == 6){
			echo "</strong></font>";
		}
		if($value["status"] == 7){
			echo "</strong></font>";
		}
		if($value["status"] == 0){
			echo "</strong></font>";
		}
		echo "</td>\n";

		echo "<td nowrap valign='top'>";
		echo "<a href='view.php?id=".$value["id"]."'>".htmlspecialchars($value["fullname"],ENT_QUOTES)."</a>";
		echo "</td>\n";

//_____________________________________________
		echo "<td nowrap  valign='top'>";
		$userid = $value["id"];
		$contactrows = get_contacts($userid);

			foreach($contactrows as $key2 => $value2){
				if ($value2["id_type_contact"] == 1){
					echo  $value2["value"];
				break;
				}
			}
		echo "</td>\n";
		echo "<td nowrap valign='top'>";
			foreach($contactrows as $key2 => $value2){
				if ($value2["id_type_contact"] == 2){
					echo $value2["value"];
					break;
				}
			}
		echo "</td>\n";
//___________________________________________________
		$userid = $value["id"];
		echo "<td nowrap valign='top'>".$value["postcode"]."</td>\n";
		echo "<td nowrap valign='top'>";
		$contactrows = get_contacts($userid);
			foreach($contactrows as $key2 => $value2){
				if ($value2["id_type_contact"] == 3){
					echo "<a href='mailto:".$value2["value"]."'>".$value2["value"]."</a>";
				break;
				}
			}
		echo "</td>\n";
		$balance = $value["saldo"];
		if($balance < $value["minlimit"] || ($value["maxlimit"] != NULL && $value["maxlimit"] != 0 && $balance > $value["maxlimit"])){
			echo "<td align='right'><font color='red'>".$balance."</font></td>\n";
		} else {
			echo "<td align='right'>".$balance."</td>\n";
		}
		echo "</tr>\n\n";
	}

	echo "</table>\n</div>\n";
}

//SHOW ACTIVE USERS
function show_active_users($active_userrows,$configuration){
	echo "<div class='border_b'><table class='data' cellpadding='0' cellspacing='0' border='1' width='99%'>";
	echo "<tr class='header'>";
	echo "<td valign='top'><strong>";
	echo "<a href='overview.php?user_orderby=letscode'>Nr.</a>";
	echo "</strong></td>";
	echo "<td valign='top'><strong>";
	echo "<a href='overview.php?user_orderby=fullname'>Naam</a>";
	echo "</strong></td>";
	echo "<td valign='top'><strong>";
	echo "<a href='overview.php?user_orderby=accountrole'>Rol</a>";
	echo "</strong></td>";
	//
	echo "<td><strong>Tel</strong></td>\n";
	echo "<td><strong>gsm</strong></td>\n";
//
	echo "<td valign='top'><strong>";
	echo "<a href='overview.php?user_orderby=postcode'>Postc</a>";
	echo "</strong></td>";
	echo "<td valign='top'><strong>";
	echo "Mail";
	echo "</strong></td>";
	echo "<td valign='top'><strong>";
	echo "Stand";
	echo "</strong></td>";
	echo "</tr>\n\n";
	$rownumb=0;
	foreach($active_userrows as $key => $value){
		$myurl = "view.php?id=".$value["id"];
		$rownumb=$rownumb+1;
		echo "<tr";
		if($rownumb % 2 == 1){
			echo " class='uneven_row'";
		}else{
	        echo " class='even_row'";
		}

		if($value["status"] == 0){
		echo " bgcolor='black' ";
		}
		echo ">\n";

                if($value["status"] == 2){
                        echo "<td nowrap valign='top' bgcolor='#f475b6'><font color='white' ><strong>";
			echo "<a href='$myurl'>" .$value["letscode"] ."</a>";
                        echo "</strong></font>";
                }elseif(check_timestamp($value["adate"],readconfigfromdb("newuserdays")) == 1){
                        echo "<td nowrap valign='top' bgcolor='#B9DC2E'><font color='white'><strong>";
                        echo "<a href='$myurl'>" .$value["letscode"] ."</a>";
                        echo "</strong></font>";
                }else{
                        echo "<td nowrap valign='top'>";
			echo "<a href='$myurl'>" .$value["letscode"] ."</a>";
                }

		echo "</td>\n";

		echo "<td nowrap valign='top'>";
		echo "<a href='$myurl'>".htmlspecialchars($value["fullname"],ENT_QUOTES)."</a>";
		echo "</td>\n";

		echo "<td nowrap valign='top'>";
		echo $value["accountrole"];
		echo "</td>\n";

//_____________________________________________
		echo "<td nowrap  valign='top'>";
		$userid = $value["id"];
		$contactrows = get_contacts($userid);

			foreach($contactrows as $key2 => $value2){
				if ($value2["id_type_contact"] == 1){
					echo  $value2["value"];
				break;
				}
			}
		echo "</td>\n";
		echo "<td nowrap valign='top'>";
			foreach($contactrows as $key2 => $value2){
				if ($value2["id_type_contact"] == 2){
					echo $value2["value"];
					break;
				}
			}
		echo "</td>\n";
//___________________________________________________
		$userid = $value["id"];
		echo "<td nowrap valign='top'>".$value["postcode"]."</td>\n";
		echo "<td nowrap valign='top'>";
		$contactrows = get_contacts($userid);
			foreach($contactrows as $key2 => $value2){
				if ($value2["id_type_contact"] == 3){
					echo "<a href='mailto:".$value2["value"]."'>".$value2["value"]."</a>";
				break;
				}
			}
		echo "</td>\n";
		$balance = $value["saldo"];
		if($balance < $value["minlimit"] || ($value["maxlimit"] != NULL && $balance > $value["maxlimit"])){
			echo "<td align='right'><font color='red'>".$balance."</font></td>\n";
		} else {
			echo "<td align='right'>".$balance."</td>\n";
		}
		echo "</tr>\n\n";
	}
		echo "</table>\n</div>\n";
}

function get_active_users($user_orderby){
	global $db;
	$query = "SELECT * FROM users ";
	$query .= " WHERE status = 1 OR status = 2 OR status = 3 OR status = 4 ";
	if (isset($user_orderby)){
		$query .= " ORDER BY users.".$user_orderby. " ";
	}
	$active_userrows = $db->GetArray($query);
	return $active_userrows;
}

function get_inactive_users($user_orderby){
	global $db;
	$query = "SELECT * FROM users ";
	$query .= " WHERE status = 0 OR status = 5 OR status = 6 OR status = 7 ";
	if (isset($user_orderby)){
		$query .= " ORDER BY users.".$user_orderby. " ";
	}
	$inactive_userrows = $db->GetArray($query);
	return $inactive_userrows;
}

function get_contacts($userid){
	global $db;
	$query = "SELECT * FROM contact ";
	$query .= " WHERE id_user =".$userid;
	if (isset($contact_orderby)){
		$query .= " ORDER BY contact.".$contact_orderby. " ";
	}
	$contactrows = $db->GetArray($query);
	return $contactrows;
}

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_footer.php");
?>
