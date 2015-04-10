<?php
/**
 * Class to perform eLAS saldo operations
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the  * GNU General Public License for more details.
*/
/** Provided functions:
 * get_balance($userid)
 * update_saldo($userid)
*/

function get_balance($userid){
        global $db;
        $query_min = "SELECT SUM(amount) AS summin";
        $query_min .= " FROM transactions ";
        $query_min .= " WHERE id_from = ".$userid;
        $min = $db->GetRow($query_min);
        $min = $min["summin"];

        $query_plus = "SELECT SUM(amount) AS sumplus";
        $query_plus .= " FROM transactions ";
        $query_plus .= " WHERE id_to = ".$userid;
        $plus = $db->GetRow($query_plus);
        $plus = $plus["sumplus"];

        $balance = $plus - $min;
        return $balance;
}

function update_saldo($userid){
        global $db;
        $balance = get_balance($userid);

	$query = "UPDATE users SET saldo = $balance WHERE id = $userid";
	$result = $db->execute($query);
	if($result == FALSE) {
		setstatus("Saldo niet geupdate", 1);
	}
}

