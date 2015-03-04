<?php
ob_start();
$rootpath = "../";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_userinfo.php");

session_start();
$s_id = $_SESSION["id"];
$s_name = $_SESSION["name"];
$s_letscode = $_SESSION["letscode"];
$s_accountrole = $_SESSION["accountrole"];

include($rootpath."includes/inc_header.php");
include($rootpath."includes/inc_nav.php");

if(isset($s_id)){
        if (isset($_POST["zend"])){
                $posted_list = array();
                $posted_list["msg_type"] = $_POST["msg_type"];
                $posted_list["id_category"] = $_POST["id_category"];
		$catname = get_cat_title($posted_list["id_category"]);
		$posted_list["prefix"] = $_POST["prefix"];
	} else {
		$posted_list["msg_type"] = 0;
		$posted_list["id_category"] = 0;
		$catname = "";
	}

	show_ptitle($catname, $posted_list["msg_type"]);
	$messagerows = get_all_msgs($posted_list);
	$cat_list = get_cats();
	show_all_msgs($messagerows, $s_accountrole, $cat_list);

        show_printversion($rootpath,$posted_list["msg_type"], $posted_list["id_category"]);
}else{
	redirect_login($rootpath);
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

function show_ptitle($catname, $type){
	if($type == 1){
		$htype = "Aanbod";
	} else {
		$htype = "Vraag";
	}
	echo "<h1>$htype voor $catname</h1>";
}

function show_printversion($rootpath, $msg_type, $id_category){
        echo "<a href='print_messages.php?msg_type=";
        echo $msg_type;
        echo "&id_category=";
        echo $id_category;
        echo "' target='new'>";
        echo "<img src='".$rootpath."gfx/print.gif' border='0'> ";
        echo "Printversie</a>";
}

function get_cats(){
        global $db;
        $query = "SELECT * FROM categories WHERE leafnote = 1 ORDER BY fullname";
        $list_cats = $db->GetArray($query);
        return $list_cats;
}

function get_cat_title($cat_id){
	global $db;
	$query = "SELECT fullname FROM categories WHERE id = $cat_id";
	$cat = $db->GetRow($query);
	$catname = $cat["fullname"];
        return $catname;
}

function chop_string($content, $maxsize){
$strlength = strlen($content);
    if ($strlength >= $maxsize){
        $spacechar = strpos($content," ", 50);
        if($spacechar == 0){
            return $content;
        }else{
            return substr($content,0,$spacechar);
        }
    }else{
        return $content;
    }
}

function show_all_msgs($messagerows, $s_accountrole, $cat_list){
	//Selection form
        echo "<div class='border_b'>";
        echo "<form method='POST' action='messages.php'>\n";
        echo "<table class='data' cellspacing='0' cellpadding='0' border='0'>\n\n";
        echo "<tr>\n<td valign='top' align='right'>V/A </td>\n";
        echo "<td>";
        echo "<select name='msg_type'>\n";
        if($posted_list["msg_type"] == 0 ){
                echo "<option value='0' SELECTED >Vraag</option>\n";
        }else{
                echo "<option value='0' >Vraag</option>\n";
        }
        if($posted_list["msg_type"] == 1 ){
                echo "<option value='1' SELECTED >Aanbod</option>\n";
        }else{
                echo "<option value='1' >Aanbod</option>\n";
        }
        echo "</select>\n";
	echo "</td>\n</tr>\n\n";

	#Add subgroup selection
        echo "<tr>\n<td>";
        echo "Subgroep:";
        echo "</td><td>\n";
        echo "<select name='prefix'>\n";

	echo "<option value='ALL'>ALLE</option>";
	$list_prefixes = get_prefixes();
	foreach ($list_prefixes as $key => $value){
		echo "<option value='" .$value["prefix"] ."'>" .$value["shortname"] ."</option>";
	}
        echo "</select>\n";
	echo "</td>\n</tr>\n\n";

	echo "<tr>\n<td align='right'>";
        echo "Categorie";
        echo "</td>\n<td>";
        echo "<select name='id_category'>\n";
        foreach ($cat_list as $value2){
                if ($posted_list["id_category"] == $value2["id"]){
                        echo "<option value='".$value2["id"]."' SELECTED>";
                }else{
                        echo "<option value='".$value2["id"]."' >";
                }
                echo htmlspecialchars($value2["fullname"],ENT_QUOTES);
                echo "</option>\n";
        }
        echo "</select>\n";
	echo "</td>\n</tr>\n\n";

        echo "<tr>\n<td colspan='2' align='right'>";
        echo "<input type='submit' name='zend' value='Zoeken'>";
        echo "</td>\n</tr>\n</table>\n";
        echo "</form>";
        echo "</p></div>";

	echo "<div class='border_b'>";
	echo "<table class='data' cellpadding='0' cellspacing='0' border='1' width='99%'>";
	echo "<tr class='header'>";
	echo "<td ><strong>";
	echo "<a href='overview.php?msg_orderby=content'>Wat</a>";
	echo "</strong></td>";
        echo "<td ><strong>";
        echo "<a href='overview.php?msg_orderby=letscode'>Wie</a>";
        echo "</strong></td>";
	echo "<td ><strong>Geldig tot";
	echo "</strong></td>";
	echo "</tr>";
	$rownumb=0;
	foreach($messagerows as $key => $value){
		$rownumb=$rownumb+1;
		if($rownumb % 2 == 1){
			echo "<tr class='uneven_row'>";
		}else{
	        	echo "<tr class='even_row'>";
		}

		echo "<td valign='top'>";
		if(strtotime($value["valdate"]) < time()) {
                        echo "<del>";
                }
		echo "<a href='view.php?id=".$value["msgid"]."'>";
		$content = htmlspecialchars($value["content"],ENT_QUOTES);
		echo chop_string($content, 50);
		if(strlen($content)>50){
			echo "...";
		}
		echo "</a>";
                 if(strtotime($value["valdate"]) < time()) {
                        echo "</del>";
                }

		echo "</a> ";
		echo "</td>";

                echo "<td valign='top' nowrap>";
                echo  htmlspecialchars($value["username"],ENT_QUOTES)." (".trim($value["letscode"]).")";
                echo "</td>";

		echo "<td>";
                if(strtotime($value["valdate"]) < time()) {
                        echo "<font color='red'><b>";
                }
                echo $value["valdate"];
                if(strtotime($value["valdate"]) < time()) {
                        echo "</b></font>";
                }
                echo "</td>";

		echo "</tr>";
	}
	echo "</table></div>";
}

function get_all_msgs($posted_list){
	$date = date('Y-m-d');
	global $db;
	$query = "SELECT *, ";
	$query .= " messages.id AS msgid, ";
	$query .= " users.id AS userid, ";
	$query .= " categories.id AS catid, ";
	$query .= " categories.fullname AS catname, ";
	$query .= " users.name AS username, ";
	$query .= " users.letscode AS letscode, ";
	$query .= " messages.validity AS valdate, ";
	$query .= " messages.cdate AS date ";
	$query .= " FROM messages, users, categories ";
	$query .= "  WHERE messages.id_user = users.id ";
	$query .= " AND messages.id_category = categories.id";

	$query .= " AND msg_type = " .$posted_list["msg_type"];
	$query .= " AND messages.id_category = " .$posted_list["id_category"];

	#Add subgroup filtering
	$prefix_filterby = $posted_list["prefix"];
	if ($prefix_filterby <> 'ALL'){
		 $query .= " AND users.letscode like '" .$prefix_filterby ."%'";
	}

	$messagerows = $db->GetArray($query);
	return $messagerows;
}

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_footer.php");
?>
