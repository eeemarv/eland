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

feedheader();
icalfeed();
feedfooter();

////////////////////////////////////////////////////////////////////////////
////////////////////////////////F U N C T I E S ////////////////////////////
////////////////////////////////////////////////////////////////////////////

function feedheader(){
	global $elasversion;
	global $baseurl;

	header("Content-Type: text/Calendar");
	header("Content-Disposition: inline; filename=calendar.ics");
	//echo "<pre>";

	echo "BEGIN:VCALENDAR\nVERSION:2.0\nMETHOD:PUBLISH\nX-WR-CALNAME: ";
	echo readconfigfromdb("systemtag");
	echo "\nPRODID:-//eLAS v" .$elasversion ." iCAL API//EN\n";
}

function feedfooter(){
	echo "END:VCALENDAR";
}

function icalfeed(){
	global $rootpath;
	global $baseurl;

        $now = date("D, d M Y H:i:s T");
        $site = readconfigfromdb("systemname");
        $newsitems= get_all_newsitems();

	if(!empty($newsitems)){
	        foreach($newsitems as $key => $value){
        	        $mylink="http://$baseurl/news/view.php?id=" . $value['nid'];
                	$mycont = htmlentities(iconv('UTF-8', 'US-ASCII//TRANSLIT',$value['headline']));
	                $mydesc = htmlentities(iconv('UTF-8', 'US-ASCII//TRANSLIT',$value['newsitem']));
        	        //$output .= "<item><title>".$mycont."</title>
                	//            <link>".htmlentities($mylink)."</link>
                        //	    <description>". $mydesc ."</description>
	                //        </item>";
			echo "BEGIN:VEVENT\n";
			//echo "\n--" .$value["itemdate"] ."--\n";
			echo "DTSTART;VALUE=DATE-TIME:" .date("Ymd\THi00", strtotime($value["itemdate"])) ."\n";
			//echo "DTEND;VALUE=DATE-TIME:";
			echo "UID:$mylink\n";
			echo "URL;VALUE=URI:$mylink\n";
			echo "SUMMARY:$mycont\n";
			echo "DESCRIPTION:$mydesc\n";
			echo "\n";
			echo "END:VEVENT\n";
        	}
	}

}

function get_all_newsitems(){
        global $db;
        $query = "SELECT *, ";
        $query .= "news.id AS nid, ";
        $query .= " news.cdate AS date ";
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
