<?php
// Fix IE's utter and complete brokenness!!!
header('X-UA-Compatible: IE=EmulateIE8');

// Make sure we serve UTF-8
header("Content-Type:text/html;charset=utf-8");
?>

<!DOCTYPE HTML PUBLIC '-//W3C//DTD HTML 4.01 Transitional//EN'>
<html>
<head>
	<title><?php echo $configuration["system"]["systemname"] ?></title>
		<?php
			echo "<link type='text/css' rel='stylesheet' href='".$rootpath."gfx/legacy.css'>";
			echo "<link type='text/css' rel='stylesheet' href='".$rootpath."gfx/menu.css'>";
			echo "<script type='text/javascript' src='/js/ajax.js'></script>";
			echo "<script type='text/javascript' src='/js/mootools-core.js'></script>\n";
			echo "<script type='text/javascript' src='/js/mootools-more.js'></script>\n";
		?>
</head>

<body>

<div align='center'> 
<table id='maintable' cellspacing='0' cellpadding='0' border='0' width='95%' class='main'>
	<tr>
		<td valign='top' class='logo' colspan='2'>
			
		</td>
	</tr>
	
	
	<tr>
	
