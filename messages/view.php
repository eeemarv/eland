<?php
ob_start();
$rootpath = "../";
$role = 'guest';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_userinfo.php");
require_once($rootpath."includes/inc_mailfunctions.php");

$msgid = $_GET["id"];
if(!isset($msgid))
{
	header('Location: ' . $rootpath . 'searchcat_viewcat.php');
	exit;
}

$message = $db->GetRow('SELECT m.*,
			u.letscode,
			u.id as uid,
			u.fullname as username,
			u.postcode,
			m.cdate AS date, 
			m.validity AS valdate,
			c.id as cid,
			c.fullname as catname
		FROM messages m, users u, categories c
		WHERE m.id = ' . $msgid . '
			AND m.id_user = u.id
			AND c.id = m.id_category');

$user = readuser($message['id_user']);
$title = $message["content"];

$contact = get_contact($user['id']);

$mailuser = get_user_maildetails($user['id']);
$usermail = $mailuser['emailaddress'];

$balance = $user["saldo"];

if ($_POST['zend'])
{
	$content = $_POST["content"];
	$cc = $_POST["cc"];

	$systemtag = readconfigfromdb("systemtag");

	$me = readuser($s_id);

	$my_contact = get_contact($s_id);
	$my_mail = get_user_maildetails($s_id);
	$mailfrom = $my_mail['emailaddress'];

    $mailsubject = "[eLAS-".$systemtag ."] - Reactie op je V/A " .$message["content"];

	if($cc){
		$mailto =  $mailuser["emailaddress"] ."," .$my_mail["emailaddress"];
	} else {
		$mailto =  $mailuser["emailaddress"];
	}

	$mailcontent = "Beste " .$user["fullname"] ."\r\n\n";
	$mailcontent .= "-- " .$me["fullname"] ." heeft een reactie op je vraag/aanbod verstuurd via eLAS --\r\n\n";
	$mailcontent .= "$content\n\n";

	$mailcontent .= "* Om te antwoorden kan je gewoon reply kiezen of de contactgegevens hieronder gebruiken\n";
	$mailcontent .= "* Contactgegevens van ".$me["fullname"] .":\n";

	foreach($my_contact as $key => $value){
		$mailcontent .= "* " .$value["abbrev"] ."\t" .$value["value"] ."\n";
	}

	if ($content)
	{
		$mailstatus = sendemail($mailfrom, $mailto, $mailsubject, $mailcontent, 1);

		if ($mailstatus)
		{
			$alert->error($mailstatus);
		}
		else
		{
			$alert->success('Mail verzonden.');
			$content = '';
		}
	}
	else
	{
		$alert->error('Fout: leeg bericht. Mail niet verzonden.');
	}
}

include($rootpath."includes/inc_header.php");

if (in_array($s_accountrole, array('admin', 'user')))
{
	echo "<table width='100%' border=0><tr><td>";
	echo "<div id='navcontainer'>";
	echo "<ul class='hormenu'>";
	echo '<li><a href="' . $rootpath . 'messages/edit.php?mode=new">Vraag/Aanbod toevoegen</a></li>';
	echo "</ul>";
	echo "</div>";
	echo "</td></tr></table>";
}

echo "<script type='text/javascript' src='". $rootpath ."js/msgpicture.js'></script>";
echo "<table class='data' border='1' width='95%'>";
echo "<tr>";

// The picture table is nested
echo "<td valign='top'>";

$msgpictures = get_msgpictures($msgid);

echo "<table class='data' border='1'>";
echo "<tr><td colspan='4' align='center'><img id='mainimg' src='" .$rootpath ."gfx/nomsg.png' width='300'></img></td></tr>";
echo "<tr>";
$picturecounter = 1;
foreach($msgpictures as $key => $value){
	$file = $value["PictureFile"];
//  $url = $rootpath ."/sites/" .$dirbase ."/msgpictures/" .$file;
	$url = 'https://s3.eu-central-1.amazonaws.com/' . getenv('S3_BUCKET') . '/' . $file;
	echo "<td>";
	if($picturecounter == 1)
	{
		 echo "<script type='text/javascript'>loadpic('$url')</script>";
	}
	if ($picturecounter <= 4)
	{
		$picurl = "showpicture.php?id=" . $value["id"];
		echo "<img src='" . $url . "' width='50' onmouseover=loadpic('$url') onclick=window.open('$picurl', 'Foto','width=800,height=600,scrollbars=yes,toolbar=no,location=no') style='cursor:pointer;'></td>";
	}
	$picturecounter += 1;
}
echo "</tr>";

echo "</td>";
echo "</table>";

echo "</td>";
// end picture table

// Show message
echo "<td valign='top'>";
show_msg($message, $balance);
echo "</td>";
// End message

echo "</tr>";

echo "<tr>";

//Contact info goes here
echo "<td width='254' valign='top'>";
show_contact($contact);
echo "</td>";
//End contact info

//Response form
echo "<td>";

// response form
echo "<div id='responseformdiv'>";
echo "<table border='0'>";
echo "<tr><td colspan='2'>";
echo "<form method='post'>";
echo "<INPUT TYPE='hidden' id='myid' VALUE='" .$msgid ."'>";
echo "<TEXTAREA NAME='content' id='reactie' COLS='60' ROWS='6' placeholder='Je reactie naar de aanbieder' required";
if(empty($usermail) || $s_accountrole == 'guest'){
	echo " DISABLED";
}
echo ">" . $content . "</TEXTAREA>";
echo "</td></tr><tr><td>";
echo "<input type='checkbox' name='cc' id='cc'";
echo ($cc) ? ' checked="checked"' : '';
echo " value='1' >Stuur een kopie naar mijzelf";
echo "</td><td>";
echo "<input type='submit' name='zend' id='zend' value='Versturen'";
if(empty($usermail) || $s_accountrole == 'guest'){
			echo "DISABLED";
	}
echo ">";
echo "</form>";
echo "</td></tr>";
echo "</table>";
//echo "</form>";
echo "</div>";

echo "</td>";
//End response form

echo "</tr>";
echo "</table>";

if($s_accountrole == "admin" || $s_id == $user['id']){
	show_editlinks($msgid);
}

////////////////////////////////////////////////////////////////////////////

function show_editlinks($msgid)
{
	global $rootpath;
	echo "<table width='100%' border=0><tr><td>";
	echo "<div id='navcontainer'>";
	echo "<ul class='hormenu'>";
	echo '<li><a href="' . $rootpath . 'messages/edit.php?id=' . $msgid . '&mode=edit">Aanpassen</a></li>';
	$myurl = "upload_picture.php?msgid=$msgid";
	echo "<script type='text/javascript'>function AddPic () { OpenTBox('" . $myurl ."'); } </script>";
    echo "<li><a href='javascript: AddPic()'>Foto toevoegen</a></li>";
	$myurl="";
	echo '<li><a href="' . $rootpath . 'messages/delete.php?id=' . $msgid . '">Verwijderen</a></li>';
	echo "</ul>";
	echo "</div>";
	echo "</td></tr></table>";
}

function get_msgpictures($id){
	global $db;
	$query = "SELECT * FROM msgpictures WHERE msgid = " .$id;
	$msgpictures = $db->GetArray($query);
        return $msgpictures;
}

function show_balance($balance,$currency){
	echo "<table cellpadding='0' cellspacing='0' border='0' width='99%'>";
	echo "<tr class='even_row'><td>";
	echo "<strong>{$currency}stand</strong></td></tr>";
	echo "<tr ><td>";
	echo $balance;
	echo "<br><br>";
	echo "</td></tr></table>";
}


function show_msg($message, $balance)
{
	global $baseurl, $msgid, $rootpath;

	$currency = readconfigfromdb("currency");
	echo "<table cellspacing='0' cellpadding='0' border='0' width='100%'>";
	echo "<tr class='even_row'><td>";
	echo "<p><strong><font size='+1'><i>";
	if($message["msg_type"]==0){
		echo "Vraag:  ";
	}elseif($message["msg_type"]==1){
		echo "Aanbod: ";
	}
	echo "</i>";
	//echo htmlspecialchars($message["name"],ENT_QUOTES)." (" .trim($message["letscode"])."): ".$message["content"];
	echo htmlspecialchars($message["content"]);
	echo "</font></strong><br>Van: ";
	echo '<a href="' . $rootpath . 'memberlist_view.php?id=' . $message['id_user'] . '">';
	echo htmlspecialchars($message["username"],ENT_QUOTES) ."  " .trim($message["letscode"]);
	echo "</a><i> Saldo-stand: " .$balance ." " .$currency ."</i>";
	echo '<br>Plaats: ' . $message['postcode'];
	echo "</td></tr>";
	echo '<tr class="even_row"><td>Categorie: ';
	echo '<a href="' . $rootpath . 'searchcat_viewcat.php?id=' . $message['cid'] . '">';
	echo htmlspecialchars($message['catname'], ENT_QUOTES) . '</a></td></tr>';
	echo "<tr><td>";
	if (!empty($message["Description"])){
		echo nl2br(htmlspecialchars($message['Description'],ENT_QUOTES));
	} else {
		echo "<i>Er werd geen omschrijving ingegeven</i>";
	}
	echo "</td></tr>";

        echo "<tr><td>&nbsp</td></tr>";

	echo "<tr><td>Aangemaakt op: " .$message["date"]."<tr><td>";
	echo "<tr><td>Geldig tot: " .$message["valdate"]."<tr><td>";

	echo "<tr><td>&nbsp</td></tr>";

	echo "<tr class='even_row'><td valign='bottom'>";
	if (!empty($message["amount"])){
		echo "De (vraag)prijs is " .$message["amount"] ." " .$currency;
		echo ($message['units']) ? ' per ' . $message['units'] : '';
	} else {
		echo "Er werd geen (vraag)prijs ingegeven";
	}
	echo "</td></tr>";

	echo "</table>";

}

function show_user($user){
	echo "<table cellspacing='0' cellpadding='0' border='0'>";
	echo "<tr><td>Postcode: </td>";
	echo "<td>".$user["postcode"]."</td></tr>";
	echo "<tr><td colspan='2'><p>&#160;</p></td></tr>";
	echo "</table>";
}

function show_title($title){
	echo "<h1>$title</h1>";
}

function show_contact($contact){
	echo "<table cellpadding='0' cellspacing='0' border='0' width='100%'>";
	echo "<tr class='even_row'><td colspan='3'><p><strong>Contactinfo</strong></p></td></tr>";
	foreach($contact as $key => $value){
		echo "<tr><td>".$value["abbrev"].": </td>";
			if($value["abbrev"] == "mail"){
					echo "<td><a href='mailto:".$value["value"]."'>".$value["value"]."</a></td>";
			}elseif($value["abbrev"] == "adr"){
					echo "<td><a href='http://maps.google.be/maps?f=q&source=s_q&hl=nl&geocode=&q=".$value["value"]."' target='new'>".$value["value"]."</a></td>";
			} else {
					echo "<td>".$value["value"]."</td>";
			}
		echo "<td></td>";
		echo "</tr>";
	}
	echo "</table>";
}

include($rootpath."includes/inc_footer.php");

