<?php
header("Content-Type:text/html;charset=utf-8");
?>
<!DOCTYPE html>
<html>
<head>
	<title><?php echo readconfigfromdb("systemname"); ?></title>
		<?php
			echo "<link type='text/css' rel='stylesheet' href='".$rootpath."gfx/main.css'>\n";
			echo "<link type='text/css' rel='stylesheet' href='".$rootpath."gfx/layout.css'>\n";
			echo "<link type='text/css' rel='stylesheet' href='".$rootpath."gfx/menu.css'>\n";
			echo "<link type='text/css' rel='stylesheet' href='".$rootpath."gfx/floatingcolumns.css'>\n";
			echo "<link type='text/css' rel='stylesheet' href='".$rootpath."growler/growler.css'>\n";
			echo "<link type='text/css' rel='stylesheet' href='".$rootpath."tinybox/tinybox.css'>\n";

			//ajax.js contains eLAS custom ajax fucntions that are being migrated to MooTools
			echo "<script type='text/javascript' src='/js/ajax.js'></script>\n";

			echo "<script type='text/javascript' src='/js/mootools-core.js'></script>\n";
			echo "<script type='text/javascript' src='/js/mootools-more.js'></script>\n";
			echo "<script type='text/javascript' src='/growler/growler.js'></script>\n";
			echo "<script type='text/javascript' src='/js/notify.js'></script>\n";
			echo "<script type='text/javascript' src='/tinybox/tinybox.js'></script>\n";

			echo "<link rel='alternate' type='application/rss+xml' title='Messages RSS' href='$rootpath/rss.php?feed=messages' />\n";
			echo "<link rel='alternate' type='application/rss+xml' title='News RSS' href='$rootpath/rss.php?feed=news' />\n";
		?>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
</head>
<body>

<script type='text/javascript'>
		var Growl = new Growler.init();
		// Growl.notify('Testing 123');
</script>

<script type='text/javascript'>
	function OpenTBox(url){
		TINY.box.show({url:url,width:0,height:0})
	}
</script>

<div id="wrapper">
 <div id="header">
  <div id="logo"></div><div id="groupname">
  <?php
	$name = readconfigfromdb("systemname");
	echo $name;
  ?>
  </div>
 </div>
 <div id="main">
  <div id="menu">
	<?php

		if(isset($s_id)){
			if($s_accountrole == "user" || "admin"){
	?>
			<div class='nav'>
		 	<ul class="vertmenu">
			<?php
				$name = readconfigfromdb("systemname");
				//echo "<li><a href='".$rootpath."index.php'>Startscherm</a></li>";
				echo "<li><a href='".$rootpath."index.php'>$name</a></li>";
				if($s_accountrole == "user" || $s_accountrole == "admin"){
					$myurl = $rootpath."ircchat.php";
					echo "<li><a href='#' onclick=\"javascript:window.open('$myurl','chatbox','width=800,height=600,scrollbars=yes,toolbar=no,location=no,menubar=no')\">Chatbox #letsbe</a></li>";
				}
			?>
			</ul>
		</div>

		<div class='nav'>
			<!-- <span class='nav'>Algemeen</span><br> -->
			<ul class="vertmenu">
			<?php
				echo "<li><a href='".$rootpath."searchcat.php'>Vraag & Aanbod</a></li>";
				echo "<li><a href='".$rootpath."memberlist.php'>Contactlijst</a></li>";
				if($s_accountrole == "user" || $s_accountrole == "admin" || $s_accountrole == "interlets"){
					echo "<li><a href='".$rootpath."transactions/alltrans.php'>Transacties</a></li>";
				}
				echo "<li><a href='".$rootpath."news.php'>Nieuws</a></li>";
				if($s_accountrole == "user" || $s_accountrole == "admin"){
					echo "<li><a href='".$rootpath."interlets/userview.php'>Interlets</a></li>";
				}
			?>
			</ul>
		</div>
		<div class='nav'>
			<!--<span class='nav'>Persoonlijk</span><br>-->
			<ul class="vertmenu">
			<?php
				if($s_accountrole == "user" || $s_accountrole == "admin"){
					echo "<li><a href='".$rootpath."userdetails/mydetails.php'>Mijn gegevens</a></li>";
 					echo "<li><a href='".$rootpath."userdetails/mymsg_overview.php'>";
					echo "Mijn Vraag & Aanbod</a></li>";
				}
				if($s_accountrole == "user" || $s_accountrole == "admin" || $s_accountrole == "interlets"){
					echo "<li><a href='".$rootpath."userdetails/mytrans_overview.php'>";
					echo "Mijn transacties</a></li>";
				}
				if($s_accountrole == "user" || $s_accountrole == "admin"){
					$myurl = $rootpath."transactions/add.php";
					echo "<li><a href='#' onclick=\"javascript:window.open('$myurl','Transactie','width=600,height=700,scrollbars=yes,toolbar=no,location=no,menubar=no')\">Nieuwe transactie</a></li>";
                                }

			?>
			</ul>
		</div>
		<div class='nav'>
			<ul class='vertmenu'>
			<?php
				if($s_accountrole == "user" || $s_accountrole == "admin"){
					$myurl = $rootpath."help.php";
					echo "<li><a href='#' onclick=\"javascript:window.open('$myurl','help','width=700,height=640,scrollbars=no,toolbar=no,location=no,menubar=no')\">Probleem melden</a></li>";
				}
			?>
			</ul>
               </div>

	<?php
	}
	if($s_accountrole == "admin"){
	?>
		<div class='nav'>
			<!--<span class='nav'>Beheer</span><br>-->
			<ul class='vertmenu'>
			<?php
				echo "<li><a href='".$rootpath."users/overview.php?user_orderby=letscode'>Gebruikers</a></li>";
				echo "<li><a href='".$rootpath."categories/overview.php'>Categorien</a></li>";
				echo "<li><a href='".$rootpath."interlets/overview.php'>LETS Groepen</a></li>";
				echo "<li><a href='".$rootpath."apikeys/overview.php'>Apikeys</a></li>";
/*				if(readconfigfromdb("mailinglists_enabled") == 1) {
					echo "<li><a href='".$rootpath."lists/overview.php'>Mailinglists</a></li>";
				} */
				echo "<li><a href='".$rootpath."type_contact/overview.php'>Contacttypes</a></li>";
				echo "<li><a href='".$rootpath."messages/overview.php'>Vraag & Aanbod</a></li>";
				echo "<li><a href='".$rootpath."reports/overview.php'>Rapporten</a></li>";
				echo "<li><a href='".$rootpath."preferences/config.php'>Instellingen</a></li>";
				echo "<li><a href='".$rootpath."importexport.php'>Import/Export</a></li>";
				echo "<li><a href='".$rootpath."eventlog.php'>Log</a></li>";
				echo "<li><a href='".$rootpath."transactions/many_to_one.php'>Massa-Transactie</a></li>";
			?>
			</ul>
		</div>

	<?php
		}
	}elseif($ptitle == "login"){
		echo "<ul class='vertmenu'>";
		echo "<li><a href='#' id='showlostpasswordform'>Login/Passwoord vergeten</a></li>";
		//echo "<li><a href='#' id='showguestloginform'>" .$tr->get('guestlogin','nav') ."</a></li>";
		$myurl = $rootpath."help.php";
		echo "<li><a href='$myurl'>Help</a></li>";
		echo "</ul>";
	}
	?>
  </div>
  <div id="content">
  <div id='log'><div id='log_res'></div></div>
