<?php
ob_start();
$rootpath = "../";
$role = 'user';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_form.php");

$id = (isset($_GET["id"])) ? $_GET['id'] : 0;
$mode = $_GET["mode"];

if (!isset($mode))
{
	$alert->error('Edit mode is not set.');
	header('Location: ' . $rootpath . 'messages/overview.php');
	exit;
}

if ($_POST['zend'])
{
	$validity = (int) $_POST["validity"];
	$vtime = count_validity($validity);

	if ($s_accountrole == 'admin')
	{
		list($user_letscode) = explode(' ', $_POST['user_letscode']);
		$user_letscode = trim($user_letscode);
		$user = $db->GetRow('select *
			from users
			where letscode = \'' . $user_letscode . '\'
				and status in (1, 2)');
		if (!$user)
		{
			$error = 'Ongeldige letscode.' . $user_letscode;
		}
	}

	$msg = array(
		'validity'		=> (int) $_POST["validity"],
		'vtime'			=> $vtime,
		'content'		=> $_POST["content"],
		'description'	=> $_POST["description"],
		'msg_type'		=> (int) $_POST["msg_type"],
		'id_user'		=> ($s_accountrole == 'admin') ? (int) $user['id'] : $s_id,
		'id_category'	=> (int) $_POST["id_category"],
		'amount'		=> (int) $_POST["amount"],
		'units'			=> $_POST["units"],
	);

	$errors = validate_input($msg, $mode);

	if ($error)
	{
		$errors[] = $error;
	}

	if (count($errors))
	{
		foreach ($errors as $error)
		{
			$alert->error($error);
		}
	}
	else 
	{
		if ($mode == 'new')
		{
			if ($id = insert_msg($msg))
			{
				$alert->success('Vraag/aanbod toegevoegd.');
				header('Location: ' . $rootpath . 'messages/view.php?id=' . $id);
				exit;				
			}
			else
			{
				$alert->error('Fout bij het opslaan van vraag/aanbod');
			}
		}
		else 
		{
			if (update_msg($id, $msg))
			{
				$alert->success('Vraag/aanbod aangepast');
				header('Location: ' . $rootpath . 'messages/view.php?id=' . $id);
				exit;
			}
			else
			{
				$alert->error('Fout bij het opslaan van aanpassing vraag/aanbod.');
			}
		}
	}
}
else if ($mode == 'edit' && $id)
{
	$msg = get_msg($id);
	$msg['description'] = $msg['Description'];
	unset($msg['Description']);
	$msg['validity'] = reverse_count_validity($msg['validity']);
}
else if ($mode == 'new')
{
	$msg = array(
		'validity'		=> '2',
		'content'		=> '',
		'description'	=> '',
		'msg_type'		=> '1',
		'id_user'		=> $s_id,
		'id_category'	=> '',
		'amount'		=> '',
		'units'			=> '',
	);

	$uid = (isset($_GET['uid']) && $s_accountrole == 'admin') ? $_GET['uid'] : $s_id;

	$user = readuser($uid);

	$user_letscode = $user['letscode'] . ' ' . $user['fullname'];
}

$letsgroup_id = $db->GetOne('SELECT id
	FROM letsgroups
	WHERE apimethod = \'internal\'');

$cat_list = array('' => '') + get_cats();

$currency = readconfigfromdb("currency");

array_walk($msg, function(&$value, $key){ $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); });

$includejs = '
	<script src="' . $cdn_typeahead . '"></script>
	<script src="' . $rootpath . 'js/msg_edit.js"></script>';

$h1 = ($mode == 'new') ? 'Nieuw Vraag of Aanbod toevoegen' : 'Vraag of Aanbod aanpassen';
$fa = 'leanpub';

include $rootpath . 'includes/inc_header.php';

echo '<form method="post" class="form-horizontal">';

echo '<div class="form-group">';
echo '<label for="msg_type" class="col-sm-2 control-label">Vraag/Aanbod</label>';
echo '<div class="col-sm-10">';
echo '<select name="msg_type" id="msg_type" class="form-control" required>';
render_select_options(array('1' => 'Aanbod', '0' => 'Vraag'), $msg['msg_type']);
echo "</select>";
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="content" class="col-sm-2 control-label">Wat</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="content" name="content" ';
echo 'value="' . $msg['content'] . '" required>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="description" class="col-sm-2 control-label">Omschrijving</label>';
echo '<div class="col-sm-10">';
echo '<textarea name="description" class="form-control" id="description" rows="4">';
echo $msg['description'];
echo '</textarea>';
echo '</div>';
echo '</div>';

// Who selection is only for admins
if($s_accountrole == "admin")
{
	echo '<div class="form-group">';
	echo '<label for="user_letscode" class="col-sm-2 control-label">';
	echo '[Admin] Gebruiker</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="user_letscode" name="user_letscode" ';
	echo 'data-letsgroup-id="' . $letsgroup_id . '" ';
	echo 'value="' . $user_letscode . '" required>';
	echo '</div>';
	echo '</div>';
}

echo '<div class="form-group">';
echo '<label for="id_category" class="col-sm-2 control-label">Categorie</label>';
echo '<div class="col-sm-10">';
echo '<select name="id_category" id="id_category" class="form-control" required>';
render_select_options($cat_list, $msg['id_category']);
echo "</select>";
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="validity" class="col-sm-2 control-label">Geldigheid in maanden</label>';
echo '<div class="col-sm-10">';
echo '<input type="number" class="form-control" id="validity" name="validity" ';
echo 'value="' . $msg['validity'] . '" required>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="amount" class="col-sm-2 control-label">Aantal ' . $currency . '</label>';
echo '<div class="col-sm-10">';
echo '<input type="number" class="form-control" id="amount" name="amount" ';
echo 'value="' . $msg['amount'] . '">';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="units" class="col-sm-2 control-label">Per (uur, stuk, ...)</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="units" name="units" ';
echo 'value="' . $msg['units'] . '">';
echo '</div>';
echo '</div>';

echo '<a href="' . $rootpath . 'userdetails/mymsg_overview.php" class="btn btn-default">Annuleren</a>&nbsp;';
echo '<input type="submit" value="Opslaan" name="zend" class="btn btn-success">';

echo '</form>';

include $rootpath . 'includes/inc_footer.php';

function validate_input($msg)
{
	global $db;
	$error_list = array();
	if (!$msg['id_category'])
	{
		$error['id_category'] = 'Geieve een categorie te selecteren.';
	}
	if (empty($msg["content"]) || (trim($msg["content"]) == ""))
	{
		$error_list["content"] = "Vul inhoud in!";
		$query =" SELECT * FROM categories ";
		$query .=" WHERE  id = '".$msg["id_category"]."' ";
		$rs = $db->Execute($query);
    	$number = $rs->recordcount();
		if( $number == 0 )
		{
			$error_list["id_category"]=">Categorie bestaat niet!";
		}
	}

	$query = "SELECT * FROM users ";
	$query .= " WHERE id = ". $msg['id_user'];
	$query .= " AND status <> 0" ;
	$rs = $db->Execute($query);
    $number2 = $rs->recordcount();

	if( $number2 == 0 )
	{
		$error_list["id_user"]="Gebruiker bestaat niet!";
	}
	return $error_list;

}


function count_validity($months)
{
	$valtime = time() + ($months * 30 * 86400);
	$vtime =  gmdate("Y-m-d H:i:s", $valtime);
	return $vtime;
}

function reverse_count_validity($vtime)
{
	return round((strtotime($vtime) - time()) / (30 * 86400));
}

function update_msg($id, $posted_list)
{
    global $db;
    if(!empty($posted_list["validity"]))
    {
    	$posted_list["validity"] = $posted_list["vtime"];
    }
    else
    {
		unset($posted_list["validity"]);
    }
    $posted_list["mdate"] = gmdate("Y-m-d H:i:s");

	$description = $posted_list['description'];

	unset($posted_list['vtime'], $posted_list['description']);

	if (empty($posted_list['amount']))
	{
		unset($posted_list['amount']);
	}

	$result = $db->AutoExecute('messages', $posted_list, 'UPDATE', 'id = ' . $id);

	// Description could not be inserted with AutoExecute because the column is mixed case.
	$db->Execute('update messages set "Description" = \'' . pg_escape_string($description) . '\' where id = ' . $id);

	if ($msg['msg_type'] != $posted_list['msg_type'] || $msg['id_category'] != $posted_list['id_category'])
	{
		$column = 'stat_msgs_';
		$column .= ($msg['msg_type']) ? 'offers' : 'wanted';

		$db->Execute('update categories
			set ' . $column . ' = ' . $column . ' - 1
			where id = ' . $msg['id_category']);

		$column = 'stat_msgs_';
		$column .= ($posted_list['msg_type']) ? 'offers' : 'wanted';

		$db->Execute('update categories
			set ' . $column . ' = ' . $column . ' + 1
			where id = ' . $posted_list['id_category']);
	}

	return $result;
}

function insert_msg($posted_list)
{
    global $db;
	$posted_list["cdate"] = gmdate("Y-m-d H:i:s");
    $posted_list["validity"] = $posted_list["vtime"];

	$description = $posted_list['description'];

	unset($posted_list['vtime'], $posted_list['description']);

	if (empty($posted_list['amount']))
	{
		unset($posted_list['amount']);
	}

	if ($db->AutoExecute('messages', $posted_list, 'INSERT'))
	{
		$id = $db->insert_ID();

		$stat_column = 'stat_msgs_';
		$stat_column .= ($posted_list['msg_type']) ? 'offers' : 'wanted';

		$db->Execute('update categories set ' . $stat_column . ' = ' . $stat_column . ' + 1 where id = ' . $posted_list['id_category']);

		// Description could not be inserted with AutoExecute because the column is mixed case.
		$db->Execute('update messages set "Description" = \'' . pg_escape_string($description) . '\' where id = ' . $id);

		return $id;
	}

	return false;
}

function get_cats()
{
	global $db;
	$query = "SELECT id, fullname  FROM categories WHERE leafnote=1 order by fullname";
	return $db->GetAssoc($query);
}

function get_msg($id)
{
	global $db;
	$query = "SELECT * , messages.validity AS valdate ";
	$query .= " FROM messages WHERE id=" .$id ;
	return $db->GetRow($query);
}
