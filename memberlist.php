<?php
ob_start();
$rootpath = "";
$role = 'guest';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_userinfo.php");
require_once($rootpath."includes/inc_form.php");

include($rootpath."includes/inc_header.php");

//echo "<script type='text/javascript' src='$rootpath/js/moomemberlist.js'></script>";

$user_orderby = $_GET["user_orderby"];
$prefix = $_POST["prefix"];
$posted_list["prefix"] = $prefix;
$searchname = $_POST["searchname"];
$sort = $_POST["sort"];

$sort = ($sort) ? $sort : 'letscode';

echo "<h1>Contactlijst</h1>";

echo "<table width='100%' border=0><tr><td>";
echo "<form method='POST' id='memberselect'>";

echo "<table  class='selectbox'>\n";

echo "<tr>\n<td>";
echo "Groep:";
echo "</td><td>\n";
echo "<select name='prefix'>\n";

echo "<option value='ALL'>ALLE</option>";
$list_prefixes = get_prefixes();
foreach ($list_prefixes as $key => $value){
	echo "<option value='" .$value["prefix"] ."'>" .$value["shortname"] ."</option>";
}

//render_select_options($prefixes, $value['prefix']);
echo "</select>\n";
echo "</td>\n";
echo "</tr>";

echo "<tr><td>Naam:</td><td>\n";
echo "<input type='text' name='searchname' value='" . $searchname . "' size='25'>";
echo "</td>";
echo "</tr>";

echo "<tr>\n<td>Sorteer:</td><td>\n";
echo "<select name='sort'>\n";
echo $sort_options = array(
	'letscode' => 'letscode',
	'fullname' => 'naam',
	'postcode' => 'postcode',
	'saldo' => 'saldo');
foreach ($sort_options as $option => $lang)
{
	echo '<option value="' . $option . '"';
	echo ($option == $sort) ? ' selected="selected"' : '';
	echo '>' . $lang . '</option>';
}
echo "</select>\n";
echo "</td>\n";
echo "</tr>";

echo "<tr><td align='right' colspan=2>";
echo "<input type='submit' name='zend' value='Weergeven'>";
echo "</td>";
echo "</tr>";
echo "</table>";
echo "</form>";

//rendermembers
echo "<td align='right'>";
show_printversion($prefix_filterby);
show_csvversion($prefix_filterby,$rootpath);
echo "</td></tr></table>";

$query = 'SELECT * FROM users u
		WHERE status IN (1, 2, 3) 
		AND u.accountrole <> \'guest\' ';
if ($prefix_filterby <> 'ALL'){
	 $query .= 'AND u.letscode like \'' . $prefix_filterby .'%\' ';
}
if(!empty($searchname)){
	$query .= 'AND (LOWER(u.fullname) like \'%' .strtolower($searchname) . '%\'
		OR LOWER(u.name) like \'%' .strtolower($searchname) . '%\') ';
}
if(!empty($sort)){
	$query .= ' ORDER BY u.' . $sort;
}

$userrows = $db->GetArray($query);

$query = 'SELECT tc.abbrev, c.id_user, c.value
	FROM contact c, type_contact tc, users u
	WHERE tc.id = c.id_type_contact
		AND tc.abbrev IN (\'mail\', \'tel\', \'gsm\')
		AND u.id = c.id_user
		AND u.status IN (1, 2, 3)';
$c_ary = $db->GetArray($query);

$contacts = array();

foreach ($c_ary as $c)
{
	$contacts[$c['id_user']][$c['abbrev']] = $c['value'];
}

//show table
echo "<div class='border_b'><table class='data' cellpadding='0' cellspacing='0' border='1' width='99%'>\n";
echo "<tr class='header'>\n";
echo "<td><strong>";
echo "Code";
echo "</strong></td>\n";
echo "<td><strong>";
echo "Naam";
echo "</strong></td>\n";
echo "<td><strong>Tel</strong></td>\n";
echo "<td><strong>gsm</strong></td>\n";
echo "<td><strong>";
echo "Postc";
echo "</strong></td>\n";
echo "<td><strong>Mail</strong></td>\n";
echo "<td><strong>Saldo</strong></td>\n";
echo "</tr>\n\n";
$newuserdays = readconfigfromdb("newuserdays");
$rownumb=0;
foreach($userrows as $key => $value){
	$rownumb=$rownumb+1;
	if($rownumb % 2 == 1){
		echo "<tr class='uneven_row'>\n";
	}else{
			echo "<tr class='even_row'>\n";
	}

	if($value["status"] == 2){
		echo "<td nowrap valign='top' bgcolor='#f475b6'><font color='white' ><strong>";
		echo $value["letscode"];
		echo "</strong></font>";
	}elseif(check_timestamp($value["cdate"],$newuserdays) == 1){
		echo "<td nowrap valign='top' bgcolor='#B9DC2E'><font color='white'><strong>";
		echo $value["letscode"];
					echo "</strong></font>";
	}else{
		echo "<td nowrap valign='top'>";
		echo $value["letscode"];
	}

	echo"</td>\n";
	echo "<td valign='top'>";
	echo "<a href='memberlist_view.php?id=".$value["id"]."'>".htmlspecialchars($value["fullname"],ENT_QUOTES)."</a></td>\n";
	echo "<td nowrap  valign='top'>";
	echo $contacts[$value['id']]['tel'];
	echo "</td>\n";
	echo "<td nowrap valign='top'>";
	echo $contacts[$value['id']]['gsm'];
	echo "</td>\n";
	echo "<td nowrap valign='top'>".$value["postcode"]."</td>\n";
	echo "<td nowrap valign='top'>";
	echo $contacts[$value['id']]['mail'];
	echo "</td>\n";

	echo "<td nowrap valign='top' align='right'>";
	$balance = $value["saldo"];
			if($balance < $value["minlimit"] || ($value["maxlimit"] != NULL && $balance > $value["maxlimit"])){
		echo "<font color='red'> $balance </font>";
	}else{
		echo $balance;
	}

	echo "</td>\n";
	echo "</tr>\n\n";

}
echo "</table></div>";



//show_outputdiv($posted_list["prefix"]);



////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function show_legend(){
echo "<div><br><br><table>";
echo "<tr>";
echo "<td bgcolor='#f475b6'><font color='white'><strong>Rood blokje:</strong></font></td><td>Uitstapper</td>";
echo "</tr><tr>";
echo "<td bgcolor='B9DC2E'><font color='white'><strong>Groen blokje:</strong></font></td><td> Instapper</td>";
echo "</tr></table></div>";
}

function show_outputdiv($prefix){
        echo "<div id='memberdiv'>";
	echo "</div>";
}

function show_header($prefix_filterby,$rootpath) {
        //echo "<form method='POST' action='memberlist.php'>";
    echo "<table width='100%' border=0><tr><td>";
	echo "<form method='POST' id='memberselect' action='" .$rootpath ."/rendermembers.php'>";

        echo "<table  class='selectbox'>\n";

        echo "<tr>\n<td>";
        echo "Groep:";
        echo "</td><td>\n";
        echo "<select name='prefix'>\n";

	echo "<option value='ALL'>ALLE</option>";
	$list_prefixes = get_prefixes();
	foreach ($list_prefixes as $key => $value){
		echo "<option value='" .$value["prefix"] ."'>" .$value["shortname"] ."</option>";
	}
    echo "</select>\n";
    echo "</td>\n";
	echo "</tr>";

	echo "<tr><td>Naam:</td><td>\n";
    echo "<input type='text' name='searchname' size='25'>";
	echo "</td>";
    echo "</tr>";

    echo "<tr>\n<td>Sorteer:</td><td>\n";
    echo "<select name='sort'>\n";
    echo "<option value='letscode'>letscode</option>";
    echo "<option value='fullname'>naam</option>";
    echo "<option value='postcode'>postcode</option>";
    echo "</select>\n";
    echo "</td>\n";
	echo "</tr>";

	echo "<tr><td align='right' colspan=2>";
	echo "<input type='submit' name='zend' value='Weergeven'>";
	echo "</td>";
	echo "</tr>";
	echo "</table>";
	echo "</form>";

	echo "<td align='right'>";
	show_printversion($prefix_filterby);
	show_csvversion($prefix_filterby,$rootpath);
	echo "</td></tr></table>";

}

function get_contacts($userid){
	global $db;
	$query = "SELECT * FROM contact ";
	$query .= " WHERE id_user =".$userid;
 	$query .= " AND contact.flag_public = 1";
	$contactrows = $db->GetArray($query);
	return $contactrows;
}

function show_printversion($prefix_filterby){
	echo "<a href='print_memberlist.php?prefix_filterby=" .$prefix_filterby . "'>";
	echo "<img src='".$rootpath."gfx/print.gif' border='0'> ";
	echo "Printversie</a>";
}

function show_csvversion($prefix_filterby,$rootpath){
	echo "<a href='csv_memberlist.php?prefix_filterby=" .$prefix_filterby;
        echo "' target='new'>";
        echo "<img src='".$rootpath."gfx/csv.jpg' border='0'> ";
        echo "CSV Export</a>";
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

function show_all_users($userrows){
	echo "<div class='border_b'><table class='data' cellpadding='0' cellspacing='0' border='1' width='99%'>\n";
	echo "<tr class='header'>\n";
	echo "<td><strong>";
	echo "<a href='memberlist.php?user_orderby=letscode'>Code</a>";
	echo "</strong></td>\n";
	echo "<td><strong>";
	echo "<a href='memberlist.php?user_orderby=fullname'>Naam</a>";
	echo "</strong></td>\n";
	echo "<td><strong>Tel</strong></td>\n";
	echo "<td><strong>gsm</strong></td>\n";
	echo "<td><strong>";
	echo "<a href='memberlist.php?user_orderby=postcode'>Postc</a>";
	echo "</strong></td>\n";
	echo "<td><strong>Mail</strong></td>\n";
	echo "<td><strong>Stand</strong></td>\n";
	echo "</tr>\n\n";
	$rownumb=0;
	$newuserdays = readconfigfromdb("newuserdays");
	foreach($userrows as $key => $value){
	 	$rownumb=$rownumb+1;
		if($rownumb % 2 == 1){
			echo "<tr class='uneven_row'>\n";
		}else{
	        	echo "<tr class='even_row'>\n";
		}

		if($value["status"] == 2){
			echo "<td nowrap valign='top' bgcolor='#f475b6'><font color='white' ><strong>";
			echo $value["letscode"];
			echo "</strong></font>";
		}elseif(check_timestamp($value["cdate"],$newuserdays) == 1){
			echo "<td nowrap valign='top' bgcolor='#B9DC2E'><font color='white'><strong>";
			echo $value["letscode"];
                        echo "</strong></font>";
		}else{
			echo "<td nowrap valign='top'>";
			echo $value["letscode"];
		}

		echo"</td>\n";
		echo "<td valign='top'>";
		echo "<a href='memberlist_view.php?id=".$value["id"]."'>".htmlspecialchars($value["fullname"],ENT_QUOTES)."</a></td>\n";
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
		echo "<td nowrap valign='top'>".$value["postcode"]."</td>\n";
		echo "<td nowrap valign='top'>";
			foreach($contactrows as $key2 => $value2){
				if ($value2["id_type_contact"] == 3){
					echo "<a href='mailto:".$value2["value"]."'>".$value2["value"]."</a>";
					break;
				}
			}
		echo "</td>\n";

		echo "<td nowrap valign='top' align='right'>";
		$balance = $value["saldo"];
		if($balance < $value["minlimit"] || ($value["maxlimit"] != NULL && $balance > $value["maxlimit"])){
			echo "<font color='red'> $balance </font>";
		}else{
			echo $balance;
		}

		echo "</td>\n";
		echo "</tr>\n\n";

	}
	echo "</table></div>";
}

include($rootpath."includes/inc_footer.php");
