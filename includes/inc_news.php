<?php
/**
 * Class to perform eLAS transactions
 *
 * This file is part of eLAS http://elas.vsbnet.be
 *
 * Copyright(C) 2009 Guy Van Sanden <guy@vsbnet.be>
 *
 * eLAS is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 3
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
*/

/** Provided functions:
 * mail_news($id)
*/

// Enable logging
global $rootpath;
require_once($rootpath."includes/inc_mailfunctions.php");
require_once($rootpath."includes/inc_amq.php");

function mail_news($id){
	global $db;
	$query = "SELECT *, ";
	$query .= "news.id AS nid, ";
	$query .= " news.cdate AS date, ";
	$query .= " news.itemdate AS idate ";
	$query .= " FROM news, users  ";
	$query .= " WHERE news.id=".$id;
	$query .= " AND news.id_user = users.id ";
	$newsitem = $db->GetRow($query);

	$mailfrom = readconfigfromdb("from_address");
	$systemname = readconfigfromdb("systemname");
	$systemtag = readconfigfromdb("systemtag");

	$mailsubject = $newsitem["headline"];

	$mailcontent  = "-- Dit is een automatische mail van het eLAS systeem, niet beantwoorden aub --<br><br>\r\n\n";

	$mailcontent  .= "Er werd een nieuw nieuwsbericht ingegeven in eLAS:\n<br><br>";
	$mailcontent  .= "Onderwerp: " .$newsitem["headline"] ."\n<br>";
	$mailcontent  .= "Locatie " .$newsitem["location"] ."\n<br>";
	$mailcontent  .= "Datum: " .$newsitem["itemdate"] ."\n\n<br><br>";
	$mailcontent  .= $newsitem["newsitem"] ."\n\n<br><br>";

	$q2 = "SELECT * from lists where topic = 'news'";
	$lists = $db->Execute($q2);
	//var_dump($lists);

	foreach($lists as $key => $value){
		amq_sendmail($value["listname"], $mailsubject, $mailcontent, 0);
	}
}

?>
