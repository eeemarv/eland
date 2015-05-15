<?php
header("Content-Type:text/html;charset=utf-8");
echo '<!DOCTYPE html>';
echo '<html>';
echo '<head>';
echo '<title>' . readconfigfromdb("systemname") .'</title>';

echo "<link type='text/css' rel='stylesheet' href='".$rootpath."gfx/main.css'>";

echo "<link type='text/css' rel='stylesheet' href='".$rootpath."gfx/layout.css'>";
echo "<link type='text/css' rel='stylesheet' href='".$rootpath."gfx/menu.css'>";
echo "<link type='text/css' rel='stylesheet' href='".$rootpath."tinybox/tinybox.css'>";

// echo "<link type='text/css' rel='stylesheet' href='".$rootpath."gfx/alert.css'>";

echo '<link type="text/css" rel="stylesheet" href="' . $cdn_bootstrap_css . '">';
echo "<link type='text/css' rel='stylesheet' href='" . $rootpath . "gfx/base.css'>";

echo "<script type='text/javascript' src='/tinybox/tinybox.js'></script>";

if (isset ($includecss))
{
	echo $includecss;
}

echo "<link rel='alternate' type='application/rss+xml' title='Messages RSS' href='$rootpath/rss.php?feed=messages' />";
echo "<link rel='alternate' type='application/rss+xml' title='News RSS' href='$rootpath/rss.php?feed=news' />";

echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
echo '</head>';
echo '<body>';

echo '<div class="page-container">';

?>
<script type='text/javascript'>
	function OpenTBox(url){
		TINY.box.show({url:url,width:0,height:0})
	}
</script>

<?php
$name = readconfigfromdb('systemname');

if ($role == 'anonymous')
{
	$menu = array(
		array(
			array('login.php', 'Login'),
			array('help.php', 'Help'),
		),
	);
}
else
{
	$menu = array(
		array(
			'user',
			array('ircchat.php', 'Chatbox #letsbe'),
		),
		array(
			'guest',
			array('searchcat.php', 'Vraag & Aanbod'),
			array('memberlist.php', 'Contactlijst'),
			array('transactions/alltrans.php', 'Transacties', 'user'),
			array('news/overview.php', 'Nieuws'),
			array('interlets/userview.php', 'Interlets', 'user'),
		),
		array(
			'user',
			array('userdetails/mydetails.php', 'Mijn gegevens'),
			array('userdetails/mymsg_overview.php', 'Mijn Vraag & Aanbod'),
			array('userdetails/mytrans_overview.php', 'Mijn transacties'),
			array('transactions/add.php', 'Nieuwe transactie'),
		),
		array(
			'user',
			array('help.php', 'Probleem melden'),
		),
		array(
			'admin',
			array('users/overview.php?user_orderby=letscode', 'Gebruikers'),
			array('categories/overview.php', 'Categorieën'),
			array('interlets/overview.php', 'LETS Groepen'),
			array('apikeys/overview.php', 'Apikeys'),
			array('type_contact/overview.php', 'Contacttypes'),
			array('messages/overview.php', 'Vraag & Aanbod'),
			array('reports/overview.php', 'Rapporten'),
			array('preferences/config.php', 'Instellingen'),
			array('importexport.php', 'Import/Export'),
			array('eventlog.php', 'Logs'),
			array('transactions/many_to_one.php', 'Massa-Transactie'),
		),
	);
}

echo '<div class="navbar navbar-default navbar-fixed-top">';
echo '<div class="navbar-header">';
echo '<a class="navbar-brand" href="#">';
echo '<img class="img-responsive navbar-left hidden-xs" width="70" src="' . $rootpath . 'gfx/logo.png">';
echo $name . '</a>';
echo '</div>';
echo '</div>';

echo '
<div class="row-offcanvas row-offcanvas-left">
  <div id="sidebar" class="sidebar-offcanvas">';
echo'<div class="col-md-12">';

/*
$open = $close = '';

foreach ($menu as $group)
{
	$group_role = 'anonymous';

	$open = '<div class="btn-group-vertical form-control" role="group">';

	foreach ($group as $item)
	{
		if (is_string($item))
		{
			$group_role = $item;
			continue;
		}

		echo $close . $open;
		$close = $open = '';

		$item_role = ($item[2]) ?: $group_role;

		switch ($item_role)
		{
			case 'admin':
				if ($role == 'user')
				{
					break;
				}
			case 'user':
				if ($role == 'guest')
				{
					break;
				}
			case 'guest':
				if ($role == 'anonymous')
				{
					break;
				}
			case 'anonymous':
				
				echo '<a class="btn btn-default btn-block btn-elas btn-active" href="' . $rootpath . $item[0] . '">' . $item[1] . '</a>';

			default:
				break;
		}
	}

	$close = '</div>';
}
echo '</div>';

*/

echo'
		<ul class="nav nav-pills nav-stacked">
		  <li><a>Chatbox #letsbe</a></li>
		  <li><a>2</a></li>
		</ul>';



echo '</div>';
echo '</div>

  <div id="main">
      <div class="col-md-12">
      	  <p class="visible-xs">
            <button type="button" class="btn btn-primary btn-xs" data-toggle="offcanvas"><i class="glyphicon glyphicon-chevron-left"></i></button>
          </p>';

          
/*
echo '<div class="row">';
echo '<div onclick="window.location=\'' . $rootpath . 'index.php\'" class="col-xs-12">';

echo '<div id="logo"></div><div id="groupname">';

$name = readconfigfromdb('systemname');
echo $name;

echo '</div>';
echo '</div>';
echo '</div>';
*/

/*
echo '<div class="row row-offcanvas row-offcanvas-left">';
echo '<div class="col-xs-6 col-sm-3 sidebar-offcanvas" id="sidebar" role="navigation">';

if(isset($s_id)){
	if($s_accountrole == "user" || "admin"){
	?>
			<div class='nav'>
		 	<ul class="vertmenu">
			<?php
				$name = readconfigfromdb("systemname");
				echo "<li><a href='".$rootpath."index.php'>$name</a></li>";
				if($s_accountrole == "user" || $s_accountrole == "admin"){
					echo "<li><a href='" . $rootpath . "ircchat.php' >Chatbox #letsbe</a></li>";
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
				if($s_accountrole == "user" || $s_accountrole == "admin"){
					echo "<li><a href='".$rootpath."transactions/alltrans.php'>Transacties</a></li>";
				}
				echo "<li><a href='".$rootpath."news/overview.php'>Nieuws</a></li>";
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
					echo "<li><a href='".$rootpath."transactions/add.php'>Nieuwe transactie</a></li>";
                                }

			?>
			</ul>
		</div>
		<div class='nav'>
			<ul class='vertmenu'>
			<?php
				if($s_accountrole == "user" || $s_accountrole == "admin"){

					echo '<li><a href="' . $rootpath . 'help.php">Probleem melden</a></li>';
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
				} 
				echo "<li><a href='".$rootpath."type_contact/overview.php'>Contacttypes</a></li>";
				echo "<li><a href='".$rootpath."messages/overview.php'>Vraag & Aanbod</a></li>";
				echo "<li><a href='".$rootpath."reports/overview.php'>Rapporten</a></li>";
				echo "<li><a href='".$rootpath."preferences/config.php'>Instellingen</a></li>";
				echo "<li><a href='".$rootpath."importexport.php'>Import/Export</a></li>";
				echo "<li><a href='".$rootpath."eventlog.php'>Logs</a></li>";
				echo "<li><a href='".$rootpath."transactions/many_to_one.php'>Massa-Transactie</a></li>";
			?>
			</ul>
		</div>

	<?php
		}
	}
	else if ($role == 'anonymous')
	{
		echo "<ul class='vertmenu'>";
		echo '<li><a href="' . $rootpath . 'login.php">Login</a></li>';		
		echo '<li><a href="' . $rootpath . 'pwreset.php">Login of Paswoord vergeten</a></li>';
		echo '<li><a href="' . $rootpath . 'help.php">Help</a></li>';
		echo "</ul>";
	}

echo '</div>';
echo '<div class="col-xs-12 col-sm-9">';


echo '<div id="log"><div id="log_res">';
*/

echo '<div>';
$alert->render();
echo '</div>';
echo ($role == 'admin') ? '<p>[admin]</p>' : '';


// echo '</div></div>';

