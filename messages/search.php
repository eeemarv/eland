<?php
ob_start();
$rootpath = "../";
$role = 'guest';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

include($rootpath."includes/inc_header.php");

if(isset($s_id)){
	show_ptitle();
	if(isset($_GET["distance"])) {
		$distance = $_GET["distance"];
	}

	if(isset($_GET["q"])){
		$q = $_GET["q"];

		show_form($q,$distance,$s_user_postcode);

		$start = 0;
		$limit = 5;
		if(isset($_GET["start"])){
			$start = $_GET["start"];
		}
		if(isset($_GET["limit"])){
			$limit = $_GET["limit"];
		}

		$zoekresultaten = search_db($q,$distance,$s_user_postcode);
		$aantal = get_number_results($q,$distance,$s_user_postcode);

		$id_user = show_results($zoekresultaten);
		echo "</div>";

	}else{
		show_form($q,$distance,$s_user_postcode);
	}

}else{
	redirect_login($rootpath);
}

////////////////////////////////////////////////////////////////////////////

function chop_string($content, $maxsize){
	$strlength = strlen($content);
	if ($strlength >= $maxsize){
		$spacechar = strpos($content," ", 50);
		if($spacechar == 0){
			return $content;
		}else{
			return substr($content,0,$spacechar);
		}
	}else{
		return $content;
	}
}

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

function show_resultnavigation($start, $limit, $aantal, $q){
	$start = $start+1;
	echo "<br>Resultaten van ".$start." tot " .($start+$limit)."</p>";
	$aantalpags = ceil($aantal / $limit);
	echo " | ";
	for($i=1; $i<=$aantalpags; $i++){
		echo " <a href='search.php?q=".$q;
		echo "&start=".((($i-1)*$limit));
		echo "&limit=".$limit."'>".$i."</a> | ";
	}
}

function show_results($zoekresultaten){
	global $rootpath;
	echo "<div class='border_b'>";
	echo "<table class='data' cellpadding='0' cellspacing='0' border='1' width='99%'>";
	echo "<tr class='header'>";
	echo "<td valign='top' nowrap><strong>V/A</strong></td>";
	echo "<td valign='top' nowrap><strong>Inhoud</strong></td>";
	echo "<td valign='top' nowrap><strong>Wie</strong></td>";
	echo "</tr>";
	$rownumb=0;
	foreach ($zoekresultaten as $value){
		$rownumb=$rownumb+1;
		if($rownumb % 2 == 1){
			echo "<tr class='uneven_row'>";
		}else{
			echo "<tr class='even_row'>";
		}
		echo "<td valign='top' nowrap>";
		if($value["msg_type"] == 0){
			echo "V";
		}
		if($value["msg_type"] == 1){
			echo "A";
		}
		echo "</td>";
		echo "<td valign='top'>";
		echo '<a href="' . $rootpath . 'messages/view.php?id=' . $value["mid"] . '">';
		$content = htmlspecialchars($value["content"],ENT_QUOTES);
		echo chop_string($content, 50);
		if(strlen($content)>50){
			echo "...";
		}
		echo "</a></td>";
		echo "</td><td valign='top' nowrap>";
		echo '<a href="'.$rootpath.'memberlist_view.php?id=' . $value['id_user'] . '">';
		echo htmlspecialchars($value["name"],ENT_QUOTES) ." (". trim($value["letscode"]).")";
		echo "</a></td></tr>";
	}
	echo "</table></div>";
}

function show_number_results($aantal, $q){
	if($aantal == 0){
		echo "<p><strong>Geen zoekresultaten gevonden voor ". $q ."</strong>";
	}
	if($aantal == 1){
		echo "<p><strong>". $aantal." zoekresultaat gevonden voor ". $q ."</strong>";
	}
	if($aantal > 1){
		echo "<p><strong>".$aantal." zoekresultaten gevonden voor ". $q ."</strong>";
	}
}

function search_db($q,$distance,$user_postcode){
	global $db;
	// Make search lowercase
	$q = strtolower($q);

	$geo = (!empty($user_postcode) && !empty($distance) && filter_var($distance, FILTER_VALIDATE_INT));

	$query = "SELECT *, ";
	$query .= "m.id AS mid ";
	if($geo) {
		$query .= " FROM messages m, users u, city_distance d ";
	} else {
		$query .= " FROM messages m, users u ";
	}
	$query .= " WHERE LOWER(content) LIKE '%$q%' ";
	$query .= " AND m.id_user = u.id ";
	$query .= " AND (u.status = 1 OR u.status = 2 OR u.status = 3) ";
	if($geo) {
		$query .= " AND (u.postcode = d.code_to) ";
		$query .= " AND d.code_from = '$user_postcode' ";
		$query .= " AND d.distance < $distance ";
	}
	$zoekresultaten = $db->GetArray($query);

	return $zoekresultaten;
}

function get_number_results($q,$distance,$user_postcode){
	global $db;

	$geo = (!empty($user_postcode) && !empty($distance) && filter_var($distance, FILTER_VALIDATE_INT));

	$query = "SELECT COUNT(*) ";
	$query .= " AS aantal ";
	if($geo) {
		$query .= " FROM messages, users, city_distance d ";
	} else {
		$query .= " FROM messages, users ";
	}
	$query .= " WHERE content LIKE '%$q%' ";
	$query .= " AND messages.id_user = users.id ";
	if($geo) {
		$query .= " AND (users.postcode = d.code_to) ";
		$query .= " AND d.code_from = '$user_postcode' ";
		$query .= " AND d.distance < $distance ";
	}
	$row = $db->GetRow($query);
	return $row["aantal"];
}

function show_form($q,$distance,$s_user_postcode){
	if(!empty($distance) && !filter_var($distance, FILTER_VALIDATE_INT)) {
		echo "<font color='#FF0000'>Fout bij opgave maximum afstand. <strong>Toont alle resultaten.</strong></font>";
	}
	echo "<form method='get' action='search.php'>";
	echo "<input type='text' name='q' size='40' ";
	if (!empty($q)){
		echo " value=".$q;
	}
	echo ">";
	echo "<input type='submit' name='zend' value='Zoeken'>";

	if(!empty($s_user_postcode) &&  filter_var($s_user_postcode, FILTER_VALIDATE_INT)) {
		echo "<br><small><i>Maximum afstand (rond je postcode) : <input type='text' size='1' name='distance'";
		if(!empty($distance)) {
			echo " value=".$distance;
		}
		echo "> km.</i></small>";
	}
	echo "</form>";
}

function show_ptitle(){
	echo "<h1>Zoek op trefwoord</h1>";
}

include($rootpath."includes/inc_footer.php");

