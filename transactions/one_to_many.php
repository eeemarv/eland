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

$req = new request('admin');
$req->add('fixed', 0, 'post', array('type' => 'text', 'size' => 3, 'maxlength' => 3, 'label' => 'Vast bedrag'), array('match' => 'positive'))
	->add('percentage', 0, 'post', array('type' => 'text', 'size' => 4, 'maxlength' => 4, 'label' => 'Percentage'))
	->add('percentage_base', 0, 'post', array('type' => 'text', 'size' => 4, 'maxlength' => 4, 'label' => 'Percentage saldo-basis'))
	->add('fill_in', '', 'post', array('type' => 'submit', 'label' => 'Vul in'))
	->add('no_newcomers', '', 'post', array('type' => 'checkbox', 'label' => 'Geen instappers.'), array())
	->add('no_leavers', '', 'post', array('type' => 'checkbox', 'label' => 'Geen uitstappers.'), array())
	->add('no_max_limit', '', 'post', array('type' => 'checkbox', 'label' => 'Geen saldo\'s boven de maximum limiet'), array())
	->add('letscode_from', '', 'post', array('type' => 'text', 'size' => 5, 'maxlength' => 10, 'autocomplete' => 'off', 'label' => 'Van LetsCode'), array('not_empty' => true, 'match' => 'existing_letscode'))
	->add('description', '', 'post', array('type' => 'text', 'size' => 40, 'maxlength' => 60, 'autocomplete' => 'off', 'label' => 'Omschrijving'), array('not_empty' => true))
	->add('confirm_password', '', 'post', array('type' => 'password', 'size' => 10, 'maxlength' => 20, 'autocomplete' => 'off', 'label' => 'Paswoord (extra veiligheid)'), array('not_empty' => true, 'match' => 'password'))
	->add('zend', '', 'post', array('type' => 'submit', 'label' => 'Voer alle transacties uit'))
	->add('transid', generate_transid(), 'post', array('type' => 'hidden'))
	->add('refresh', '', 'post', array('type' => 'submit', 'label' => 'Ververs pagina'));

$query = 'SELECT id, fullname, letscode, accountrole, status, saldo, minlimit, maxlimit, adate
	FROM users
	WHERE status IN (1, 2, 3, 4)
	ORDER BY letscode';
$active_users = $db->GetArray($query);

$letscode_from = $req->get('letscode_from');
$from_user_id = null;

foreach($active_users as $user){
	$req->add('amount-'.$user['id'], 0, 'post', array('type' => 'text', 'size' => 3, 'maxlength' => 3, 'onkeyup' => 'recalc_table_sum(this);', 'onchange' => 'recalc_table_sum(this)'), array('match' => 'positive'));
	if ($letscode_from && $user['letscode'] == $letscode_from){
		$from_user_id = $user['id'];
		$from_user_fullname = $user['fullname'];
	}
}

if ($req->get('zend') && !$req->errors() && $from_user_id){
	$notice = '';
	$description = $req->get('description');
	$transid = $req->get('transid');
	$duplicate = check_duplicate_transaction($transid);
	if ($duplicate){
		$notice .= '<p><font color="red"><strong>Een dubbele boeking van een transactie werd voorkomen</strong></font></p>';
	} else {
		foreach($active_users as $user){
			$amount = $req->get('amount-'.$user['id']);
			if (!$amount || $from_user_id == $user['id'])
			{
				continue;
			}
			$trans = array(
				'id_from' => $from_user_id,
				'id_to' => $user['id'],
				'amount' => $amount,
				'description' => $description,
				'date' => date('Y-m-d H:i:s'),
				'transid'	=> $transid,
			);
			$notice_text = 'Transactie van gebruiker '.$from_user_fullname.' ( '.$letscode_from.' ) naar '.$user['fullname'].' ( '.$user['letscode'].' ) met bedrag '.$amount.' ';
			if(insert_transaction($trans, $transid))
			{
				mail_transaction($posted_list);
				$alert->success('OK - '.$notice_text. 'opgeslagen');
			}
			else
			{
				$alert->error($notice_text.'mislukt.');
			}
			$transid = generate_transid();
		}
	}
	$req->set('letscode_from', '');
	$req->set('description', '');
}

$fixed = $req->get('fixed');
$percentage = $req->get('percentage');
$percentage_base = $req->get('percentage_base');
$perc = 0;

if ($req->get('fill_in') && ($fixed || $percentage)){
	foreach ($active_users as $user){
		if ($user['letscode'] == $req->get('letscode_from')
			|| (check_newcomer($user['adate']) && $req->get('no_newcomers'))
			|| ($user['status'] == 2 && $req->get('no_leavers'))
			|| ($user['saldo'] > $user['maxlimit'] && $req->get('no_max_limit'))){
			$req->set('amount-'.$user['id'], 0);
		} else {
			if ($percentage){
				$perc = round(($user['saldo'] - $percentage_base)*$percentage/100);
				$perc = ($perc > 0) ? $perc : 0;
			}
			$req->set('amount-'.$user['id'], $fixed + $perc);
		}
	}
}

$req->set('amount-'.$from_user_id, 0);
$req->set('confirm_password', '');

$data_table = new data_table();
$data_table->set_data($active_users)->set_input($req)
	->add_column('letscode', array('title' => 'Aan LetsCode', 'render' => 'status'))
	->add_column('fullname', array('title' => 'Naam', 'render' => 'admin'))
	->add_column('accountrole', array('title' => 'Rol', 'footer_text' => 'TOTAAL', 'render' => 'admin'))
	->add_column('saldo', array('title' => 'Saldo', 'footer' => 'sum', 'render' => 'limit'))
	->add_column('amount', array('title' => 'Bedrag', 'input' => 'id', 'footer' => 'sum'))
	->add_column('maxlimit', array('title' => 'Max.Limiet'));

include($rootpath.'includes/inc_header.php');

echo '<h2><font color="#8888FF"><b><i>[admin]</i></b></font></h2>';
if ($notice) {
	echo '<div style="background-color: #DDDDFF;padding: 10px;">'.$notice.'</div>';
}

echo '<h1>Massa-Transactie. "Eén naar Veel". </h1>';

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo '<form method="post">';
echo '<div id="transformdiv" style="padding:10px;"><table cellspacing="5" cellpadding="0" border="0">';
$req->set_output('tr')->render(array('letscode_from', 'description'));
echo '</table><br/>';
$data_table->render();
echo '<table cellspacing="5" cellpadding="0" border="0">';
$req->set_output('tr')->render(array('confirm_password', 'zend', 'transid'));
echo '</table></div>';
echo '<div style="background-color:#ffdddd; padding: 10px;">';
echo '<p><strong>Een vast bedrag en/of percentage invullen voor alle rekeningen.</strong></p>';
echo '<table  cellspacing="5" cellpadding="0" border="0">';
$req->set_output('tr')->render(array('fixed', 'percentage', 'percentage_base', 'no_newcomers', 'no_leavers', 'no_max_limit', 'fill_in', 'transid'));
echo '</table>';
echo '<p><strong><i>Van LETSCode</i></strong> wordt altijd automatisch overgeslagen. Alle bedragen blijven individueel aanpasbaar alvorens de massa-transactie uitgevoerd wordt.</p>';
echo '<p><strong><i>Je kan een vast bedrag en/of een percentage op het saldo invullen</i></strong> Als een percentage wordt ingevuld, worden de bedragen berekend t.o.v. percentage saldo basis.</p>';
echo '</div><br/><table>';
$req->set_output('tr')->render('refresh');
echo '</table></form>';

echo '</div>';
echo '</div>';

include($rootpath.'includes/inc_footer.php');


function get_active_users(){
	global $db;

}

function check_newcomer($adate){
	global $configuration;
	$now = time();
	$limit = $now - ($configuration['system']['newuserdays'] * 60 * 60 * 24);
	$timestamp = strtotime($adate);
	return  ($limit < $timestamp) ? 1 : 0;
}

?>
