<?php
/**
 * Class to perform <purpose}
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
 * add_contact($posted_list, $uid) 	Add a contact to a user
*/

function add_contact($posted_list, $uid){
        global $db;
        $posted_list["id_user"] = $uid;
        $result = $db->AutoExecute("contact", $posted_list, 'INSERT');
	if($result != TRUE) {
		setstatus("Contact niet toegevoegd", 1);
	}
}

?>
