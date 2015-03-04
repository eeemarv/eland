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
$ptitle="home1";
// get the initial includes
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

$s_id = $_SESSION["id"];
$s_name = $_SESSION["name"];
$s_letscode = $_SESSION["letscode"];
$s_accountrole = $_SESSION["accountrole"];

include($rootpath."includes/inc_header.php");
echo "<script type='text/javascript' src='$rootpath/js/mooindex.js'></script>";

if(isset($s_id)){
	show_outputdiv();
}else{
	//var_dump($_SESSION);
	redirect_login($rootpath);
}

////////////////////////////////////////////////////////////////////////////
////////////////////////////////F U N C T I E S ////////////////////////////
////////////////////////////////////////////////////////////////////////////

function show_outputdiv(){
        echo "<div id='output'>";
        //echo "<script type=\"text/javascript\">loadurl('renderindex.php')</script>";
        echo "</div>";
}

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");

}

include($rootpath."includes/inc_footer.php");
?>
