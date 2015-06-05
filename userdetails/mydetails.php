<?php
ob_start();
$rootpath = "../";
$role = 'user';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

if (!isset($s_id))
{
	header("Location: " . $rootpath . "login.php");
	exit;
}

if (!($s_accountrole == 'user' || $s_accountrole == 'admin'))
{
	exit;
}

$includejs = '<script type="text/javascript">var user_id = ' . $s_id . ';</script>
	<script src="' . $cdn_jquery . '"></script>
	<script src="' . $cdn_jqplot . 'jquery.jqplot.min.js"></script>
	<script src="' . $cdn_jqplot . 'plugins/jqplot.donutRenderer.min.js"></script>
	<script src="' . $cdn_jqplot . 'plugins/jqplot.cursor.min.js"></script>
	<script src="' . $cdn_jqplot . 'plugins/jqplot.dateAxisRenderer.min.js"></script>
	<script src="' . $cdn_jqplot . 'plugins/jqplot.canvasTextRenderer.min.js"></script>
	<script src="' . $cdn_jqplot . 'plugins/jqplot.canvasAxisTickRenderer.min.js"></script>
	<script src="' . $cdn_jqplot . 'plugins/jqplot.highlighter.min.js"></script>
	<script src="' . $rootpath . 'js/plot_user_transactions.js"></script>';

$includecss = '<link rel="stylesheet" type="text/css" href="' . $cdn_jqplot . 'jquery.jqplot.min.css" />
	<link rel="stylesheet" type="text/css" href="' . $rootpath . 'gfx/tooltip.css" />';

$h1 = 'Mijn gegevens';

include $rootpath . 'includes/inc_header.php';

$user = readuser($s_id);
show_user($user);
show_editlink();

$contact = get_contact($s_id);
show_contact($contact, $s_id);

$balance = $user["saldo"];
show_balance($balance, $user, readconfigfromdb("currency"));

include $rootpath . 'includes/inc_footer.php';


function show_changepwlink($s_id){
	echo "<p>| <a href='mydetails_pw.php?id=" .$s_id. "'>Paswoord veranderen</a> |</p>";
}

function get_type_contacts(){
	global $db;
	$query = "SELECT * FROM type_contact";
	return $db->GetArray($query);
}

function show_editlink()
{
	global $s_id;
	echo "<table width='100%' border=0><tr><td>";
	echo "<div id='navcontainer'>";
	echo "<ul class='hormenu'>";
	echo '<li><a href="mydetails_edit.php">Gegevens aanpassen</a></li>';
	echo "<li><a href='mydetails_pw.php' id='showpwform'>Passwoord wijzigen</a></li>";

	echo "<script type='text/javascript'>function AddPic () { OpenTBox('" ."/userdetails/upload_picture.php" ."'); } </script>";
    echo "<li><a href='javascript: AddPic()'>Foto toevoegen</a></li>";

	echo "<script type='text/javascript'>function RemovePic() {  OpenTBox('" ."/userdetails/remove_picture.php?id=" .$s_id ."'); } </script>";
	echo "<li><a href='javascript: RemovePic();'>Foto verwijderen</a></li>";

	echo "</ul>";
	echo "</div>";
	echo "</td></tr></table>";
}

function show_user($user)
{
	global $rootpath;

	echo "<table class='memberview' cellpadding='0' cellspacing='0' border='0' width='99%'>";
	echo "<tr class='memberheader'>";

	// Show header block
	echo "<td colspan='2' valign='top'><strong>".htmlspecialchars($user["name"],ENT_QUOTES)." (";
	echo trim($user["letscode"])." )";
	if($user["status"] == 2){
		echo " <font color='#F56DB5'>Uitstapper </font>";
	}
	echo "</strong></td></tr>";
	// End header

	// Wrap arround another table to show user picture
	echo "<td width='170' align='left'>";
	if(!isset($user["PictureFile"])) {
		echo "<img src='" .$rootpath ."gfx/nouser.png' width='250'></img>";
	} else {
		echo '<img src="https://s3.eu-central-1.amazonaws.com/' . getenv('S3_BUCKET') . '/'. $user['PictureFile'] .'" width="250"></img>';
	}
	echo "</td>";

	// inline table
	echo "<td>";
	echo "<table cellpadding='0' cellspacing='0' border='0' width='100%'>";
	echo "<tr><td width='50%' valign='top'>Naam: </td>";
	echo "<td width='50%' valign='top'>".$user["fullname"]."</td></tr>";
	echo "<tr><td width='50%' valign='top'>Postcode: </td>";
	echo "<td width='50%' valign='top'>".$user["postcode"]."</td></tr>";
	echo "<tr><td width='50%' valign='top'>Geboortedatum:  </td>";
	echo "<td width='50%' valign='top'>".$user["birthday"]."</td></tr>";

	echo "<tr><td valign='top'>Hobbies/interesses: </td>";
	echo "<td valign='top'>".htmlspecialchars($user["hobbies"],ENT_QUOTES)."</td></tr>";
	echo "<tr><td valign='top'>Commentaar: </td>";
	echo "<td valign='top'>".htmlspecialchars($user["comments"],ENT_QUOTES)."</td></tr>";

	echo "<tr><td valign='top'>Saldo mail met recent vraag en aanbod: </td><td valign='top'>";
	echo ($user["cron_saldo"] == 't') ? "Aan" : "Uit";
	echo '</td></tr>';

	echo "</table>";
	echo "</td>";
	echo "</table>";
}


function show_balance($balance, $user, $currency){
	echo "<div class='border_b'>";
	echo "<table class='memberview' cellpadding='0' cellspacing='0' border='0' width='99%'>";
	echo "<tr class='memberheader'><td colspan='2'>";
	echo "<strong>{$currency}stand</strong></td></tr>";
	echo "<tr>";
	echo "<td width='50%'>Huidige {$currency}stand: </td>";
	echo "<td width='50%'>";
	echo $balance;
	echo "</td></tr>";
	echo "<tr>";
	echo "<td width='50%'>Limiet minstand: </td>";
	echo "<td width='50%'>";
	echo $user["minlimit"];
	echo "</td></tr>";
	echo "<td width='50%'>Limiet maxstand: </td>";
	echo "<td width='50%'>";
	echo $user["maxlimit"];
	echo "</td></tr>";
	echo "</table>";
	echo "<table  cellpadding='0' cellspacing='0' border='0'  width='99%'>";
	echo "<tr></tr><td><div id='chartdiv1' style='height:300px;width:400px;'></div></td>";
	echo "<td><div id='chartdiv2' style='height:300px;width:300px;'></div></td></tr></table>";
}

function get_contact($id){
	global $db;
	$query = "SELECT *, ";
	$query .= " contact.id AS cid, users.id AS uid, type_contact.id AS tcid, ";
	$query .= " type_contact.name AS tcname, users.name AS uname ";
	$query .= " FROM users, type_contact, contact ";
	$query .= " WHERE users.id=".$id;
	$query .= " AND contact.id_type_contact = type_contact.id ";
	$query .= " AND users.id = contact.id_user ";

	$contact = $db->GetArray($query);
	return $contact;
}

function show_contact($contact, $user_id){
	echo "<div >";
	echo "<table cellpadding='0' cellspacing='0' border='1' width='99%' class='data'>";

	echo "<tr class='even_row'>";
	echo "<td colspan='5'><p><strong>Contactinfo</strong></p></td>";
	echo "</tr>";
echo "<tr>";
echo "<th valign='top'>Type</th>";
echo "<th valign='top'>Waarde</th>";
echo "<th valign='top'>Commentaar</th>";
echo "<th valign='top'>Publiek</th>";
echo "<th valign='top'></th>";
echo "</tr>";

	foreach($contact as $key => $value){
		echo "<tr>";
		echo "<td valign='top'>".$value["abbrev"].": </td>";
		echo "<td valign='top'>".htmlspecialchars($value["value"],ENT_QUOTES)."</td>";
		echo "<td valign='top'>".htmlspecialchars($value["comments"],ENT_QUOTES)."</td>";
		echo "<td valign='top'>";
		echo ($value["flag_public"]) ? "Ja" : "Nee";
		echo "</td>";
		echo "<td valign='top' nowrap>|";
		echo '<a href="mydetails_cont_edit.php?id='.$value["id"].'">';
		echo " aanpassen </a> |";
		echo '<a href="mydetails_cont_delete.php?id='.$value["id"].'">';
		echo "verwijderen </a>|";
		echo "</td>";
		echo "</tr>";
	}
	echo "<tr><td colspan='5'><p>&#160;</p></td></tr>";
	echo "<tr><td colspan='5'>| ";
	echo "<a href='mydetails_cont_add.php'>";
	echo "Contact toevoegen</a> ";
	echo "|</td></tr>";
	echo "</table></div>";
}
