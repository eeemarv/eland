<?php
ob_start();
$rootpath = "../";
$role = 'user';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_userinfo.php");

include($rootpath."includes/inc_header.php");

if (isset($s_id)){
        if($s_accountrole == "user" || $s_accountrole == "admin" || $s_accountrole == "interlets"){
		$user = get_user($s_id);
		echo "<h1>Mijn transacties</h1>";
		
		echo $user["$minlimit"];
		$balance = $user["saldo"];
		show_balance($balance, $user);

	$interletsq = $db->GetArray('SELECT * FROM interletsq WHERE id_from = ' .$s_id);

	if(!empty($interletsq)){
			echo "<h2>Interlets transacties in verwerking</h2>";
			echo "<div class='border_b'>";
			echo "<table class='data' cellpadding='0' cellspacing='0' border='1' width='99%'>";
			echo "<tr class='header'>";
			//echo "<td valign='top'>TransID</td>";
			echo "<td valign='top'>Datum</td>";
			echo "<td valign='top'>Van</td>";
			echo "<td valign='top'>Groep</td>";
			echo "<td valign='top'>Aan</td>";
			echo "<td valign='top'>Waarde</td>";
			echo "<td valign='top'>Omschrijving</td>";
			echo "<td valign='top'>Pogingen</td>";
			echo "<td valign='top'>Status</td>";
			echo "</tr>";

			$rownumb=0;
			foreach($interletsq as $key => $value){
				$rownumb=$rownumb+1;
				if($rownumb % 2 == 1){
					echo "<tr class='uneven_row'>";
				}else{
					echo "<tr class='even_row'>";
				}
				echo "<td nowrap valign='top'>";
					echo $value["date_created"];
					echo "</td>";

				echo "<td nowrap valign='top'>";
			$user = get_user($value["id_from"]);
					//echo $value["id_from"];
			echo $user["fullname"];
					echo "</td>";

					echo "<td nowrap valign='top'>";
			$group = get_letsgroup($value["letsgroup_id"]);
			echo $group["shortname"];
					//echo $value["letsgroup_id"];
					echo "</td>";

			echo "<td nowrap valign='top'>";
					echo $value["letscode_to"];
					echo "</td>";

					echo "<td nowrap valign='top'>";
					$ratio = readconfigfromdb("currencyratio");
					$realvalue = $value["amount"] * $ratio;
					echo $realvalue;
					echo "</td>";

					echo "<td nowrap valign='top'>";
					echo $value["description"];
					echo "</td>";

					echo "<td nowrap valign='top'>";
					echo $value["retry_count"];
					echo "</td>";

			echo "<td nowrap valign='top'>";
					echo $value["last_status"];
					echo "</td>";

			echo "</tr>";
		}
		echo "</table></div>";
	}

	//my transactions

	$query = 'SELECT t.*, 
			fu.name AS fromname,
			fu.id AS fromid,
			fu.letscode AS fromcode,
			fu.minlimit AS fromminlimit,
			tu.name AS toname,
			tu.id AS toid,
			tu.letscode AS tocode,
			t.date AS datum
		FROM transactions t, users fu, users tu
		WHERE (t.id_from =' . $s_id . ' OR t.id_to = '. $s_id. ')
			AND t.id_from = fu.id
			AND t.id_to = tu.id ORDER BY date DESC';
	$transactions =  $db->GetArray($query);

	// show transactions
	echo "<div class='border_b'>";
	echo "<table class='data' cellpadding='0' cellspacing='0' border='1' width='99%'>";
	echo "<tr class='header'>";
	echo "<td><strong>Datum</strong></td><td><strong>Van</strong></td>";
	echo "<td><strong>Aan</strong></td>";
	echo "<td><strong>Bedrag uit</strong></td>";
	echo "<td><strong>Bedrag in</strong></td>";
	echo "<td><strong>Dienst</strong></td></tr>";
	$rownumb=0;
	foreach ($transactions as $key => $value){
	 	$rownumb=$rownumb+1;
		if($rownumb % 2 == 1){
			echo "<tr class='uneven_row'>";
		}else{
	        	echo "<tr class='even_row'>";
		}
		echo "<td valign='top'>";
		echo $value["datum"];
		echo '</td><td valign="top"';
		echo ($value['fromid'] == $s_id) ? ' class="me"' : '';
		echo '>';		
		if(!empty($value["real_from"])){
			echo htmlspecialchars($value["real_from"],ENT_QUOTES);
		} else {
			echo '<a href="' . $rootpath . 'memberlist_view.php?id=' . $value['fromid'] . '">';
			echo htmlspecialchars($value["fromname"],ENT_QUOTES)." (".trim($value["fromcode"]).")";
			echo '</a>';
		}
		echo '</td><td valign="top"';
		echo ($value['toid'] == $s_id) ? ' class="me"' : '';
		echo '>';
		if(!empty($value["real_to"])){
			echo htmlspecialchars($value["real_to"],ENT_QUOTES);
		} else {
			echo '<a href="' . $rootpath . 'memberlist_view.php?id=' . $value['toid'] . '">';
			echo htmlspecialchars($value["toname"],ENT_QUOTES)." (".trim($value["tocode"]).")";
			echo '</a>';
		}
		echo "</td>";

		if ($value["fromid"] == $s_id){
		 		echo "<td valign='top'>";
				echo "-".$value["amount"];
				echo "</td>";
				echo "<td></td>";
		}else{
			echo "<td></td>";
			echo "<td valign='top'>";
			echo "+".$value["amount"];
			echo "</td>";
		}
		echo "<td valign='top'>";
		echo htmlspecialchars($value["description"],ENT_QUOTES);
		echo "</td></tr>";
	}
	echo "</table></div>";	

	}else{
		redirect_login($rootpath);
	}
}else{
	redirect_login($rootpath);
}

////////////////////////////////////////////////////////////////////////////

function show_balance($balance, $user){
	$currency = readconfigfromdb("currency");
	$minlimit = $user["minlimit"];
	if ($balance < $minlimit ){
		echo "<strong><font color='red'>Je hebt de limiet minstand bereikt.<br>";
		echo " Je kunt geen {$currency} uitschrijven!</font></strong>";
	}
	if($user["maxlimit"] != NULL && $balance > $user["maxlimit"]){
		echo "<strong><font color='red'>Je hebt de limiet maxstand bereikt.<br>";
                echo " Je kunt geen {$currency} meer ontvangen</font></strong>";
        }
	echo "<p><strong>Huidige {$currency}stand: ".$balance."</strong></br>";
	echo "Limiet minstand: ".$minlimit."</p>";
}



function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}


include($rootpath."includes/inc_footer.php");
