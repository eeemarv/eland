<?php
ob_start();
$rootpath = "../";
$role = 'user';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_transactions.php");
require_once($rootpath."includes/inc_userinfo.php");
require_once($rootpath."includes/inc_mailfunctions.php");
require_once($rootpath."includes/inc_form.php");

$transaction = array();

if (isset($_POST['zend']))
{
	$transaction["description"] = $_POST["description"];
	list($letscode_from) = explode(' ', $_POST['letscode_from']);
	list($letscode_to) = explode(' ', $_POST['letscode_to']);
	$transaction['amount'] = $_POST['amount'];
	$transaction['date'] = ($_POST['date']) ? $_POST['date'] : $transaction["date"] = date("Y-m-d H:i:s");
	$letsgroup_id = $_POST['letsgroup_id'];

	$timestamp = make_timestamp($transaction["date"]);

	$letsgroup = $db->GetRow('SELECT * FROM letsgroups WHERE id = ' . $letsgroup_id);

	if (!isset($letsgroup))
	{
		$alert->error('Letsgroep niet gevonden.');
	}

	$where = ($s_accountrole == 'user') ? 'id = ' . $s_id : 'letscode = \'' . $letscode_from . '\'';
	$fromuser = $db->GetRow('SELECT * FROM users WHERE ' . $where);

	$letscode_touser = ($letsgroup['apimethod'] == 'internal') ? $letscode_to : $letsgroup['localletscode'];
	$touser = $db->GetRow('SELECT * FROM users WHERE letscode = \'' . $letscode_touser . '\'');

	$transaction['id_from'] = $fromuser['id'];
	$transaction['id_to'] = $touser['id'];

	$transaction['transid'] = generate_transid();

	$errors = validate_input($transaction, $fromuser, $touser, $letsgroup);

	if(!empty($errors))
	{
		$alert->error(implode('<br>', $errors));
	}
	else
	{
		switch($letsgroup['apimethod'])
		{
			case 'internal':
			case 'mail':
			
				if (insert_transaction($transaction))
				{
					if ($letsgroup['apimethod'] == 'internal')
					{
						mail_transaction($transaction);
					}
					else
					{
						mail_interlets_transaction($transaction);
					}
					$alert->success("Transactie opgeslagen");
				}
				else
				{
					$alert->error("Gefaalde transactie");
				}
				header('Location: ' . $rootpath . 'transactions/alltrans.php');
				exit;

				break;

			case "elassoap":

				$transaction["letscode_to"] = $letscode_to;
				$transaction["letsgroup_id"] = $letsgroup_id;
				$currencyratio = readconfigfromdb("currencyratio");
				$transaction["amount"] = $transaction["amount"] / $currencyratio;
				$transaction["amount"] = (float) $transaction["amount"];
				$transaction["amount"] = round($transaction["amount"], 5);
				$transaction["signature"] = sign_transaction($transaction, $letsgroup["presharedkey"]);
				$transaction["retry_until"] = time() + (60*60*24*4);
				// Queue the transaction for later handling
				$transid = queuetransaction($transaction, $fromuser, $touser);
				if($transaction['transid'] == $transid)
				{
					$alert->success("Interlets transactie in verwerking");
				}
				else
				{
					$alert->error("Gefaalde transactie");
				}
				header('Location: ' . $rootpath . 'transactions/alltrans.php');
				exit;
				
				break;

			case 'interletsdirect':

				break;
		}
	}

	$transaction['letscode_to'] = $_POST['letscode_to'];
	$transaction['letscode_from'] = ($s_accountrole == 'admin') ? $_POST['letscode_from'] : $s_letscode . ' ' . $s_name;
}
else
{
	$mid = ($_GET['mid']) ?: false;
	$uid = ($_GET['uid']) ?: false;

	$transaction = array(
		'date'			=> date('Y-m-d'),
		'letscode_from'	=> $s_letscode . ' ' . $s_name,
		'letscode_to'	=> '',
		'amount'		=> '',
		'description'	=> '',
	);

	if ($mid)
	{
		$transaction = $db->GetRow('SELECT
				m.content, m.amount, u.letscode as letscode_to, u.fullname
			FROM messages m, users u
			WHERE u.id = m.id_user
				AND m.id = ' . $mid);
		$transaction['letscode_to'] .= ' ' . $transaction['fullname'];
		$transaction['description'] =  '#m' . $mid . ' ' . $transaction['content'];
	}
	else if ($uid)
	{
		$transaction = $db->GetRow('SELECT letscode as letscode_to, fullname FROM users WHERE id = ' . $uid);
		$transaction['letscode_to'] .= ' ' . $transaction['fullname'];
	}
}

$internal_letsgroup_prefixes = $db->GetAssoc('SELECT id, prefix
	FROM letsgroups
	WHERE apimethod = \'internal\'
	ORDER BY prefix asc');

foreach ($internal_letsgroup_prefixes as $letsgroup_id => $letsgroup_prefix)
{
	if (!$letsgroup_prefix)
	{
		break;
	}

	if (strpos(strtolower($s_letscode), strtolower($letsgroup_prefix) === 0))
	{
		break;
	}
}

if (!isset($_POST['zend']))
{
	$letsgroup['id'] = $letsgroup_id;
}

$includejs = '
	<script src="' . $cdn_jquery . '"></script>
	<script src="' . $cdn_datepicker . '"></script>
	<script src="' . $cdn_datepicker_nl . '"></script>
	<script src="' . $cdn_typeahead . '"></script>
	<script src="' . $rootpath . 'js/transactions_add.js"></script>';

$includecss = '<link rel="stylesheet" type="text/css" href="' . $cdn_datepicker_css . '" />';

include $rootpath . 'includes/inc_header.php';

$user = get_user($s_id);
$balance = $user["saldo"];

//$list_users = get_users($s_id);

$currency = readconfigfromdb('currency');

echo "<h1>{$currency} uitschrijven</h1>";

$minlimit = $user["minlimit"];

echo "<div id='baldiv'>";
echo '<p><strong>' . $user["name"].' '.$user["letscode"] . ' huidige ' . $currency . ' stand: '.$balance.'</strong> || ';
echo "<strong>Limiet minstand: " . $minlimit . "</strong></p>";
echo "</div>";

$date = date("Y-m-d");

echo "<div id='transformdiv'>";
echo "<form  method='post'>";
echo "<table>";

echo "<tr>";
echo "<td align='right'>Van LETScode</td>";
echo "<td>";

echo '<input type="text" name="letscode_from" size="30" value="' . $transaction['letscode_from'] . '" ';
echo ($s_accountrole == 'admin') ? '' : ' disabled="disabled" ';
echo 'required id="letscode_from">';

echo "</td><td width='150'><div id='fromoutputdiv'></div>";
echo "</td></tr>";

echo "<tr><td valign='top' align='right'>Datum</td><td>";
echo "<input type='text' name='date' size='10' value='" .$date ."' ";
echo ($s_accountrole == "admin") ? '' : ' disabled="disabled" ';
echo 'data-provide="datepicker" data-date-format="yyyy-mm-dd" ';
echo 'data-date-language="nl" ';
echo 'data-date-today-highlight="true" ';
echo 'data-date-autoclose="true" ';
echo 'data-date-enable-on-readonly="false" ';
echo ">";
echo "</td><td>";

echo "</td></tr><tr><td></td><td>";
echo "</td></tr>";

echo "<tr><td align='right'>";
echo "Aan LETS groep";
echo "</td><td>";
$letsgroups = $db->getArray('SELECT id, groupname, url FROM letsgroups');

echo "<select name='letsgroup_id' id='letsgroup_id'>";

foreach ($letsgroups as $value)
{
	$thumbprint = (getenv('ELAS_DEBUG')) ? time() : $redis->get($value['url'] . '_typeahead_thumbprint');
	echo '<option value="' . $value['id'] . '" ';
	echo 'data-thumbprint="' . $thumbprint . '"';
	echo ($value['id'] == $letsgroup['id']) ? ' selected="selected"' : '';
	echo ($value['id'] == $letsgroup_id) ? ' data-this-letsgroup="1"' : '';
	echo '>' . htmlspecialchars($value['groupname'], ENT_QUOTES) . '</option>';
}

echo "</select>";
echo "</td><td>";
echo "</td></tr>";

echo "<tr><td></td><td>";
echo "<tr><td align='right'>";
echo "Aan LETScode";
echo "</td><td>";
echo '<input type="text" name="letscode_to" id="letscode_to" ';
echo 'value="' . $transaction['letscode_to'] . '" size="30" required>';
echo "</td><td><div id='tooutputdiv'></div>";
echo "</td></tr><tr><td></td><td>";
echo "</td></tr>";

echo '<tr><td valign="top" align="right">Aantal ' . $currency . '</td><td>';
echo "<input type='number' min='1' name='amount' size='10' ";
echo 'value="' . $transaction['amount'] . '" required>';
echo "</td><td>";
echo "</td></tr>";
echo "<tr><td></td><td>";
echo "</td></tr>";

echo "<tr><td valign='top' align='right'>Dienst</td><td>";
echo '<input type="text" name="description" id="description" size="30" maxlength="60" ';
echo 'value="' . $transaction['description'] . '" required>';
echo "</td><td>";
echo "</td></tr><tr><td></td><td>";
echo "</td></tr>";
echo "<tr><tr><td colspan='3'>&nbsp;</td></tr><td></td><td colspan='2'>";
echo "<input type='submit' name='zend' id='zend' value='Overschrijven'>";
echo "</td></tr></table>";
echo "</form>";
echo "</div>";

echo '<small><p>Tip: Het veld Aan LETSCode geeft autosuggesties door naam of letscode in te typen.</p></small>';

include $rootpath . 'includes/inc_footer.php';

///////////////////////////////////////////////////////


// Make timestamps for SQL statements
function make_timestamp($timestring)
{
	list($day, $month, $year) = explode('-', $timestring);
	return mktime(0, 0, 0, trim($month), trim($day), trim($year));
}

function validate_input($transaction, $fromuser, $touser, $letsgroup)
{
	global $s_accountrole;

	$errors = array();

	if (!isset($transaction["description"]) || (trim($transaction["description"] )==""))
	{
		$errors["description"]="Dienst is niet ingevuld";
	}

	if (!isset($transaction["amount"])|| (trim($transaction["amount"] )==""))
	{
		$errors["amount"]="Bedrag is niet ingevuld";
	}
	else if (eregi('^[0-9]+$', $transaction['amount']) == FALSE)
	{
		$errors["amount"]="Bedrag is geen geldig getal";
	}

	$user = get_user($transaction["id_from"]);
	if(($user["saldo"] - $transaction["amount"]) < $fromuser["minlimit"] && $s_accountrole != "admin")
	{
		$errors["amount"]="Je beschikbaar saldo laat deze transactie niet toe";
	}

	if(empty($fromuser))
	{
		$errors["id_from"] = "Gebruiker bestaat niet";
	}

	if(empty($touser) )
	{
		$errors["id_to"] = "Bestemmeling bestaat niet";
	}

	if($fromuser["letscode"] == $touser["letscode"])
	{
		$errors["id"] = "Van en Aan zijn hetzelfde";
	}

	if(($touser["maxlimit"] != NULL && $touser["maxlimit"] != 0)
		&& $touser["saldo"] > $touser["maxlimit"] && $s_accountrole != "admin")
	{
		$t_account = ($letsgroup['apimethod'] == 'internal') ? 'bestemmeling' : 'interletsrekening';
		$errors["id_to"] = 'De ' . $t_account . ' heeft zijn maximum limiet bereikt.';
	}

	if($letsgroup['apimethod'] == 'internal'
		&& $s_accountrole != 'admin'
		&& !($touser["status"] == '1' || $touser["status"] == '2'))
	{
		$errors["id_to"]="De bestemmeling is niet actief";
	}

	//date may not be empty
	if (!isset($transaction["date"]) || (trim($transaction["date"] )==""))
	{
		$errors["date"]="Datum is niet ingevuld";
	}
	else if (strtotime($transaction["date"]) == -1)
	{
		$errors["date"]="Fout in datumformaat (jjjj-mm-dd)";
	}

	return $errors;
}





