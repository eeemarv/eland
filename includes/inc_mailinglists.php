<?php
/**
 * Class to perform eLAS Mail operations
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

function get_mailinglists(){
        global $db;
        $query = "SELECT * FROM lists";

        $lists = $db->GetArray($query);
        return $lists;
        #var_dump($lists);
}

function get_my_open_mailinglists($userid){
        global $db;
        $query = "SELECT * FROM lists WHERE auth = 'open' AND listname in (SELECT listname FROM listsubscriptions WHERE user_id = " .$userid  .")";
        #print $query;

        $lists = $db->GetArray($query);
        return $lists;
        #var_dump($lists);
}

function get_mailinglist($listname){
		global $db;
        $query = "SELECT * FROM lists WHERE listname = '" .$listname ."'";
        $list =  $db->GetRow($query);
        #var_dump($list);
        return $list;
}

function get_availablelists($userid){
		global $db;
		$query = "SELECT * FROM lists WHERE auth = 'open' AND listname not in (SELECT listname FROM listsubscriptions WHERE user_id = " .$userid .")";
		$lists = $db->GetArray($query);
		return $lists;
}

?>
