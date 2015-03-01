<?php
// Get the account balance

function get_filtered_users($prefix_filterby){
        global $db;
        $query = "SELECT * FROM users ";
        $query .= "WHERE (status = 1  ";
        $query .= "OR status =2 OR status = 3)  ";
        $query .= "AND users.accountrole <> 'guest' ";
       	if ($prefix_filterby <> 'ALL'){
                 $query .= "AND users.letscode like '" .$prefix_filterby ."%'";
        }
        $query .= " order by letscode";
        $list_users = $db->GetArray($query);
        return $list_users;
}

function show_user_balance($users,$date){
        echo "<div class='border_b'>";
        echo "<table class='data' cellpadding='0' cellspacing='0' border='1' width='99%'>";
        echo "<tr class='header'>";
        echo "<td nowrap valign='top'><strong>Letscode</strong></td>";
        echo "<td nowrap valign='top'><strong>Naam</strong></td>";
        echo "<td nowrap valign='top'><strong>Saldo</strong></td>";
        $rownumb=0;
        foreach($users as $key => $value){
                $value["balance"] = $value["saldo"];
                $rownumb=$rownumb+1;
                if($rownumb % 2 == 1){
                        echo "<tr class='uneven_row'>";
                }else{
                        echo "<tr class='even_row'>";
                }
                echo "<td nowrap valign='top'>";
                echo $value["letscode"];
                echo "</td>";
                echo "<td nowrap valign='top'>";
                echo $value["fullname"];
                echo "</td>";
                echo "<td nowrap valign='top'>";
                echo $value["balance"];
                echo "</td>";
                echo "</tr>";
        }
        echo "</table></div>";
}

?>
