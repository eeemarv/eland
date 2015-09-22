<?php
ob_start();
$rootpath = '../';
$role = 'user';
require_once $rootpath . 'includes/inc_default.php';
require_once $rootpath . 'includes/inc_transactions.php';

$transaction = array();

if (isset($_POST['zend']))
{
	$transaction['description'] = $_POST['description'];
	list($letscode_from) = explode(' ', $_POST['letscode_from']);
	list($letscode_to) = explode(' ', $_POST['letscode_to']);
	$transaction['amount'] = $_POST['amount'];
	$transaction['date'] = date('Y-m-d H:i:s');
	$letsgroup_id = $_POST['letsgroup_id'];

	$letsgroup = $db->fetchAssoc('SELECT * FROM letsgroups WHERE id = ?', array($letsgroup_id));

	if (!isset($letsgroup))
	{
		$alert->error('Letsgroep niet gevonden.');
	}

	if ($s_accountrole == 'user')
	{
		$fromuser = $db->fetchAssoc('SELECT * FROM users WHERE id = ?', array($s_id));
	}
	else
	{
		$fromuser = $db->fetchAssoc('SELECT * FROM users WHERE letscode = ?', array($letscode_from));		
	}

	$letscode_touser = ($letsgroup['apimethod'] == 'internal') ? $letscode_to : $letsgroup['localletscode'];
	$touser = $db->fetchAssoc('SELECT * FROM users WHERE letscode = ?', array($letscode_touser));

	$transaction['id_from'] = $fromuser['id'];
	$transaction['id_to'] = $touser['id'];

	$transaction['transid'] = generate_transid();

	$errors = array();

	if (!$transaction['description'])
	{
		$errors[]= 'De omschrijving is niet ingevuld';
	}

	if (!$transaction['amount'])
	{
		$errors[] = 'Bedrag is niet ingevuld';
	}

	else if (!(ctype_digit($transaction['amount'])))
	{
		$errors[] = 'Het bedrag is geen geldig getal';
	}

	if(($fromuser['saldo'] - $transaction['amount']) < $fromuser['minlimit'] && $s_accountrole != 'admin')
	{
		$errors[] = 'Je beschikbaar saldo laat deze transactie niet toe';
	}

	if(empty($fromuser))
	{
		$errors[] = 'Gebruiker bestaat niet';
	}

	if(empty($touser) )
	{
		$errors[] = 'Bestemmeling bestaat niet';
	}

	if($fromuser['letscode'] == $touser['letscode'])
	{
		$errors[] = 'Van en Aan letscode zijn hetzelfde';
	}

	if($touser['saldo'] > $touser['maxlimit'] && $s_accountrole != 'admin')
	{
		$t_account = ($letsgroup['apimethod'] == 'internal') ? 'bestemmeling' : 'interletsrekening';
		$errors[] = 'De ' . $t_account . ' heeft zijn maximum limiet bereikt.';
	}

	if($letsgroup['apimethod'] == 'internal'
		&& $s_accountrole != 'admin'
		&& !($touser['status'] == '1' || $touser['status'] == '2'))
	{
		$errors[] = 'De bestemmeling is niet actief';
	}

	if (!$transaction['date'])
	{
		$errors[] = 'Datum is niet ingevuld';
	}
	else if (strtotime($transaction['date']) == -1)
	{
		$errors[] = 'Fout in datumformaat (jjjj-mm-dd)';
	}

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
					$alert->success('Transactie opgeslagen');
				}
				else
				{
					$alert->error('Gefaalde transactie');
				}
				header('Location: ' . $rootpath . 'transactions/alltrans.php');
				exit;

				break;

			case 'elassoap':

				$transaction['letscode_to'] = $letscode_to;
				$transaction['letsgroup_id'] = $letsgroup_id;
				$currencyratio = readconfigfromdb('currencyratio');
				$transaction['amount'] = $transaction['amount'] / $currencyratio;
				$transaction['amount'] = (float) $transaction['amount'];
				$transaction['amount'] = round($transaction['amount'], 5);
				$transaction['signature'] = sign_transaction($transaction, $letsgroup['presharedkey']);
				$transaction['retry_until'] = gmdate('Y-m-d H:i:s', time() + 86400);

				$transid = queuetransaction($transaction, $fromuser, $touser);

				if($transaction['transid'] == $transid)
				{
					$alert->success('Interlets transactie in verwerking');
				}
				else
				{
					$alert->error('Gefaalde transactie');
				}

				header('Location: ' . $rootpath . 'transactions/alltrans.php');
				exit;

				break;

			default:

				$alert->error('Geen geldige apimethode geselecteerd voor deze letsgroep. (contacteer een admin)');

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
	$fuid = ($_GET['fuid']) ?: false;

	$transaction = array(
		'date'			=> date('Y-m-d'),
		'letscode_from'	=> $s_letscode . ' ' . $s_name,
		'letscode_to'	=> '',
		'amount'		=> '',
		'description'	=> '',
	);

	if ($mid)
	{
		$row = $db->fetchAssoc('SELECT
				m.content, m.amount, u.letscode, u.fullname
			FROM messages m, users u
			WHERE u.id = m.id_user
				AND m.id = ?', array($mid));
		$transaction['letscode_to'] = $row['letscode'] . ' ' . $row['fullname'];
		$transaction['description'] =  '#m' . $mid . ' ' . $row['content'];
		$transaction['amount'] = $row['amount'];
	}
	else if ($uid)
	{
		$row = readuser($uid);
		$transaction['letscode_to'] = $row['letscode'] . ' ' . $row['fullname'];
	}

	if ($fuid && $s_accountrole == 'admin')
	{
		$row = readuser($fuid);
		$transaction['letscode_from'] = $row['letscode'] . ' ' . $row['fullname'];
	}
}

$internal_letsgroup_prefixes = array();

$rs = $db->prepare('SELECT id, prefix
	FROM letsgroups
	WHERE apimethod = \'internal\'
	ORDER BY prefix asc');

$rs->execute();

while ($row = $rs->fetch())
{
	$internal_letsgroup_prefixes[$row['id']] = $row['prefix'];
}

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

$includejs = '<script src="' . $cdn_typeahead . '"></script>
	<script src="' . $rootpath . 'js/transactions_add.js"></script>';

$user = readuser($s_id);
$balance = $user['saldo'];

$letsgroups = $db->fetchAll('SELECT id, groupname, url FROM letsgroups');

$currency = readconfigfromdb('currency');

$top_buttons .= '<a href="' . $rootpath . 'transactions/alltrans.php" class="btn btn-default"';
$top_buttons .= ' title="Transactielijst"><i class="fa fa-exchange"></i>';
$top_buttons .= '<span class="hidden-xs hidden-sm"> Lijst</span></a>';

$top_buttons .= '<a href="' . $rootpath . 'userdetails/mytrans_overview.php" class="btn btn-default"';
$top_buttons .= ' title="Mijn transacties"><i class="fa fa-exchange"></i>';
$top_buttons .= '<span class="hidden-xs hidden-sm"> Mijn transacties</span></a>';

$h1 = 'Nieuwe transactie';
$fa = 'exchange';

include $rootpath . 'includes/inc_header.php';

$minlimit = $user['minlimit'];

echo '<div>';
echo '<p><strong>' . $user['letscode'] .' '. $user['name']. ' huidige ' . $currency . ' stand: '.$balance.'</strong> || ';
echo '<strong>Limiet minstand: ' . $minlimit . '</strong></p>';
echo '</div>';

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo '<form  method="post" class="form-horizontal">';

echo ($s_accountrole == 'admin') ? '' : '<div style="display:none;">';

echo '<div class="form-group"';
echo ($s_accountrole == 'admin') ? '' : ' disabled="disabled" ';
echo '>';
echo '<label for="letscode_from" class="col-sm-2 control-label">';
echo '<span class="label label-default">Admin</span> ';
echo 'Van letscode</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="letscode_from" name="letscode_from" ';
echo 'value="' . $transaction['letscode_from'] . '" required>';
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

echo '<a href="' . $rootpath . 'transactions/alltrans.php" class="btn btn-default">Annuleren</a>&nbsp;';
echo '<input type="submit" name="zend" value="Overschrijven" class="btn btn-success">';

echo '</form>';
echo '</div>';
echo '</div>';

echo '<small><p>Tip: Het veld Aan LETSCode geeft autosuggesties door naam of letscode in te typen. ';
echo 'Kies eerst de juiste letsgroep om de juiste suggesties te krijgen.</p></small>';

include $rootpath . 'includes/inc_footer.php';
