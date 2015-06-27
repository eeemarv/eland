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

    $mailsubject = "[eLAS-".$systemtag ."] - Reactie op je V/A " .$message['content'];

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

$msgpictures = get_msgpictures($msgid);
$currency = readconfigfromdb('currency');

$includejs = '<script src="' . $cdn_jssor_slider_mini_js . '"></script>
	<script src="' . $rootpath . 'js/msg_view.js"></script>';

$top_buttons = '';

if ($s_accountrole == 'user' || $s_accountrole == 'admin')
{
	$top_buttons .= '<a href="' . $rootpath . 'messages/edit.php?mode=new" class="btn btn-success"';
	$top_buttons .= ' title="Vraag of aanbod toevoegen"><i class="fa fa-plus"></i>';
	$top_buttons .= '<span class="hidden-xs hidden-sm"> Toevoegen</span></a>';

	if ($s_accountrole == 'admin' || $s_id == $message['uid'])
	{
		$top_buttons .= '<a href="' . $rootpath . 'messages/edit.php?mode=edit&id=' . $msgid . '" ';
		$top_buttons .= 'class="btn btn-primary"';
		$top_buttons .= ' title="Vraag of aanbod aanpassen"><i class="fa fa-pencil"></i>';
		$top_buttons .= '<span class="hidden-xs hidden-sm"> Aanpassen</span></a>';

		$top_buttons .= '<a href="' . $rootpath . 'messages/delete.php?id=' . $msgid . '" ';
		$top_buttons .= 'class="btn btn-danger"';
		$top_buttons .= ' title="Vraag of aanbod verwijderen"><i class="fa fa-times"></i>';
		$top_buttons .= '<span class="hidden-xs hidden-sm"> Verwijderen</span></a>';
	}

	if ($message['msg_type'] == 1 && $s_id != $message['uid'])
	{
		$top_buttons .= '<a href="' . $rootpath . 'transactions/add.php?mid=' . $msgid . '" class="btn btn-warning"';
		$top_buttons .= ' title="Transactie voor dit aanbod toevoegen"><i class="fa fa-exchange"></i>';
		$top_buttons .= '<span class="hidden-xs hidden-sm"> Transactie</span></a>';
	}
}

$top_buttons .= '<a href="' . $rootpath . 'searchcat.php" class="btn btn-default"';
$top_buttons .= ' title="Vraag of aanbod zoeken"><i class="fa fa-newspaper-o"></i>';
$top_buttons .= '<span class="hidden-xs hidden-sm"> Zoeken</span></a>';

$h1 = ($message['msg_type']) ? 'Aanbod' : 'Vraag';
$h1 .= ': ' . htmlspecialchars($message['content'], ENT_QUOTES);
$fa = 'newspaper-o';

include $rootpath.'includes/inc_header.php';

echo '<div class="row">';
echo '<div class="col-md-6 text-center">';
echo '<div class="col-lg-8 col-lg-offset-2 text-center">';
echo '<div id="slider1_container" style="position: relative; 
                top: 0px; left: 0px; width: 600px; height: 300px;">';
echo '<div u="slides" style="cursor: move; position: absolute;
                    overflow: hidden; left: 0px; top: 0px; width: 600px; height: 300px;">';

foreach ($msgpictures as $key => $value)
{
	$file = $value['PictureFile'];
	$url = 'https://s3.eu-central-1.amazonaws.com/' . getenv('S3_BUCKET') . '/' . $file;
	echo '<div><img u="image" src="' . $url . '" /></div>';
}

echo '</div>';

echo '<div u="navigator" class="jssorb01" style="bottom: 16px; right: 10px;">';
echo '<div u="prototype"></div>';
echo '</div>';

echo '<span u="arrowleft" class="jssora02l" style="top: 123px; left: 8px;"></span>';
echo '<span u="arrowright" class="jssora02r" style="top: 123px; right: 8px;"></span>';

echo '</div></div></div>';

echo '<div class="col-md-6">';

echo '<div class="panel panel-default">';
echo '<div class="panel-body">';

if (!empty($message['Description']))
{
	echo nl2br(htmlspecialchars($message['Description'],ENT_QUOTES));
}
else
{
	echo '<i>Er werd geen omschrijving ingegeven.</i>';
}

echo '</div>';
echo '</div>';

echo '<p>';

if (!empty($message['amount']))
{
	echo 'De (vraag)prijs is ' . $message['amount'] . ' ' . $currency;
	echo ($message['units']) ? ' per ' . $message['units'] : '';
}
else
{
	echo 'Er werd geen (vraag)prijs ingegeven.';
}

echo '</p>';

echo '<dl>';

echo '<dt>De </dt></dl>';

echo '</div>'; //col-md-6

echo '</div>'; //row




echo '<div class="row">';
echo '<div class="col-md-12">';
if (count($msgpictures))
{
	echo '<div id="slider1_container" style="display: none; position: relative; margin: 0 auto; width: 980px; height: 380px; overflow: hidden;">';
	echo '<div u="slides" style="cursor: move; position: absolute; overflow: hidden; left: 0px; top: 0px; width: 600px; height: 400px;">';

	foreach ($msgpictures as $key => $value)
	{
		$file = $value["PictureFile"];
		$url = 'https://s3.eu-central-1.amazonaws.com/' . getenv('S3_BUCKET') . '/' . $file;
		echo '<div><img u="image" src="' . $url . '" /></div>';
	}
	echo '</div>';
	echo '</div>';
}
else
{
	$av = ($message['msg_type']) ? 'deze vraag' : 'dit aanbod';
	echo '<p>Er zijn geen afbeeldingen voor ' . $av . '</p>';
}
echo '</div></div>';

echo '<div class="row">';
echo '<div class="col-xs-12>';

echo '<div class="panel">';
echo 'Omschrijving';
echo '</div>';

echo '<dl class="dl-horizontal">';
echo '<dt>Van gebruiker: ';
echo '</dt>';
echo '<dd>';
echo '<a href="' . $rootpath . 'memberlist_view.php?id=' . $message['id_user'] . '">';
echo htmlspecialchars($message["fullname"],ENT_QUOTES) ."  " .trim($message["letscode"]);
echo '</a>';
echo '</dd>';
echo '</dl>';

echo '<dl class="dl-horizontal">';
echo '<dt>Saldo stand: ';
echo '</dt>';
echo '<dd>';
echo $balance . ' ' .$currency;
echo '</dd>';
echo '</dl>';



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


echo '</div>';
echo '</div>';



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

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';


echo "<div id='responseformdiv'>";
echo "<table border='0'>";
echo "<tr><td colspan='2'>";
echo "<form method='post'>";
echo "<INPUT TYPE='hidden' id='myid' VALUE='" .$msgid ."'>";
echo "<TEXTAREA NAME='content' id='reactie' COLS='60' ROWS='6' placeholder='Je reactie naar de aanbieder' required";
if(empty($usermail) || $s_accountrole == 'guest')
{
	echo " DISABLED";
}
echo ">" . $content . "</TEXTAREA>";
echo "</td></tr><tr><td>";
echo "<input type='checkbox' name='cc' id='cc'";
echo (isset($cc)) ? ' checked="checked"' : '';
echo " value='1' >Stuur een kopie naar mijzelf";
echo "</td><td>";
echo "<input type='submit' name='zend' id='zend' value='Versturen'";
if(empty($usermail) || $s_accountrole == 'guest')
{
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

echo '</div>';
echo '</div>';

if($s_accountrole == "admin" || $s_id == $user['id']){
	show_editlinks($msgid);
}




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

function get_msgpictures($id)
{
	global $db;
    return $db->GetArray("SELECT * FROM msgpictures WHERE msgid = " .$id);
}

function show_balance($balance,$currency)
{
	echo "<table cellpadding='0' cellspacing='0' border='0' width='99%'>";
	echo "<tr class='even_row'><td>";
	echo "<strong>{$currency}stand</strong></td></tr>";
	echo "<tr ><td>";
	echo $balance;
	echo "<br><br>";
	echo "</td></tr></table>";
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

include $rootpath . 'includes/inc_footer.php';

