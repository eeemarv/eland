<?php

// Copyright(C) 2009 Guy Van Sanden <guy@vsbnet.be>
//
//    This program is free software: you can redistribute it and/or modify
//    it under the terms of the GNU General Public License as published by
//    the Free Software Foundation, either version 3 of the License, or
//    (at your option) any later version.
//
//    This program is distributed in the hope that it will be useful,
//    but WITHOUT ANY WARRANTY; without even the implied warranty of
//    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//    GNU General Public License for more details.
//
//    You should have received a copy of the GNU General Public License
//    along with this program.  If not, see <http://www.gnu.org/licenses/>.
 
ob_start();
$rootpath = "./";
// get the initial includes
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

$feed = $_GET["feed"];
switch($feed){
        case "messages":
                messagefeed();
                break;
        case "news":
                newsfeed();
                break;
}


////////////////////////////////////////////////////////////////////////////
////////////////////////////////F U N C T I E S ////////////////////////////
////////////////////////////////////////////////////////////////////////////

function messagefeed(){	
	global $rootpath;
	global $baseurl;
	$now = date("D, d M Y H:i:s T");
	$site = readconfigfromdb("systemname");
	$messages = get_all_msgs();
	$output = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
            <rss version=\"2.0\" xmlns:atom=\"http://www.w3.org/2005/Atom\">
                <channel>
                    <title>eLAS messages $site</title>
                    <link>http://$baseurl/rss.php</link>
                    <description>Laaste nieuw eLAS V/A $site</description>
		    <atom:link href=\"http://$baseurl/rss.php?feed=messages\" rel=\"self\" type=\"application/rss+xml\" />

            ";

	if(!empty($messages)){
		foreach($messages as $key => $value){
			$mylink="http://$baseurl/login.php?redirectmsg=" . $value['msgid'];
			if($value["msg_type"]==0){
                		$mytype = "Vraag";
	        	}else {
        	        	$mytype = "Aanbod";
        		}

			$mycont = "[" .$mytype ."] " .htmlentities(iconv('UTF-8', 'US-ASCII//TRANSLIT',$value['content']));
			$mydesc = htmlentities(iconv('UTF-8', 'US-ASCII//TRANSLIT',$value['Description']));
			if(empty($value['mdate'])) {
				$pubdate =  date("D, d M Y H:i:s O", strtotime($value['cdate']));
			} else {
				$pubdate =  date("D, d M Y H:i:s O", strtotime($value['mdate']));
			}
			$output .= "<item><title>".$mycont."</title>
				    <pubDate>$pubdate</pubDate>
        	        	    <link>".htmlentities($mylink)."</link>
			    	<description>". $mydesc ."</description>
	                	</item>\n";
		}	
	}

		$output .= "</channel></rss>";
		header("Content-Type: application/rss+xml");
		echo $output;
}    

function newsfeed(){
	global $rootpath;
	global $baseurl;
        $now = date("D, d M Y H:i:s T");
        $site = readconfigfromdb("systemname");
        $newsitems= get_all_newsitems();
        $output = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
            <rss version=\"2.0\" xmlns:atom=\"http://www.w3.org/2005/Atom\">
                <channel>
                    <title>eLAS news $site</title>
                    <link>http://$baseurl/rss.php</link>
                    <description>Laaste nieuws in eLAS $site</description>
		    <atom:link href=\"http://$baseurl/rss.php?feed=news\" rel=\"self\" type=\"application/rss+xml\" />
            ";

	if(!empty($newsitems)){
	        foreach($newsitems as $key => $value){
        	        $mylink="http://$baseurl/news/view.php?id=" . $value['nid'];
                	$mycont = htmlentities(iconv('UTF-8', 'US-ASCII//TRANSLIT',$value['headline']));
	                $mydesc = htmlentities(iconv('UTF-8', 'US-ASCII//TRANSLIT',$value['newsitem']));
			##$output .= "==" . $value['headline'] ." = " .$value['cdate'] ."==";
			#if(empty($value['cdate'])){
				#$pubdate =  date("D, d M Y H:i:s O", strtotime($value['itemdate']));
			#} else {
				#$pubdate =  date("D, d M Y H:i:s O", strtotime($value['cdate']));
			#}
			$pubdate =  date("D, d M Y H:i:s O", strtotime($value['date']));
        	        $output .= "<item><title>".$mycont."</title>
				    <pubDate>$pubdate</pubDate>
                	            <link>".htmlentities($mylink)."</link>
                        	    <description>". $mydesc ."</description>
	                        </item>\n";
        	}
	}

        $output .= "</channel></rss>";
        header("Content-Type: application/rss+xml");
	echo $output;
}

function get_all_msgs(){
        global $db;
        $query = "SELECT *, ";
        $query .= " messages.id AS msgid, ";
        $query .= " messages.validity AS valdate, ";
        $query .= " users.id AS userid, ";
        $query .= " categories.id AS catid, ";
        $query .= " categories.name AS catname, ";
        $query .= " users.name AS username, ";
        $query .= " messages.cdate AS date ";
        $query .= " FROM messages, users, categories ";
        $query .= " WHERE messages.id_user = users.id";
        $query .= " AND messages.id_category = categories.id";
        $query .= " AND (users.status = 1 OR users.status = 2 OR users.status = 3) ";
        $query .= " ORDER BY messages.cdate DESC ";
        $query .= " LIMIT 50 ";
        $messagerows = $db->GetArray($query);
        return $messagerows;
}

function get_all_newsitems(){
        global $db;
        $query = "SELECT *, ";
        $query .= "news.id AS nid, ";
        $query .= " news.cdate AS date, ";
	$query .= " news.itemdate AS idate ";
        $query .= " FROM news, users ";
        $query .= " WHERE news.id_user = users.id AND approved = 1";
        if(news.itemdate != "0000-00-00 00:00:00"){
                                $query .= " ORDER BY news.itemdate DESC ";
        }else{
                                $query .= " ORDER BY news.cdate DESC ";
        }
        $query .= " LIMIT 50 ";
        $newsitems = $db->GetArray($query);
        if(!empty($newsitems)){
                return $newsitems;
        }
}

?>
