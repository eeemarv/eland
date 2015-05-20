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

if (isset($_POST['cancel']))
{
	header('Location: ' . $rootpath . 'transactions/alltrans.php');
	exit;
}

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
		$alert->error(implode("\n", $errors));
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
	$transaction['letscode_from'] = $_POST['letscode_from'];
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
	<script src="' . $cdn_datepicker . '"></script>
	<script src="' . $cdn_datepicker_nl . '"></script>
	<script src="' . $cdn_typeahead . '"></script>
	<script src="' . $rootpath . 'js/transactions_add.js"></script>';

$includecss = '<link rel="stylesheet" type="text/css" href="' . $cdn_datepicker_css . '" />';

include $rootpath . 'includes/inc_header.php';

$user = get_user($s_id);
$balance = $user["saldo"];

$letsgroups = $db->getArray('SELECT id, groupname, url FROM letsgroups');

$currency = readconfigfromdb('currency');

echo "<h1>Nieuwe transactie</h1>";

$minlimit = $user["minlimit"];

echo "<div>";
echo '<p><strong>' . $user["name"].' '.$user["letscode"] . ' huidige ' . $currency . ' stand: '.$balance.'</strong> || ';
echo "<strong>Limiet minstand: " . $minlimit . "</strong></p>";
echo "</div>";

$date = date("Y-m-d");

echo '<form  method="post" class="form-horizontal">';

echo ($s_accountrole == 'admin') ? '' : '<div style="display:none;">';

echo '<div class="form-group"';
echo ($s_accountrole == 'admin') ? '' : ' disabled="disabled" ';
echo '>';
echo '<label for="letscode_from" class="col-sm-2 control-label">Van letscode</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="letscode_from" name="letscode_from" ';
echo 'value="' . $transaction['letscode_from'] . '" required>';
echo '</div>';
echo '</div>';

echo '<div class="form-group"';
echo ($s_accountrole == 'admin') ? '' : ' disabled="disabled" ';
echo '>';
echo '<label for="date" class="col-sm-2 control-label">Datum</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="date" name="date" ';
echo 'data-provide="datepicker" data-date-format="yyyy-mm-dd" ';
echo 'data-date-language="nl" ';
echo 'data-date-today-highlight="true" ';
echo 'data-date-autoclose="true" ';
echo 'data-date-enable-on-readonly="false" ';
echo 'value="' . $transaction['date'] . '" required>';
echo '</div>';
echo '</div>';

echo ($s_accountrole == 'admin') ? '' : '</div>';

echo '<div class="form-group">';
echo '<label for="letsgroup_id" class="col-sm-2 control-label">Aan letsgroep</label>';
echo '<div class="col-sm-10">';
echo '<select type="text" class="form-control" id="letsgroup_id" name="letsgroup_id">';
foreach ($letsgroups as $value)
{
	$thumbprint = (getenv('ELAS_DEBUG')) ? time() : $redis->get($value['url'] . '_typeahead_thumbprint');
	echo '<option value="' . $value['id'] . '" ';
	echo 'data-thumbprint="' . $thumbprint . '"';
	echo ($value['id'] == $letsgroup['id']) ? ' selected="selected"' : '';
	echo ($value['id'] == $letsgroup_id) ? ' data-this-letsgroup="1"' : '';
	echo '>' . htmlspecialchars($value['groupname'], ENT_QUOTES) . '</option>';
}
echo '</select>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="letscode_to" class="col-sm-2 control-label">Aan letscode</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="letscode_to" name="letscode_to" ';
echo 'value="' . $transaction['letscode_to'] . '" required>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="amount" class="col-sm-2 control-label">Aantal ' . $currency . '</label>';
echo '<div class="col-sm-10">';
echo '<input type="number" class="form-control" id="amount" name="amount" ';
echo 'value="' . $transaction['amount'] . '" required>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="description" class="col-sm-2 control-label">Omschrijving</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="description" name="description" ';
echo 'value="' . $transaction['description'] . '" required>';
echo '</div>';
echo '</div>';

echo '<input type="submit" name="cancel" value="Annuleren" class="btn btn-default">&nbsp;';
echo '<input type="submit" name="zend" value="Overschrijven" class="btn btn-success">';

echo "</form>";

include($rootpath."includes/inc_footer.php");

////////

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
		$t_account = ($letsgroup['apimethod'] == 'internal') ? 'interletsrekening' : 'bestemmeling';
		$errors["id_to"] = 'De ' . $t_account . ' heeft zijn maximum limiet bereikt.';
	}


	if($letsgroup['apimethod'] == 'internal' && !($touser["status"] == '1' || $touser["status"] == '2'))
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





