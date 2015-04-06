<?php
ob_start();

$rootpath = '../';
$role = 'admin';
require_once($rootpath.'includes/inc_default.php');
require_once($rootpath.'includes/inc_adoconnection.php');

require_once($rootpath.'includes/inc_transactions.php');
require_once($rootpath.'includes/inc_request.php');
require_once($rootpath.'includes/inc_data_table.php');

//status 0: inactief
//status 1: letser
//status 2: uitstapper
//status 3: instapper
//status 4: secretariaat
//status 5: infopakket
//status 6: stapin
//status 7: extern

$req = new request();
$req->add('fixed', 0, 'post', array('type' => 'number', 'min' => 0, 'size' => 4, 'maxlength' => 4, 'label' => 'Vast bedrag'), array('match' => 'positive'))
	->add('percentage', 0, 'post', array('type' => 'number', 'size' => 4, 'maxlength' => 4, 'label' => 'Percentage op saldo'))
	->add('percentage_base', 0, 'post', array('type' => 'number', 'size' => 4, 'maxlength' => 4, 'label' => 'Basis voor percentage op saldo'))
	->add('percentage_transactions', 0, 'post', array('type' => 'number', 'min' => 0, 'size' => 4, 'maxlength' => 4, 'label' => 'Percentage op uitgeschreven transacties'))
	->add('percentage_transactions_days', 0, 'post', array('type' => 'number', 'size' => 4, 'maxlength' => 4, 'label' => 'Periode in dagen voor het percentage op uitgeschreven transacties.'))
	->add('fill_in', '', 'post', array('type' => 'submit', 'label' => 'Vul in'))
	->add('no_newcomers', '', 'post', array('type' => 'checkbox', 'label' => 'Geen instappers.'), array())
	->add('no_leavers', '', 'post', array('type' => 'checkbox', 'label' => 'Geen uitstappers.'), array())
	->add('no_min_limit', '', 'post', array('type' => 'checkbox', 'label' => 'Geen saldo\'s onder de minimum limiet.'), array())
	->add('letscode_to', '', 'post', array('type' => 'text', 'size' => 5, 'maxlength' => 10, 'autocomplete' => 'off', 'label' => 'Aan LetsCode'), array('not_empty' => true, 'match' => 'existing_letscode'))
	->add('description', '', 'post', array('type' => 'text', 'size' => 40, 'maxlength' => 60, 'autocomplete' => 'off', 'label' => 'Omschrijving'), array('not_empty' => true))
	->add('confirm_password', '', 'post', array('type' => 'password', 'size' => 10, 'maxlength' => 20, 'autocomplete' => 'off', 'label' => 'Paswoord (extra veiligheid)'), array('not_empty' => true, 'match' => 'password'))
	->add('zend', '', 'post', array('type' => 'submit', 'label' => 'Voer alle transacties uit.'))
	->add('transid', generate_transid(), 'post', array('type' => 'hidden'))
	->add('refresh', '', 'post', array('type' => 'submit', 'label' => 'Ververs pagina'));

$active_users = $db->GetArray(
	'SELECT id, fullname, letscode,
		accountrole, status, saldo, minlimit, maxlimit, adate
	FROM users
	WHERE status IN (1, 2)
	ORDER BY letscode');

$letscode_to = $req->get('letscode_to');
$to_user_id = null;

foreach($active_users as $user){
	$req->add('amount-'.$user['id'], 0, 'post', array('type' => 'number', 'min' => 0, 'size' => 3, 'maxlength' => 3, 'onkeyup' => 'recalc_table_sum(this);'), array('match' => 'positive'));
	if ($letscode_to && $user['letscode'] == $letscode_to){
		$to_user_id = $user['id'];
		$to_user_fullname = $user['fullname'];
	}
}

if ($req->get('zend') && $req->errors())
{
	$alert->error('Eén of meerdere velden in het formulier zijn niet correct ingevuld (zie onder).');
	$form_errors = true;
}

if ($req->get('zend') && !(isset($form_errors)) && $to_user_id)
{
	$description = $req->get('description');
	$transid = $req->get('transid');

	if (check_duplicate_transaction($transid))
	{
		$alert->error('Een dubbele boeking van een transactie werd voorkomen.');
	} else {
		foreach($active_users as $user){
			$amount = $req->get('amount-'.$user['id']);
			if (!$amount || $to_user_id == $user['id'])
			{
				continue;
			}
			$trans = array(
				'id_to' 		=> $to_user_id,
				'id_from' 		=> $user['id'],
				'amount' 		=> $amount,
				'description' 	=> $description,
				'date' 			=> date('Y-m-d H:i:s'),
				'transid'		=> $transid,
			);
			$notice_text = 'Transactie van gebruiker '.$user['fullname'].' ( '.$user['letscode'].' ) naar '.$to_user_fullname.' ( '.$letscode_to.' ) met bedrag '.$amount.' ';
			if(insert_transaction($trans))
			{
				mail_transaction($trans);
				$alert->success($notice_text . ' opgeslagen');
			} else {
				$alert->error($notice_text . ' mislukt');
			}
			$transid = generate_transid();
		}
	}
	$req->set('letscode_to', '');
	$req->set('description', '');
}

$fixed = $req->get('fixed');
$percentage = $req->get('percentage');
$percentage_base = $req->get('percentage_base');
$perc = 0;

$percentage_transactions = $req->get('percentage_transactions');
$percentage_transactions_days = $req->get('percentage_transactions_days');

if ($percentage_transactions && $percentage_transactions_days)
{
	$refdate = date('Y-m-d H:i:s', time() - (86400 * $percentage_transactions_days));
	$user_trans = $db->GetAssoc('SELECT tr.id_from, SUM(tr.amount) 
		FROM transactions tr, users u
		WHERE tr.cdate > \'' . $refdate . '\'
			AND u.id = tr.id_from
			AND u.status IN (1, 2)
		GROUP BY tr.id_from');
}

if ($req->get('fill_in') && ($fixed || $percentage || $user_trans))
{
	$newusertreshold = time() - readconfigfromdb('newuserdays') * 86400;
	foreach ($active_users as $user)
	{
		if ($user['letscode'] == $req->get('letscode_to')
			|| ($newusertreshold < strtotime($user['adate'])
				&& $req->get('no_newcomers'))
			|| ($user['status'] == 2 && $req->get('no_leavers'))
			|| ($user['saldo'] < $user['minlimit'] && $req->get('no_min_limit')))
		{
			$req->set('amount-'.$user['id'], 0);
		}
		else
		{
			if ($percentage)
			{
				$perc = round(($user['saldo'] - $percentage_base)*$percentage/100);
				$perc = ($perc > 0) ? $perc : 0;
			}
			$perc_trans = (isset($user_trans[$user['id']])) ? round($user_trans[$user['id']] * $percentage_transactions / 100) : 0;
			$amount = $fixed + $perc + $perc_trans;
			$req->set('amount-'.$user['id'], $amount);
		}
	}
}

$req->set('amount-'.$to_user_id, 0);
$req->set('confirm_password', '');

$data_table = new data_table();
$data_table->set_data($active_users)->set_input($req)
	->add_column('letscode', array('title' => 'Van LetsCode', 'render' => 'status'))
	->add_column('fullname', array('title' => 'Naam'))
	->add_column('accountrole', array('title' => 'Rol', 'footer_text' => 'TOTAAL'))
	->add_column('saldo', array('title' => 'Saldo', 'footer' => 'sum', 'render' => 'limit'))
	->add_column('amount', array('title' => 'Bedrag', 'input' => 'id', 'footer' => 'sum'))
	->add_column('minlimit', array('title' => 'Min.Limiet'));

include($rootpath.'includes/inc_header.php');

echo '<h2><font color="#8888FF"><b><i>[admin]</i></b></font></h2>';

echo '<h1>Massa-Transactie. "Veel naar Eén".</h1><p>bvb. voor leden-bijdrage.</p>';

echo '<form method="post"><div style="background-color:#ffdddd; padding:10px;">';
echo '<h2>Invul-hulp</h2>';
echo '<p>Met deze invul hulp kan je snel alle bedragen van de massa-transactie invullen. ';
echo 'De eigenlijke massa-transactie doe je met het gele formulier onderaan. Daar zie ook de ';
echo 'feitelijk bedragen die zullen worden overgeschreven. Je kan daar de bedragen alvorens nog individueel ';
echo 'aanpassen.</p>';
echo '<table  cellspacing="5" cellpadding="0" border="0">';
$req->set_output('tr')->render(array(
	'fixed', 'percentage', 'percentage_base',
	'percentage_transactions', 'percentage_transactions_days',
	'no_newcomers', 'no_leavers', 'no_min_limit', 'fill_in'));
echo '</table>';
echo '<p><strong><i>Aan LETSCode</i></strong> wordt altijd automatisch overgeslagen. Alle bedragen blijven individueel aanpasbaar alvorens de massa-transactie uitgevoerd wordt.</p>';
echo '<p>Deze mogelijkheden zijn combineerbaar:';
echo '<ul><li><strong>Vast bedrag</strong></li>';
echo '<li><strong>Percentage op saldo</strong> (Het percentage kan ook negatief zijn.) ';
echo 'De basis zal gewoonlijk nul zijn, maar er het is ook mogelijk een percentage te berekenen t.o.v. een ander bedrag.</li>';
echo '<li><strong>Percentage op uitgeschreven transacties</strong> Percentage en aantal dagen dienen beide ingevuld te zijn indien men deze optie wil gebruiken.</li>';
echo '</ul></div></form>';
echo '<div id="transformdiv" style="padding:10px;">';
$data_table->render();
echo '<table cellspacing="0" cellpadding="5" border="0">';
$req->set_output('tr')->render(array('letscode_to', 'description', 'confirm_password', 'zend', 'transid'));
echo '</table></div><table>';
$req->set_output('tr')->render('refresh');
echo '</table></form>';

include($rootpath.'includes/inc_footer.php');


function check_newcomer($adate)
{
	return  (time() - (readconfigfromdb('newuserdays') * 86400) < strtotime($adate)) ? true : false;
}
