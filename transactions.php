<?php

$page_access = 'guest';
require_once __DIR__ . '/include/web.php';
require_once __DIR__ . '/include/transactions.php';

$orderby = $_GET['orderby'] ?? 'cdate';
$asc = $_GET['asc'] ?? 0;
$limit = $_GET['limit'] ?? 25;
$start = $_GET['start'] ?? 0;
$id = $_GET['id'] ?? false;
$add = isset($_GET['add']) ? true : false;
$edit = $_GET['edit'] ?? false;
$submit = isset($_POST['zend']) ? true : false;

$mid = $_GET['mid'] ?? false;
$tuid = $_GET['tuid'] ?? false;
$tus = $_GET['tus'] ?? false;
$fuid = $_GET['fuid'] ?? false;
$uid = $_GET['uid'] ?? false;
$inline = isset($_GET['inline']) ? true : false;

$q = $_GET['q'] ?? '';
$fcode = $_GET['fcode'] ?? '';
$tcode = $_GET['tcode'] ?? '';
$andor = $_GET['andor'] ?? 'and';
$fdate = $_GET['fdate'] ?? '';
$tdate = $_GET['tdate'] ?? '';

$currency = $app['config']->get('currency');

/**
 * add
 */
if ($add)
{
	if ($s_guest)
	{
		$app['alert']->error('Je hebt geen rechten om een transactie toe te voegen.');
		cancel();
	}

	$transaction = [];

	$redis_transid_key = $app['this_group']->get_schema() . '_transid_u_' . $s_id;

	if ($submit)
	{
		$stored_transid = $app['predis']->get($redis_transid_key);

		if (!$stored_transid)
		{
			$errors[] = 'Formulier verlopen.';
		}

		$transaction['transid'] = trim($_POST['transid']);
		$transaction['description'] = trim($_POST['description']);

		list($letscode_from) = explode(' ', $_POST['letscode_from']);
		list($letscode_to) = explode(' ', $_POST['letscode_to']);

		$transaction['amount'] = $amount = ltrim($_POST['amount'], '0 ');;
		$transaction['date'] = gmdate('Y-m-d H:i:s');

		$group_id = trim($_POST['group_id']);

		if ($stored_transid != $transaction['transid'])
		{
			$errors[] = 'Fout transactie id.';
		}

		if ($app['db']->fetchColumn('select transid from transactions where transid = ?', [$stored_transid]))
		{
			$errors[] = 'Een herinvoer van de transactie werd voorkomen.';
		}

		if (strlen($transaction['description']) > 60)
		{
			$errors[] = 'De omschrijving mag maximaal 60 tekens lang zijn.';
		}

		if ($group_id != 'self')
		{
			$group = $app['db']->fetchAssoc('SELECT * FROM letsgroups WHERE id = ?', [$group_id]);

			if (!isset($group))
			{
				$errors[] =  'groep niet gevonden.';
			}
			else
			{
				$group['domain'] = strtolower(parse_url($group['url'], PHP_URL_HOST));
			}
		}

		if ($s_user && !$s_master)
		{
			$fromuser = $app['db']->fetchAssoc('SELECT * FROM users WHERE id = ?', [$s_id]);
		}
		else
		{
			$fromuser = $app['db']->fetchAssoc('SELECT * FROM users WHERE letscode = ?', [$letscode_from]);
		}

		$letscode_touser = ($group_id == 'self') ? $letscode_to : $group['localletscode'];

		$touser = $app['db']->fetchAssoc('select * from users where letscode = ?', [$letscode_touser]);

		if(empty($fromuser))
		{
			$errors[] = 'Gebruiker "Van ' . $app['type_template']->get('code') . '" bestaat niet';
		}

		if (!strlen($letscode_to))
		{
			$errors[] = 'Geen bestemmeling (Aan ' . $app['type_template']->get('code') . ') ingevuld';
		}

		if(empty($touser) && !count($errors))
		{
			if ($group_id == 'self')
			{
				$errors[] = 'Bestemmeling (Aan ' . $app['type_template']->get('code') . ') bestaat niet';
			}
			else
			{
				$errors[] = 'De interletsrekening (in deze groep) bestaat niet';
			}
		}

		if ($group_id == 'self' && !count($errors))
		{
			if ($touser['status'] == 7)
			{
				$errors[] = 'Je kan niet rechtstreeks naar een interletsrekening overschrijven.';
			}
		}

		if ($fromuser['status'] == 7 && !count($errors))
		{
			$errors[] = 'Je kan niet rechtstreeks van een interletsrekening overschrijven.';
		}

		$transaction['id_from'] = $fromuser['id'];
		$transaction['id_to'] = $touser['id'];

		if (!$transaction['description'])
		{
			$errors[]= 'De omschrijving is niet ingevuld';
		}

		if (!$transaction['amount'])
		{
			$errors[] = 'Bedrag is niet ingevuld';
		}

		else if (!(ctype_digit((string) $transaction['amount'])) && !count($errors))
		{
			$errors[] = 'Het bedrag is geen geldig getal';
		}

		if (!$s_admin && !count($errors))
		{
			if ($fromuser['minlimit'] === -999999999)
			{
				$minlimit = $app['config']->get('minlimit');

				if(($fromuser['saldo'] - $amount) < $minlimit && $minlimit !== '')
				{
					$err = 'Je beschikbaar saldo laat deze transactie niet toe. ';
					$err .= 'Je saldo bedraagt ' . $fromuser['saldo'] . ' ' . $currency . ' ';
					$err .= 'en de minimum groepslimiet bedraagt ';
					$err .= $minlimit . ' ' . $currency;
					$errors[] = $err;
				}
			}
			else
			{
				if(($fromuser['saldo'] - $amount) < $fromuser['minlimit'])
				{
					$err = 'Je beschikbaar saldo laat deze transactie niet toe. ';
					$err .= 'Je saldo bedraagt ' . $fromuser['saldo'] . ' ';
					$err .= $currency . ' en je minimum limiet bedraagt ';
					$err .= $fromuser['minlimit'] . ' ' . $currency . '.';
					$errors[] = $err;
				}
			}
		}

		if(($fromuser['letscode'] == $touser['letscode']) && !count($errors))
		{
			$errors[] = 'Van en Aan ' . $app['type_template']->get('code') . ' zijn hetzelfde';
		}

		if (!$s_admin && !count($errors))
		{
			if ($touser['maxlimit'] === 999999999)
			{
				$maxlimit = $app['config']->get('maxlimit');

				if(($touser['saldo'] + $transaction['amount']) > $maxlimit && $maxlimit !== '')
				{
					$err = 'De ';
					$err .= $group_id == 'self' ? 'bestemmeling (Aan ' . $app['type_template']->get('code') . ')' : 'interletsrekening (in deze groep)';
					$err .= ' heeft zijn maximum limiet bereikt. ';
					$err .= 'Het saldo bedraagt ' . $touser['saldo'] . ' ' . $currency;
					$err .= ' en de maximum ';
					$err .= 'groepslimiet bedraagt ' . $maxlimit . ' ' . $currency . '.';
					$errors[] = $err;
				}
			}
			else
			{
				if(($touser['saldo'] + $transaction['amount']) > $touser['maxlimit'])
				{
					$err = 'De ';
					$err .= $group_id == 'self' ? 'bestemmeling (Aan ' . $app['type_template']->get('code') . ')' : 'interletsrekening (in deze groep)';
					$err .= ' heeft zijn maximum limiet bereikt. ';
					$err .= 'Het saldo bedraagt ' . $touser['saldo'] . ' ' . $currency;
					$err .= ' en de maximum ';
					$err .= 'limiet bedraagt ' . $touser['maxlimit'] . ' ' . $currency . '.';
					$errors[] = $err;
				}
			}
		}

		if($group_id == 'self'
			&& !$s_admin
			&& !($touser['status'] == '1' || $touser['status'] == '2')
			&& !count($errors))
		{
			$errors[] = 'De bestemmeling (Aan ' . $app['type_template']->get('code') . ') is niet actief';
		}

		if ($s_user && !count($errors))
		{
			$balance_eq = $app['config']->get('balance_equilibrium');

			if (($fromuser['status'] == 2) && (($fromuser['saldo'] - $amount) < $balance_eq))
			{
				$errors[] = 'Als uitstapper kan je geen ' . $amount . ' ' . $app['config']->get('currency') . ' uitgeven.';
			}

			if (($touser['status'] == 2) && (($touser['saldo'] + $amount) > $balance_eq))
			{
				$dest = ($group_id == 'self') ? 'De bestemmeling (Aan ' . $app['type_template']->get('code') . ')' : 'De interletsrekening van de letsgroep';
				$errors[] = $dest . ' is uitstapper en kan geen ' . $amount . ' ' . $app['config']->get('currency') . ' ontvangen.';
			}
		}

		if ($error_token = $app['form_token']->get_error())
		{
			$errors[] = $error_token;
		}

		$contact_admin = ($s_admin) ? '' : ' Contacteer een admin.';

		if (isset($group['url']))
		{
			$group_domain = strtolower(parse_url($group['url'], PHP_URL_HOST));
		}
		else
		{
			$group_domain = false;
		}

		if(count($errors))
		{
			$app['alert']->error($errors);
		}
		else if ($group_id == 'self')
		{
			if ($id = insert_transaction($transaction))
			{
				$transaction['id'] = $id;
				mail_transaction($transaction);
				$app['alert']->success('Transactie opgeslagen');
			}
			else
			{
				$app['alert']->error('Gefaalde transactie');
			}

			cancel();
		}
		else if ($group['apimethod'] == 'mail')
		{
			if ($id = insert_transaction($transaction))
			{
				$transaction['id'] = $id;
				$transaction['letscode_to'] = $letscode_to;

				mail_mailtype_interlets_transaction($transaction);

				$app['alert']->success('Interlets transactie opgeslagen (verwerking per mail).');
			}
			else
			{
				$app['alert']->error('Gefaalde interlets transactie');
			}

			cancel();
		}
		else if ($group['apimethod'] != 'elassoap')
		{
			$app['alert']->error('Interlets groep ' . $group['groupname'] . ' heeft geen geldige api methode.' . $contact_admin);

			cancel();
		}
		else if (!$group_domain)
		{
			$app['alert']->error('Geen url voor interlets groep ' . $group['groupname'] . '. ' . $contact_admin);

			cancel();
		}
		else if (!$app['groups']->get_schema($group_domain))
		{
			// The interlets group uses eLAS or is on another server

			if (!$group['remoteapikey'])
			{
				$errors[] = 'Geen apikey voor deze interlets groep ingesteld.' . $contact_admin;
			}

			if (!$group['presharedkey'])
			{
				$errors[] = 'Geen preshared key voor deze interlets groep ingesteld.' . $contact_admin;
			}

			if (!$group['myremoteletscode'])
			{
				$errors[] = 'Geen remote letscode ingesteld voor deze interlets groep.' . $contact_admin;
			}

			$currencyratio = $app['config']->get('currencyratio');

			if (!$currencyratio || !ctype_digit((string) $currencyratio) || $currencyratio < 1)
			{
				$errors[] = 'De currencyratio is niet correct ingesteld. ' . $contact_admin;
			}

			if (strlen($letscode_to))
			{
				$active_users = $app['cache']->get($group['domain'] . '_typeahead_data');

				$user_letscode_found = false;

				foreach ($active_users as $active_user)
				{
					if ($active_user['c'] == $letscode_to)
					{
						$real_name_to = $active_user['n'];
						$user_letscode_found = true;
						break;
					}
				}

				if ($user_letscode_found)
				{
					if(!$real_name_to)
					{
						$errors[] = 'Er werd geen naam gevonden voor de gebruiker van de interlets groep.';
					}
				}
				else
				{
					$errors[] = 'Er werd geen gebruiker gevonden met ' . $app['type_template']->get('code') . ' ' . $letscode_to;
				}
			}

			if (count($errors))
			{
				$app['alert']->error($errors);
				cancel();
			}

			$trans = $transaction;

			$trans['amount'] = $trans['amount'] / $currencyratio;
			$trans['amount'] = (float) $trans['amount'];
			$trans['amount'] = round($trans['amount'], 5);

			$trans['letscode_to'] = $letscode_to;

			$soapurl = ($group['elassoapurl']) ?: $group['url'] . '/soap';
			$soapurl .= '/wsdlelas.php?wsdl';

			$client = new nusoap_client($soapurl, true);

			$error = $client->getError();

			if ($error)
			{
				$app['alert']->error('eLAS soap error: ' . $error . ' <br>' . $contact_admin);
				cancel();
			}

			$result = $client->call('dopayment', [
				'apikey' 		=> $group['remoteapikey'],
				'from' 			=> $group['myremoteletscode'],
				'real_from' 	=> link_user($fromuser, false, false),
				'to' 			=> $letscode_to,
				'description' 	=> $trans['description'],
				'amount' 		=> $trans['amount'],
				'transid' 		=> $trans['transid'],
				'signature' 	=> sign_transaction($trans, trim($group['presharedkey'])),
			]);

			$error = $client->getError();

			if ($error)
			{
				$app['alert']->error('eLAS soap error: ' . $error . ' <br>' . $contact_admin);
				cancel();
			}

			if ($result == 'OFFLINE')
			{
				$errors[] = 'De andere letsgroep is offline. Probeer het later opnieuw. ';
			}

			if ($result == 'FAILED')
			{
				$errors[] = 'De interlets transactie is gefaald.' . $contact_admin;
			}

			if ($result == 'SIGFAIL')
			{
				$errors[] = 'De signatuur van de interlets transactie is gefaald. ' . $contact_admin;
			}

			if ($result == 'DUPLICATE')
			{
				$errors[] = 'De transactie bestaat reeds in de andere letsgroep. ' . $contact_admin;
			}

			if ($result == 'NOUSER')
			{
				$errors[] = 'De gebruiker in de interletsgroep werd niet gevonden. ';
			}

			if ($result == 'APIKEYFAIL')
			{
				$errors[] = 'De apikey is niet correct. ' . $contact_admin;
			}

			if (!count($errors) && $result != 'SUCCESS')
			{
				$errors[] = 'De interlets transactie kon niet verwerkt worden. ' . $contact_admin;
			}

			if (count($errors))
			{
				$app['alert']->error($errors);
				cancel();
			}

			$transaction['real_to'] = $letscode_to . ' ' . $real_name_to;

			$app['monolog']->debug('insert transation: --  ' . http_build_query($transaction) . ' --');

			$id = insert_transaction($transaction);

			if (!$id)
			{
				$subject = 'Interlets FAILURE!';
				$text = 'WARNING: LOCAL COMMIT OF TRANSACTION ' . $transaction['transid'] . ' FAILED!!!  This means the transaction is not balanced now!';
				$text .= ' group:' . $group['groupname'];

				$app['queue.mail']->queue([
					'to' => 'admin',
					'subject' => $subject,
					'text' => $text,
				]);

				$app['alert']->error('De lokale commit van de interlets transactie is niet geslaagd. ' . $contact_admin);
				cancel();
			}

			$transaction['id'] = $id;

			mail_transaction($transaction);

			$app['alert']->success('De interlets transactie werd verwerkt.');
			cancel();
		}
		else
		{
			// the interlets group is on the same server (eLAND)

			$remote_schema = $app['groups']->get_schema($group_domain);

			$to_remote_user = $app['db']->fetchAssoc('select *
				from ' . $remote_schema . '.users
				where letscode = ?', [$letscode_to]);

			if (!$to_remote_user)
			{
				$errors[] = 'De interlets gebruiker (bestemmeling "Aan letscode") bestaat niet.';
			}
			else if (!in_array($to_remote_user['status'], ['1', '2']))
			{
				$errors[] = 'De interlets gebruiker (bestemmeling "Aan letscode") is niet actief.';
			}

			$remote_group = $app['db']->fetchAssoc('select *
				from ' . $remote_schema . '.letsgroups
				where url = ?', [$app['base_url']]);

			if (!$remote_group && !count($errors))
			{
				$errors[] = 'De remote interlets groep heeft deze letsgroep ('. $app['config']->get('systemname') . ') niet geconfigureerd.';
			}

			if (!$remote_group['localletscode'] && !count($errors))
			{
				$errors[] = 'Er is geen interlets account gedefiniëerd in de remote interlets groep.';
			}

			$remote_interlets_account = $app['db']->fetchAssoc('select *
				from ' . $remote_schema . '.users
				where letscode = ?', [$remote_group['localletscode']]);

			if (!$remote_interlets_account && !count($errors))
			{
				$errors[] = 'Er is geen interlets account in de remote interlets group.';
			}

			if ($remote_interlets_account['accountrole'] !== 'interlets' && !count($errors))
			{
				$errors[] = 'Het interlets account in de remote interlets groep heeft geen juiste rol. Deze moet van het type interlets zijn.';
			}

			if (!in_array($remote_interlets_account['status'], [1, 2, 7]) && !count($errors))
			{
				$errors[] = 'Het interlets account in de remote interlets groep heeft geen juiste status. Deze moet van het type extern, actief of uitstapper zijn.';
			}

			$remote_currency = $app['config']->get('currency', $remote_schema);
			$remote_currencyratio = $app['config']->get('currencyratio', $remote_schema);
			$remote_balance_eq = $app['config']->get('balance_equilibrium', $remote_schema);
			$currencyratio = $app['config']->get('currencyratio');

			if ((!$currencyratio || !ctype_digit((string) $currencyratio) || $currencyratio < 1)
				&& !count($errors))
			{
				$errors[] = 'De currencyratio is niet correct ingesteld. ' . $contact_admin;
			}

			if ((!$remote_currencyratio ||
				!ctype_digit((string) $remote_currencyratio)
				|| $remote_currencyratio < 1) && !count($errors))
			{
				$errors[] = 'De currencyratio van de andere groep is niet correct ingesteld. ' . $contact_admin;
			}

			$remote_amount = round(($transaction['amount'] * $remote_currencyratio) / $currencyratio);

			if (($remote_amount < 1) && !count($errors))
			{
				$errors[] = 'Het bedrag is te klein want het kan niet uitgedrukt worden in de gebruikte munt van de interletsgroep.';
			}

			if (!count($errors))
			{
				if ($remote_interlets_account['minlimit'] === -999999999)
				{
					$minlimit = $app['config']->get('minlimit', $remote_schema);

					if(($remote_interlets_account['saldo'] - $remote_amount) < $minlimit && $minlimit !== '')
					{
						$err = 'Het interlets account van de remote interlets groep heeft onvoldoende saldo ';
						$err .= 'beschikbaar. Het saldo bedraagt ' . $remote_interlets_account['saldo'] . ' ';
						$err .= $remote_currency . ' ';
						$err .= 'en de remote minimum groepslimiet bedraagt ' . $minlimit . ' ';
						$err .= $remote_currency . '.';
						$errors[] = $err;
					}
				}
				else
				{
					if(($remote_interlets_account['saldo'] - $remote_amount) < $remote_interlets_account['minlimit'])
					{
						$err = 'Het interlets account van de remote interlets groep heeft onvoldoende saldo ';
						$err .= 'beschikbaar. Het saldo bedraagt ' . $remote_interlets_account['saldo'] . ' ';
						$err .= $remote_currency . ' ';
						$err .= 'en de remote minimum limiet bedraagt ' . $remote_interlets_account['minlimit'] . ' ';
						$err .= $remote_currency . '.';
						$errors[] = $err;
					}
				}
			}

			if (($remote_interlets_account['status'] == 2)
				&& (($remote_interlets_account['saldo'] - $remote_amount) < $remote_balance_eq)
				&& !count($errors))
			{
				$errors[] = 'Het remote interlets account heeft de status uitstapper en kan geen ' . $remote_amount . ' ' . $remote_currency . ' uitgeven (' . $amount . ' ' . $app['config']->get('currency') . ').';
			}

			if (!count($errors))
			{
				if ($to_remote_user['maxlimit'] === 999999999)
				{
					$maxlimit = $app['config']->get('maxlimit', $remote_schema);

					if(($to_remote_user['saldo'] + $remote_amount) > $maxlimit && $maxlimit !== '')
					{
						$err = 'De bestemmeling in de andere groep ';
						$err .= 'heeft de maximum groepslimiet bereikt. ';
						$err .= 'Het saldo bedraagt ' . $to_remote_user['saldo'] . ' ' . $remote_currency;
						$err .= ' en de maximum ';
						$err .= 'groepslimiet bedraagt ' . $maxlimit . ' ' . $remote_currency . '.';
						$errors[] = $err;
					}
				}
				else
				{
					if(($to_remote_user['saldo'] + $remote_amount) > $to_remote_user['maxlimit'])
					{
						$err = 'De bestemmeling in de andere groep ';
						$err .= 'heeft de maximum limiet bereikt. ';
						$err .= 'Het saldo bedraagt ' . $to_remote_user['saldo'] . ' ' . $remote_currency;
						$err .= ' en de maximum ';
						$err .= 'limiet bedraagt ' . $to_remote_user['maxlimit'] . ' ' . $remote_currency . '.';
						$errors[] = $err;
					}
				}
			}

			if (($to_remote_user['status'] == 2)
				&& (($to_remote_user['saldo'] + $remote_amount) > $remote_balance_eq)
				&& !count($errors))
			{
				$errors[] = 'De remote bestemmeling (Aan letscode) is uitstapper en kan geen ' . $remote_amount . ' ' . $remote_currency . ' ontvangen (' . $amount . ' ' . $app['config']->get('currency') . ').';
			}

			if (count($errors))
			{
				$app['alert']->error($errors);
//				cancel();
			}
			else
			{
			//
				$transaction['creator'] = ($s_master) ? 0 : $s_id;
				$transaction['cdate'] = gmdate('Y-m-d H:i:s');
				$transaction['real_to'] = $to_remote_user['letscode'] . ' ' . $to_remote_user['name'];

				$app['db']->beginTransaction();

				try
				{
					$app['db']->insert('transactions', $transaction);
					$id = $app['db']->lastInsertId('transactions_id_seq');
					$app['db']->executeUpdate('update users
						set saldo = saldo + ? where id = ?',
						[$transaction['amount'], $transaction['id_to']]);
					$app['db']->executeUpdate('update users
						set saldo = saldo - ? where id = ?',
						[$transaction['amount'], $transaction['id_from']]);

					$trans_org = $transaction;
					$trans_org['id'] = $id;

					$transaction['creator'] = 0;
					$transaction['amount'] = $remote_amount;
					$transaction['id_from'] = $remote_interlets_account['id'];
					$transaction['id_to'] = $to_remote_user['id'];
					$transaction['real_from'] = link_user($fromuser['id'], false, false);
					unset($transaction['real_to']);

					$app['db']->insert($remote_schema . '.transactions', $transaction);
					$id = $app['db']->lastInsertId($remote_schema . '.transactions_id_seq');
					$app['db']->executeUpdate('update ' . $remote_schema . '.users
						set saldo = saldo + ? where id = ?',
						[$remote_amount, $transaction['id_to']]);
					$app['db']->executeUpdate('update ' . $remote_schema . '.users
						set saldo = saldo - ? where id = ?',
						[$transaction['amount'], $transaction['id_from']]);
					$transaction['id'] = $id;

					$app['db']->commit();

				}
				catch(Exception $e)
				{
					$app['db']->rollback();
					$app['alert']->error('Transactie niet gelukt.');
					throw $e;
					exit;
				}

				$app['user_cache']->clear($fromuser['id']);
				$app['user_cache']->clear($touser['id']);

				$app['user_cache']->clear($remote_interlets_account['id'], $remote_schema);
				$app['user_cache']->clear($to_remote_user['id'], $remote_schema);

				mail_transaction($trans_org);
				mail_transaction($transaction, $remote_schema);

				$app['monolog']->info('direct interlets transaction ' . $transaction['transid'] . ' amount: ' .
					$amount . ' from user: ' .  link_user($fromuser['id'], false, false) .
					' to user: ' . link_user($touser['id'], false, false));

				$app['monolog']->info('direct interlets transaction (receiving) ' . $transaction['transid'] .
					' amount: ' . $remote_amount . ' from user: ' . $remote_interlets_account['letscode'] . ' ' .
					$remote_interlets_account['name'] . ' to user: ' . $to_remote_user['letscode'] . ' ' .
					$to_remote_user['name'], ['schema' => $remote_schema]);

				$app['autominlimit']->init()
					->process($transaction['id_from'], $transaction['id_to'], $transaction['amount']);

				$app['alert']->success('Interlets transactie uitgevoerd.');
				cancel();
			}
		}

		$transaction['letscode_to'] = $_POST['letscode_to'];
		$transaction['letscode_from'] = ($s_admin || $s_master) ? $_POST['letscode_from'] : link_user($s_id, false, false);
	}
	else
	{
		//GET form

		$transid = generate_transid();

		$app['predis']->set($redis_transid_key, $transid);
		$app['predis']->expire($redis_transid_key, 3600);

		$transaction = [
			'date'			=> gmdate('Y-m-d H:i:s'),
			'letscode_from'	=> ($s_master) ? '' : link_user($s_id, false, false),
			'letscode_to'	=> '',
			'amount'		=> '',
			'description'	=> '',
			'transid'		=> $transid,
		];

		$group_id = 'self';
		$to_schema_table = '';

		if ($tus)
		{
			if ($app['groups']->get_host($tus))
			{
				$host_from_tus = $app['groups']->get_host($tus);

				$group_id = $app['db']->fetchColumn('select id
					from letsgroups
					where url = ?', [$app['protocol'] . $host_from_tus]);
				$to_schema_table = $tus . '.';
			}
		}

		if ($mid && !($tus xor $host_from_tus))
		{
			$row = $app['db']->fetchAssoc('SELECT
					m.content, m.amount, m.id_user, u.letscode, u.name, u.status
				from ' . $to_schema_table . 'messages m,
					'. $to_schema_table . 'users u
				where u.id = m.id_user
					and m.id = ?', [$mid]);

			if (($s_admin && !$tus) || $row['status'] == 1 || $row['status'] == 2)
			{
				$transaction['letscode_to'] = $row['letscode'] . ' ' . $row['name'];
				$transaction['description'] =  substr('#m.' . $to_schema_table . $mid . ' ' . $row['content'], 0, 60);
				$amount = $row['amount'];
				if ($tus)
				{
					$amount = round(($app['config']->get('currencyratio') * $amount) / $app['config']->get('currencyratio', $tus));
				}
				$transaction['amount'] = $amount;
				$tuid = $row['tuid'];
			}
		}
		else if ($tuid)
		{
			$to_user = $app['user_cache']->get($tuid, ((isset($host_from_tus)) ? $tus : false));

			if (($s_admin && !$tus) || $to_user['status'] == 1 || $to_user['status'] == 2)
			{
				$transaction['letscode_to'] = $to_user['letscode'] . ' ' . $to_user['name'];
			}
		}

		if ($fuid && $s_admin && ($fuid != $tuid))
		{
			$from_user = $app['user_cache']->get($fuid);
			$transaction['letscode_from'] = $from_user['letscode'] . ' ' . $from_user['name'];
		}

		if ($tuid == $s_id && !$fuid && $tus != $app['this_group']->get_schema())
		{
			$transaction['letscode_from'] = '';
		}
	}

	$app['assets']->add(['typeahead', 'typeahead.js', 'transaction_add.js']);

	$balance = $session_user['saldo'];

	$groups = [];

	$groups[] = [
		'groupname' => $app['config']->get('systemname') . ' (eigen groep)',
		'id'		=> 'self',
	];

	if (count($eland_interlets_groups))
	{
		$urls = [];

		foreach ($eland_interlets_groups as $h)
		{
			$urls[] = $app['protocol'] . $h;
		}

		$eland_groups = $app['db']->executeQuery('select id, groupname, url
			from letsgroups
			where apimethod <> \'internal\'
				and url in (?)',
				[$urls],
				[\Doctrine\DBAL\Connection::PARAM_STR_ARRAY]);

		foreach ($eland_groups as $g)
		{
			$groups[] = $g;
		}
	}

	if (count($elas_interlets_groups))
	{
		$ids = [];

		foreach ($elas_interlets_groups as $key => $name)
		{
			$ids[] = $key;
		}

		$elas_groups = $app['db']->executeQuery('select id, groupname, url
			from letsgroups
			where apimethod <> \'internal\'
				and id in (?)',
				[$ids],
				[\Doctrine\DBAL\Connection::PARAM_INT_ARRAY]);

		foreach ($elas_groups as $g)
		{
			$groups[] = $g;
		}
	}

	$groups_en = count($groups) > 1 && $app['config']->get('currencyratio') > 0 ? true : false;

	$top_buttons .= aphp('transactions', [], 'Lijst', 'btn btn-default', 'Transactielijst', 'exchange', true);

	if (!$s_master)
	{
		$top_buttons .= aphp('transactions', ['uid' => $s_id], 'Mijn transacties', 'btn btn-default', 'Mijn transacties', 'user', true);
	}

	$h1 = 'Nieuwe transactie';
	$fa = 'exchange';

	include __DIR__ . '/include/header.php';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<form  method="post" class="form-horizontal" autocomplete="off">';

	echo '<div class="form-group"';
	echo $s_admin ? '' : ' disabled" ';
	echo '>';
	echo '<label for="letscode_from" class="col-sm-2 control-label">';
	echo $s_admin ? '<span class="label label-info">Admin</span> ' : '';
	echo 'Van ';
	echo $app['type_template']->get('code');
	echo '</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="letscode_from" name="letscode_from" ';
	echo 'data-typeahead-source="';
	echo $groups_en ? 'group_self' : 'letscode_to';
	echo '" ';
	echo 'value="' . $transaction['letscode_from'] . '" required';
	echo $s_admin ? '' : ' disabled';
	echo '>';

/*
	echo '<ul class="account-info">';

	echo '<li class="info-balance">Huidige saldo: <span class="num">';
	echo '</span> <span class="cur">';
	echo $app['config']->get('currency') . '</span></li>';

	echo '<li class="info-minlimit">Minimum limiet: <span class="num">';
	echo '</span> <span class="cur">';
	echo $app['config']->get('currency') . '</span></li>';

	echo '<li class="info-equilibrium text-danger">Uitstapsaldo: ';
	echo '<span class="num">';
	echo $app['config']->get('balance_equilibrium');
	echo '</span> <span class="cur">';
	echo $app['config']->get('currency') . '</span></li>';

	echo '<li class="info-group-minlimit">Minimum groepslimiet: ';
	echo '<span class="num">';
	echo $app['config']->get('minlimit');
	echo '</span> <span class="cur">';
	echo $app['config']->get('currency') . '</span></li>';

	echo '</ul>';
*/
	echo '</div>';
	echo '</div>';

	if ($groups_en)
	{
		echo '<div class="form-group">';
		echo '<label for="group_id" class="col-sm-2 control-label">Aan letsgroep</label>';
		echo '<div class="col-sm-10">';
		echo '<select type="text" class="form-control" id="group_id" name="group_id">';

		foreach ($groups as $l)
		{
			echo '<option value="' . $l['id'] . '" ';

			if ($l['id'] == 'self')
			{
				echo 'id="group_self" ';

				if ($s_admin)
				{
					$typeahead = ['users_active', 'users_inactive', 'users_ip', 'users_im'];
				}
				else
				{
					$typeahead = 'users_active';
				}

				$typeahead = $app['typeahead']->get($typeahead);

				$sch = $app['this_group']->get_schema();
			}
			else
			{
				$domain = strtolower(parse_url($l['url'], PHP_URL_HOST));

				$typeahead = $app['typeahead']->get('users_active', $domain, $l['id']);

				$sch = $app['groups']->get_schema($domain);
			}

			if ($sch)
			{
				echo ' data-newuserdays="' . $app['config']->get('newuserdays', $sch) . '"';
				echo ' data-minlimit="' . $app['config']->get('minlimit', $sch) . '"';
				echo ' data-maxlimit="' . $app['config']->get('maxlimit', $sch) . '"';
				echo ' data-currency="' . $app['config']->get('currency', $sch) . '"';
				echo ' data-currencyratio="' . $app['config']->get('currencyratio', $sch) . '"';
				echo ' data-balance-equilibrium="' . $app['config']->get('balance_equilibrium', $sch) . '"';
			}

			echo ' data-typeahead="' . $typeahead . '"';
			echo ($l['id'] == $group_id) ? ' selected="selected"' : '';
			echo '>' . htmlspecialchars($l['groupname'], ENT_QUOTES) . '</option>';
		}

		echo '</select>';
/*
		echo '<ul class="account-info">';

		echo '<li class="info-balance">Huidige saldo: <span class="num">';
		echo '</span> <span class="cur">';
		echo $app['config']->get('currency') . '</span></li>';

		echo '<li class="info-minlimit">Minimum limiet: <span class="num">';
		echo '</span> <span class="cur">';
		echo $app['config']->get('currency') . '</span></li>';

		echo '<li class="info-equilibrium text-danger">Uitstapsaldo: ';
		echo '<span class="num">';
		echo $app['config']->get('balance_equilibrium');
		echo '</span> <span class="cur">';
		echo $app['config']->get('currency') . '</span></li>';

		echo '<li class="info-group-minlimit">Minimum groepslimiet: ';
		echo '<span class="num">';
		echo $app['config']->get('minlimit');
		echo '</span> <span class="cur">';
		echo $app['config']->get('currency') . '</span></li>';

		echo '</ul>';
*/
		echo '</div>';
		echo '</div>';
	}
	else
	{
		if ($s_admin)
		{
			$typeahead = ['users_active', 'users_inactive', 'users_ip', 'users_im'];
		}
		else
		{
			$typeahead = 'users_active';
		}

		$typeahead = $app['typeahead']->get($typeahead);

		echo '<input type="hidden" id="group_id" name="group_id" value="self">';
	}

	echo '<div class="form-group">';
	echo '<label for="letscode_to" class="col-sm-2 control-label">Aan ';
	echo $app['type_template']->get('code');
	echo '</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="letscode_to" name="letscode_to" ';

	if ($groups_en)
	{
		echo 'data-typeahead-source="group_id" ';
	}
	else
	{
		echo 'data-typeahead="' . $typeahead . '" ';
	}

	echo 'data-newuserdays="' . $app['config']->get('newuserdays') . '" ';

	echo 'value="' . $transaction['letscode_to'] . '" required>';

	echo '<ul class="account-info">';

	echo '<li>Dit veld geeft autosuggesties door naam of ' . $app['type_template']->get('code') . ' te typen. ';
	echo (count($groups) > 1) ? 'Kies eerst de juiste letsgroep om de juiste suggesties te krijgen.' : '';
	echo '</li>';

/*
	echo '<li class="info-balance">Huidige saldo: <span class="num">45';
	echo '</span> <span class="cur">';
	echo $app['config']->get('currency') . '</span></li>';

	echo '<li class="info-maxlimit">Maximum limiet: <span class="num">';
	echo '</span> <span class="cur">';
	echo $app['config']->get('currency') . '</span></li>';

	echo '<li class="info-equilibrium text-danger">Uitstapsaldo: ';
	echo '<span class="num">';
	echo $app['config']->get('balance_equilibrium');
	echo '</span> <span class="cur">';
	echo $app['config']->get('currency') . '</span></li>';

	echo '<li class="info-group-maxlimit">Maximum groepslimiet: ';
	echo '<span class="num">';
	echo $app['config']->get('maxlimit');
	echo '</span> <span class="cur">';
	echo $app['config']->get('currency') . '</span></li>';
*/
	echo '</ul>';

	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="amount" class="col-sm-2 control-label">Aantal</label>';
//	echo '<div class="col-sm-10">';

/*
	echo '<div class="col-sm-12">';
	echo '<div class="input-group margin-bottom">';
	echo '<span class="input-group-addon">';
	echo '<i class="fa fa-search"></i>';
	echo '</span>';
	echo '<input type="text" class="form-control" id="q" value="' . $q . '" name="q" placeholder="Zoekterm">';
	echo '</div>';
	echo '</div>';
*/

	echo '<div class="col-sm-10" id="amount_container">';
	echo '<div class="input-group">';

	echo '<span class="input-group-addon">';
	echo $app['config']->get('currency');
	echo '</span>';

	echo '<input type="number" class="form-control" id="amount" name="amount" ';
	echo 'value="' . $transaction['amount'] . '" min="1" required>';

	echo '</div>';

	echo '<ul>';

	if ($app['config']->get('currencyratio') > 0)
	{
		echo '<li id="info_ratio">Valuatie: <span class="num">';
		echo $app['config']->get('currencyratio');
		echo '</span> per uur</li>';
	}

/*
	echo '<li id="info_amount">Maximum <span class="num"></span> ';
	echo '<span class="cur">' . $app['config']->get('currency') . '</span></li>';

	echo '<li id="info_amount_unknown">Het maximum is niet gekend wegens ';
	echo 'overschrijving naar een externe niet-eLAND installatie.</li>';
*/

	echo '</ul>';

	echo '</div>';

	echo '<div class="col-sm-5 collapse" id="remote_amount_container">';
	echo '<div class="input-group">';
	echo '<span class="input-group-addon">';
	echo '</span>';
	echo '<input type="number" class="form-control" ';
	echo 'id="remote_amount" name="remote_amount" ';
	echo 'value="">';

	echo '</div>';

	echo '<ul>';

	echo '<li id="info_ratio">Valuatie: <span class="num"></span> per uur</li>';


/*	echo '<li id="info_remote_amount">Maximum <span class="num"></span> ';
	echo '<span class="cur"></span></li></ul>';
*/
	echo '</div>';

	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="description" class="col-sm-2 control-label">Omschrijving</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="description" name="description" ';
	echo 'value="' . $transaction['description'] . '" required maxlength="60">';
	echo '</div>';
	echo '</div>';

	echo aphp('transactions', [], 'Annuleren', 'btn btn-default') . '&nbsp;';
	echo '<input type="submit" name="zend" value="Overschrijven" class="btn btn-success">';
	$app['form_token']->generate();
	echo '<input type="hidden" name="transid" value="' . $transaction['transid'] . '">';

	echo '</form>';
	echo '</div>';
	echo '</div>';

	echo '<ul><small><i>';

	if ($s_admin)
	{
		echo '<li>Admins kunnen over en onder limieten gaan in de eigen groep.</li>';
	}

	echo '</i></small></ul>';

	include __DIR__ . '/include/footer.php';
	exit;
}

/*
 * interlets accounts schemas needed for interlinking users.
 */

$interlets_accounts_schemas = $app['interlets_groups']->get_eland_accounts_schemas($app['this_group']->get_schema());

$s_inter_schema_check = array_merge($eland_interlets_groups, [$s_schema => true]);

/**
 * show a transaction
 */

if ($id || $edit)
{
	$id = ($edit) ? $edit : $id;

	$transaction = $app['db']->fetchAssoc('select t.*
		from transactions t
		where t.id = ?', [$id]);

	$inter_schema = false;

	if (isset($interlets_accounts_schemas[$transaction['id_from']]))
	{
		$inter_schema = $interlets_accounts_schemas[$transaction['id_from']];
	}
	else if (isset($interlets_accounts_schemas[$transaction['id_to']]))
	{
		$inter_schema = $interlets_accounts_schemas[$transaction['id_to']];
	}

	if ($inter_schema)
	{
		$inter_transaction = $app['db']->fetchAssoc('select t.*
			from ' . $inter_schema . '.transactions t
			where t.transid = ?', [$transaction['transid']]);
	}
	else
	{
		$inter_transaction = false;
	}
}

/**
 * edit description
 */

if ($edit)
{
	if (!$s_admin)
	{
		$app['alert']->error('Je hebt onvoldoende rechten om een omschrijving van een transactie aan te passen.');
		cancel($edit);
	}

	if (!$inter_transaction && ($transaction['real_from'] || $transaction['real_to']))
	{
		$app['alert']->error('De omschrijving van een transactie naar een eLAS installatie kan niet aangepast worden.');
		cancel($edit);
	}

	if ($submit)
	{
		$description = trim($_POST['description'] ?? '');

		if ($error_token = $app['form_token']->get_error())
		{
			$errors[] = $error_token;
		}

		if (strlen($description) > 60)
		{
			$errors[] = 'De omschrijving mag maximaal 60 tekens lang zijn.';
		}

		if (!$description)
		{
			$errors[]= 'De omschrijving is niet ingevuld';
		}

		if (!count($errors))
		{
			$app['db']->update('transactions', ['description' => $description], ['id' => $edit]);

			if ($inter_transaction)
			{
				$app['db']->update($inter_schema . '.transactions', ['description' => $description], ['id' => $inter_transaction['id']]);
			}

			$app['monolog']->info('Transaction description edited from "' . $transaction['description'] .
				'" to "' . $description . '", transid: ' . $transaction['transid']);

			$app['alert']->success('Omschrijving transactie aangepast.');

			cancel($id);
		}

		$app['alert']->error($errors);
	}

	$top_buttons .= aphp('transactions', [], 'Lijst', 'btn btn-default', 'Transactielijst', 'exchange', true);

	$h1 = 'Omschrijving transactie aanpassen';
	$fa = 'exchange';

	include __DIR__ . '/include/header.php';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<form  method="post" class="form-horizontal" autocomplete="off">';

// copied from "show a transaction"

	echo '<dl class="dl-horizontal">';

	echo '<dt>Tijdstip</dt>';
	echo '<dd>';
	echo $app['date_format']->get($transaction['cdate']);
	echo '</dd>';

	echo '<br>';

	echo '<dt>Transactie ID</dt>';
	echo '<dd>';
	echo $transaction['transid'];
	echo '</dd>';

	echo '<br>';

	if ($transaction['real_from'])
	{
		echo '<dt>Van interlets account</dt>';
		echo '<dd>';
		echo link_user($transaction['id_from'], false, $s_admin);
		echo '</dd>';

		echo '<dt>Van interlets gebruiker</dt>';
		echo '<dd>';
		echo '<span class="btn btn-default btn-xs"><i class="fa fa-share-alt"></i></span> ';

		if ($inter_transaction)
		{
			echo link_user($inter_transaction['id_from'],
				$inter_schema,
				$s_inter_schema_check[$inter_schema]);
		}
		else
		{
			echo $transaction['real_from'];
		}

		echo '</dd>';
	}
	else
	{
		echo '<dt>Van gebruiker</dt>';
		echo '<dd>';
		echo link_user($transaction['id_from']);
		echo '</dd>';
	}

	echo '<br>';

	if ($transaction['real_to'])
	{
		echo '<dt>Naar interlets account</dt>';
		echo '<dd>';
		echo link_user($transaction['id_to'], false, $s_admin);
		echo '</dd>';

		echo '<dt>Naar interlets gebruiker</dt>';
		echo '<dd>';
		echo '<span class="btn btn-default btn-xs"><i class="fa fa-share-alt"></i></span> ';

		if ($inter_transaction)
		{
			echo link_user($inter_transaction['id_to'],
				$inter_schema,
				$s_inter_schema_check[$inter_schema]);
		}
		else
		{
			echo $transaction['real_to'];
		}

		echo '</dd>';
	}
	else
	{
		echo '<dt>Naar gebruiker</dt>';
		echo '<dd>';
		echo link_user($transaction['id_to']);
		echo '</dd>';
	}

	echo '<br>';

	echo '<dt>Waarde</dt>';
	echo '<dd>';
	echo $transaction['amount'] . ' ' . $app['config']->get('currency');
	echo '</dd>';

	echo '<br>';

	echo '<dt>Omschrijving</dt>';
	echo '<dd>';
	echo $transaction['description'];
	echo '</dd>';

	echo '</dl>';

//
	echo '<div class="form-group">';
	echo '<label for="description" class="col-sm-2 control-label">Nieuwe omschrijving</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="description" name="description" ';
	echo 'value="' . $transaction['description'] . '" required maxlength="60">';
	echo '</div>';
	echo '</div>';

	echo aphp('transactions', ['id' => $edit], 'Annuleren', 'btn btn-default') . '&nbsp;';
	echo '<input type="submit" name="zend" value="Aanpassen" class="btn btn-primary">';
	$app['form_token']->generate();
	echo '<input type="hidden" name="transid" value="' . $transaction['transid'] . '">';

	echo '</form>';
	echo '</div>';
	echo '</div>';

	echo '<ul><small><i>';
	echo '<li>Omdat dat transacties binnen het netwerk zichtbaar zijn voor iedereen kan ';
	echo 'de omschrijving aangepast worden door admins in het geval deze ongewenste informatie bevat (bvb. een opmerking die beledigend is).</li>';
	echo '<li>Pas de omschrijving van een transactie enkel aan wanneer het echt noodzakelijk is! Dit om verwarring te vermijden.</li>';
	echo '<li>Transacties kunnen nooit ongedaan gemaakt worden. Doe een tegenboeking bij vergissing.</li>';

	if ($app['config']->get('currencyratio') > 0)
	{
		echo '<li>Valuatie: <span class="sum">';
		echo $app['config']->get('currencyratio');
		echo '</span> ' . $app['config']->get('currency') . ' per LETS-uur.</li>';
	}

	echo '</i></small></ul>';

	include __DIR__ . '/include/footer.php';
	exit;
}

/**
 * show a transaction
 */

if ($id)
{
	$next = $app['db']->fetchColumn('select id
		from transactions
		where id > ?
		order by id asc
		limit 1', [$id]);

	$prev = $app['db']->fetchColumn('select id
		from transactions
		where id < ?
		order by id desc
		limit 1', [$id]);

	if ($s_user || $s_admin)
	{
		$top_buttons .= aphp('transactions', ['add' => 1], 'Toevoegen', 'btn btn-success', 'Transactie toevoegen', 'plus', true);
	}

	if ($s_admin && ($inter_transaction || !($transaction['real_from'] || $transaction['real_to'])))
	{
		$top_buttons .= aphp('transactions', ['edit' => $id], 'Aanpassen', 'btn btn-primary', 'Omschrijving aanpassen', 'pencil', true);
	}

	if ($prev)
	{
		$top_buttons .= aphp('transactions', ['id' => $prev], 'Vorige', 'btn btn-default', 'Vorige', 'chevron-down', true);
	}

	if ($next)
	{
		$top_buttons .= aphp('transactions', ['id' => $next], 'Volgende', 'btn btn-default', 'Volgende', 'chevron-up', true);
	}

	$top_buttons .= aphp('transactions', [], 'Lijst', 'btn btn-default', 'Transactielijst', 'exchange', true);

	if ($s_user || $s_admin)
	{
		$top_buttons .= aphp('transactions', ['uid' => $s_id], 'Mijn transacties', 'btn btn-default', 'Mijn transacties', 'user', true);
	}

	$h1 = 'Transactie';
	$fa = 'exchange';

	include __DIR__ . '/include/header.php';

	echo '<div class="panel panel-default printview">';
	echo '<div class="panel-heading">';

	echo '<dl class="dl-horizontal">';

	echo '<dt>Tijdstip</dt>';
	echo '<dd>';
	echo $app['date_format']->get($transaction['cdate']);
	echo '</dd>';

	echo '<br>';

	echo '<dt>Transactie ID</dt>';
	echo '<dd>';
	echo $transaction['transid'];
	echo '</dd>';

	echo '<br>';

	if ($transaction['real_from'])
	{
		echo '<dt>Van interlets account</dt>';
		echo '<dd>';
		echo link_user($transaction['id_from'], false, $s_admin);
		echo '</dd>';

		echo '<dt>Van interlets gebruiker</dt>';
		echo '<dd>';
		echo '<span class="btn btn-default btn-xs"><i class="fa fa-share-alt"></i></span> ';

		if ($inter_transaction)
		{
			echo link_user($inter_transaction['id_from'],
				$inter_schema,
				$s_inter_schema_check[$inter_schema]);
		}
		else
		{
			echo $transaction['real_from'];
		}

		echo '</dd>';
	}
	else
	{
		echo '<dt>Van gebruiker</dt>';
		echo '<dd>';
		echo link_user($transaction['id_from']);
		echo '</dd>';
	}

	echo '<br>';

	if ($transaction['real_to'])
	{
		echo '<dt>Naar interlets account</dt>';
		echo '<dd>';
		echo link_user($transaction['id_to'], false, $s_admin);
		echo '</dd>';

		echo '<dt>Naar interlets gebruiker</dt>';
		echo '<dd>';
		echo '<span class="btn btn-default btn-xs"><i class="fa fa-share-alt"></i></span> ';

		if ($inter_transaction)
		{
			echo link_user($inter_transaction['id_to'],
				$inter_schema,
				$s_inter_schema_check[$inter_schema]);
		}
		else
		{
			echo $transaction['real_to'];
		}

		echo '</dd>';
	}
	else
	{
		echo '<dt>Naar gebruiker</dt>';
		echo '<dd>';
		echo link_user($transaction['id_to']);
		echo '</dd>';
	}

	echo '<br>';

	echo '<dt>Waarde</dt>';
	echo '<dd>';
	echo $transaction['amount'] . ' ' . $app['config']->get('currency');
	echo '</dd>';

	echo '<br>';

	echo '<dt>Omschrijving</dt>';
	echo '<dd>';
	echo $transaction['description'];
	echo '</dd>';

	echo '</dl>';

	if ($inter_transaction && isset($eland_interlets_groups[$inter_schema]))
	{
		echo '<p><a href="';
		echo generate_url('transactions', ['id' => $inter_transaction['id']], $inter_schema);
		echo '">Zie de complementaire transactie in de andere groep.</a></p>';
	}

	echo '</div></div>';

	echo '<ul><small><i>';

	if ($app['config']->get('currencyratio') > 0)
	{
		echo '<li>Valuatie: <span class="num">';
		echo $app['config']->get('currencyratio');
		echo '</span> ' . $app['config']->get('currency') . ' per uur.</li>';
	}

	echo '</i></small></ul>';

	include __DIR__ . '/include/footer.php';
	exit;
}

/**
 * list
 */
$s_owner = (!$s_guest && $s_group_self && $s_id == $uid && $uid) ? true : false;

$params_sql = $where_sql = $where_code_sql = [];

$params = [
	'orderby'	=> $orderby,
	'asc'		=> $asc,
	'limit'		=> $limit,
	'start'		=> $start,
];

if ($uid)
{
	$user = $app['user_cache']->get($uid);

	$where_sql[] = 't.id_from = ? or t.id_to = ?';
	$params_sql[] = $uid;
	$params_sql[] = $uid;
	$params['uid'] = $uid;

	$fcode = $tcode = link_user($user, false, false);
	$andor = 'or';
}

if ($q)
{
	$where_sql[] = 't.description ilike ?';
	$params_sql[] = '%' . $q . '%';
	$params['q'] = $q;
}

if (!$uid)
{
	if ($fcode)
	{
		list($fcode) = explode(' ', trim($fcode));

		$fuid = $app['db']->fetchColumn('select id from users where letscode = \'' . $fcode . '\'');

		if ($fuid)
		{
			$where_code_sql[] = 't.id_from = ?';
			$params_sql[] = $fuid;

			$fcode = link_user($fuid, false, false);
		}
		else
		{
			$where_code_sql[] = '1 = 2';
		}

		$params['fcode'] = $fcode;
	}

	if ($tcode)
	{
		list($tcode) = explode(' ', trim($tcode));

		$tuid = $app['db']->fetchColumn('select id from users where letscode = \'' . $tcode . '\'');

		if ($tuid)
		{
			$where_code_sql[] = 't.id_to = ?';
			$params_sql[] = $tuid;

			$tcode = link_user($tuid, false, false);
		}
		else
		{
			$where_code_sql[] = '1 = 2';
		}

		$params['tcode'] = $tcode;
	}

	if (count($where_code_sql) > 1)
	{
		if ($andor == 'or')
		{
			$where_code_sql = [' ( ' . implode(' or ', $where_code_sql) . ' ) '];
		}

		$params['andor'] = $andor;
	}
}

$where_sql = array_merge($where_sql, $where_code_sql);

if ($fdate)
{
	$fdate_sql = $app['date_format']->reverse($fdate);

	if ($fdate_sql === false)
	{
		$app['alert']->warning('De begindatum is fout geformateerd.');
	}
	else
	{
		$where_sql[] = 't.date >= ?';
		$params_sql[] = $fdate_sql;
		$params['fdate'] = $fdate;
	}
}

if ($tdate)
{
	$tdate_sql = $app['date_format']->reverse($tdate);

	if ($tdate_sql === false)
	{
		$app['alert']->warning('De einddatum is fout geformateerd.');
	}
	else
	{
		$where_sql[] = 't.date <= ?';
		$params_sql[] = $tdate_sql;
		$params['tdate'] = $tdate;
	}
}

if (count($where_sql))
{
	$where_sql = ' where ' . implode(' and ', $where_sql) . ' ';
}
else
{
	$where_sql = '';
}

$query = 'select t.*
	from transactions t ' .
	$where_sql . '
	order by t.' . $orderby . ' ';
$query .= ($asc) ? 'asc ' : 'desc ';
$query .= ' limit ' . $limit . ' offset ' . $start;

$transactions = $app['db']->fetchAll($query, $params_sql);

foreach ($transactions as $key => $t)
{
	if (!($t['real_from'] || $t['real_to']))
	{
		continue;
	}

	$inter_schema = false;

	if (isset($interlets_accounts_schemas[$t['id_from']]))
	{
		$inter_schema = $interlets_accounts_schemas[$t['id_from']];
	}
	else if (isset($interlets_accounts_schemas[$t['id_to']]))
	{
		$inter_schema = $interlets_accounts_schemas[$t['id_to']];
	}

	if ($inter_schema)
	{
		$inter_transaction = $app['db']->fetchAssoc('select t.*
			from ' . $inter_schema . '.transactions t
			where t.transid = ?', [$t['transid']]);

		if ($inter_transaction)
		{
			$transactions[$key]['inter_schema'] = $inter_schema;
			$transactions[$key]['inter_transaction'] = $inter_transaction;
		}
	}
}


$row_count = $app['db']->fetchColumn('select count(t.*)
	from transactions t ' . $where_sql, $params_sql);

$app['pagination']->init('transactions', $row_count, $params, $inline);

$asc_preset_ary = [
	'asc'	=> 0,
	'indicator' => '',
];

$tableheader_ary = [
	'description' => array_merge($asc_preset_ary, [
		'lbl' => 'Omschrijving']),
	'amount' => array_merge($asc_preset_ary, [
		'lbl' => $app['config']->get('currency')]),
	'cdate'	=> array_merge($asc_preset_ary, [
		'lbl' 		=> 'Tijdstip',
		'data_hide' => 'phone'])
];

if ($uid)
{
	$tableheader_ary['user'] = array_merge($asc_preset_ary, [
		'lbl'			=> 'Tegenpartij',
		'data_hide'		=> 'phone, tablet',
		'no_sort'		=> true,
	]);
}
else
{
	$tableheader_ary += [
		'from_user' => array_merge($asc_preset_ary, [
			'lbl' 		=> 'Van',
			'data_hide'	=> 'phone, tablet',
			'no_sort'	=> true,
		]),
		'to_user' => array_merge($asc_preset_ary, [
			'lbl' 		=> 'Aan',
			'data_hide'	=> 'phone, tablet',
			'no_sort'	=> true,
		]),
	];
}

$tableheader_ary[$orderby]['asc'] = ($asc) ? 0 : 1;
$tableheader_ary[$orderby]['indicator'] = ($asc) ? '-asc' : '-desc';

if ($s_admin || $s_user)
{
	if ($uid)
	{
		$user_str = link_user($user, false, false);

		if ($user['status'] != 7)
		{
			if ($s_owner)
			{
				$top_buttons .= aphp('transactions', ['add' => 1], 'Transactie toevoegen', 'btn btn-success', 'Transactie toevoegen', 'plus', true);
			}
			else if ($s_admin)
			{
				$top_buttons .= aphp('transactions', ['add' => 1, 'fuid' => $uid], 'Transactie van ' . $user_str, 'btn btn-success', 'Transactie van ' . $user_str, 'plus', true);
			}

			if ($s_admin || ($s_user && !$s_owner))
			{
				$top_buttons .= aphp('transactions', ['add' => 1, 'tuid' => $uid], 'Transactie naar ' . $user_str, 'btn btn-warning', 'Transactie naar ' . $user_str, 'exchange', true);
			}
		}

		if (!$inline)
		{
			$top_buttons .= aphp('transactions', [], 'Lijst', 'btn btn-default', 'Transactielijst', 'exchange', true);
		}
	}
	else
	{
		$top_buttons .= aphp('transactions', ['add' => 1], 'Toevoegen', 'btn btn-success', 'Transactie toevoegen', 'plus', true);

		if (!$s_master)
		{
			$top_buttons .= aphp('transactions', ['uid' => $s_id], 'Mijn transacties', 'btn btn-default', 'Mijn transacties', 'user', true);
		}
	}
}

if ($s_admin)
{
	$top_right .= '<a href="#" class="csv">';
	$top_right .= '<i class="fa fa-file"></i>';
	$top_right .= '&nbsp;csv</a>';
}

$filtered = ($q || $fcode || $tcode || $fdate || $tdate) ? true : false;

if ($uid)
{
	if ($s_owner && !$inline)
	{
		$h1 = 'Mijn transacties';
	}
	else
	{
		$h1 = aphp('transactions', ['uid' => $uid], 'Transacties');
		$h1 .= ' van ' . link_user($uid);
	}
}
else
{
	$h1 = 'Transacties';
	$h1 .= ($filtered) ? ' <small>gefilterd</small>' : '';
}

$fa = 'exchange';

if (!$inline)
{
	$h1 .= '<div class="pull-right">';
	$h1 .= '&nbsp;<button class="btn btn-default hidden-xs" title="Filters" ';
	$h1 .= 'data-toggle="collapse" data-target="#filter"';
	$h1 .= '><i class="fa fa-caret-down"></i><span class="hidden-xs hidden-sm"> Filters</span></button>';
	$h1 .= '</div>';

	$app['assets']->add(['datepicker', 'typeahead', 'typeahead.js', 'csv.js']);

	include __DIR__ . '/include/header.php';

	$panel_collapse = ($filtered && !$uid) ? '' : ' collapse';

	echo '<div class="panel panel-info' . $panel_collapse . '" id="filter">';
	echo '<div class="panel-heading">';

	echo '<form method="get" class="form-horizontal">';

	echo '<div class="row">';

	echo '<div class="col-sm-12">';
	echo '<div class="input-group margin-bottom">';
	echo '<span class="input-group-addon">';
	echo '<i class="fa fa-search"></i>';
	echo '</span>';
	echo '<input type="text" class="form-control" id="q" value="' . $q . '" name="q" placeholder="Zoekterm">';
	echo '</div>';
	echo '</div>';

	echo '</div>';

	echo '<div class="row">';

	echo '<div class="col-sm-5">';
	echo '<div class="input-group margin-bottom">';
	echo '<span class="input-group-addon" id="fcode_addon">Van ';
	echo '<span class="fa fa-user"></span></span>';

	if ($s_guest)
	{
		$typeahead_name_ary = ['users_active'];
	}
	else if ($s_user)
	{
		$typeahead_name_ary = ['users_active', 'users_extern'];
	}
	else if ($s_admin)
	{
		$typeahead_name_ary = ['users_active', 'users_extern',
			'users_inactive', 'users_im', 'users_ip'];
	}

	echo '<input type="text" class="form-control" ';
	echo 'aria-describedby="fcode_addon" ';
	echo 'data-typeahead="' . $app['typeahead']->get($typeahead_name_ary) . '" ';
	echo 'data-newuserdays="' . $app['config']->get('newuserdays') . '" ';
	echo 'name="fcode" id="fcode" placeholder="' . $app['type_template']->get('code') . '" ';
	echo 'value="' . $fcode . '">';

	echo '</div>';
	echo '</div>';

	$andor_options = [
		'and'	=> 'EN',
		'or'	=> 'OF',
	];

	echo '<div class="col-sm-2">';
	echo '<select class="form-control margin-bottom" name="andor">';
	render_select_options($andor_options, $andor);
	echo '</select>';
	echo '</div>';

	echo '<div class="col-sm-5">';
	echo '<div class="input-group margin-bottom">';
	echo '<span class="input-group-addon" id="tcode_addon">Naar ';
	echo '<span class="fa fa-user"></span></span>';
	echo '<input type="text" class="form-control margin-bottom" ';
	echo 'data-typeahead-source="fcode" ';
	echo 'placeholder="' . $app['type_template']->get('code') . '" ';
	echo 'aria-describedby="tcode_addon" ';
	echo 'name="tcode" value="' . $tcode . '">';
	echo '</div>';
	echo '</div>';

	echo '</div>';

	echo '<div class="row">';

	echo '<div class="col-sm-5">';
	echo '<div class="input-group margin-bottom">';
	echo '<span class="input-group-addon" id="fdate_addon">Vanaf ';
	echo '<span class="fa fa-calendar"></span></span>';
	echo '<input type="text" class="form-control margin-bottom" ';
	echo 'aria-describedby="fdate_addon" ';

	echo 'id="fdate" name="fdate" ';
	echo 'value="' . $fdate . '" ';
	echo 'data-provide="datepicker" ';
	echo 'data-date-format="' . $app['date_format']->datepicker_format() . '" ';
	echo 'data-date-default-view-date="-1y" ';
	echo 'data-date-end-date="0d" ';
	echo 'data-date-language="nl" ';
	echo 'data-date-today-highlight="true" ';
	echo 'data-date-autoclose="true" ';
	echo 'data-date-immediate-updates="true" ';
	echo 'data-date-orientation="bottom" ';
	echo 'placeholder="' . $app['date_format']->datepicker_placeholder() . '"';
	echo '>';

	echo '</div>';
	echo '</div>';

	echo '<div class="col-sm-5">';
	echo '<div class="input-group margin-bottom">';
	echo '<span class="input-group-addon" id="tdate_addon">Tot en met ';
	echo '<span class="fa fa-calendar"></span></span>';
	echo '<input type="text" class="form-control margin-bottom" ';
	echo 'aria-describedby="tdate_addon" ';

	echo 'id="tdate" name="tdate" ';
	echo 'value="' . $tdate . '" ';
	echo 'data-provide="datepicker" ';
	echo 'data-date-format="' . $app['date_format']->datepicker_format() . '" ';
	echo 'data-date-end-date="0d" ';
	echo 'data-date-language="nl" ';
	echo 'data-date-today-highlight="true" ';
	echo 'data-date-autoclose="true" ';
	echo 'data-date-immediate-updates="true" ';
	echo 'data-date-orientation="bottom" ';
	echo 'placeholder="' . $app['date_format']->datepicker_placeholder() . '"';
	echo '>';

	echo '</div>';
	echo '</div>';

	echo '<div class="col-sm-2">';
	echo '<input type="submit" value="Toon" class="btn btn-default btn-block">';
	echo '</div>';

	echo '</div>';

	$params_form = $params;
	unset($params_form['q'], $params_form['fcode'], $params_form['andor'], $params_form['tcode']);
	unset($params_form['fdate'], $params_form['tdate'], $params_form['uid']);
	unset($params_form['start']);

	$params_form['r'] = $s_accountrole;
	$params_form['u'] = $s_id;

	if (!$s_group_self)
	{
		$params_form['s'] = $s_schema;
	}

	foreach ($params_form as $name => $value)
	{
		if (isset($value))
		{
			echo '<input name="' . $name . '" value="' . $value . '" type="hidden">';
		}
	}

	echo '</form>';

	echo '</div>';
	echo '</div>';
}
else
{
	echo '<div class="row">';
	echo '<div class="col-md-12">';

	echo '<h3><i class="fa fa-exchange"></i> ' . $h1;
	echo '<span class="inline-buttons">' . $top_buttons . '</span>';
	echo '</h3>';
}

$app['pagination']->render();

if (!count($transactions))
{
	echo '<br>';
	echo '<div class="panel panel-primary">';
	echo '<div class="panel-body">';
	echo '<p>Er zijn geen resultaten.</p>';
	echo '</div></div>';
	$app['pagination']->render();

	if (!$inline)
	{
		include __DIR__ . '/include/footer.php';
	}
	exit;
}

echo '<div class="panel panel-primary printview">';
echo '<div class="table-responsive">';
echo '<table class="table table-bordered table-striped table-hover footable csv transactions" ';
echo 'data-sort="false">';
echo '<thead>';
echo '<tr>';

foreach ($tableheader_ary as $key_orderby => $data)
{
	echo '<th';
	echo (isset($data['data_hide'])) ? ' data-hide="' . $data['data_hide'] . '"' : '';
	echo '>';
	if (isset($data['no_sort']))
	{
		echo $data['lbl'];
	}
	else
	{
		$h_params = $params;

		$h_params['orderby'] = $key_orderby;
		$h_params['asc'] = $data['asc'];

		echo '<a href="' . generate_url('transactions', $h_params) . '">';
		echo $data['lbl'] . '&nbsp;<i class="fa fa-sort' . $data['indicator'] . '"></i>';
		echo '</a>';
	}
	echo '</th>';
}

echo '</tr>';
echo '</thead>';
echo '<tbody>';

if ($uid)
{
	foreach($transactions as $t)
	{
		echo '<tr>';
		echo '<td>';
		echo aphp('transactions', ['id' => $t['id']], $t['description']);
		echo '</td>';

		echo '<td>';
		echo '<span class="text-';
		echo ($t['id_from'] == $uid) ? 'danger">-' : 'success">+';
		echo $t['amount'];
		echo '</span></td>';

		echo '<td>';
		echo $app['date_format']->get($t['cdate']);
		echo '</td>';

		echo '<td>';

		if ($t['id_from'] == $uid)
		{
			if ($t['real_to'])
			{
				echo '<span class="btn btn-default btn-xs"><i class="fa fa-share-alt"></i></span> ';

				if (isset($t['inter_transaction']))
				{
					echo link_user($t['inter_transaction']['id_to'],
						$t['inter_schema'],
						$s_inter_schema_check[$t['inter_schema']]);
				}
				else
				{
					echo $t['real_to'];
				}

				echo '</dd>';
			}
			else
			{
				echo link_user($t['id_to']);
			}
		}
		else
		{
			if ($t['real_from'])
			{
				echo '<span class="btn btn-default btn-xs"><i class="fa fa-share-alt"></i></span> ';

				if (isset($t['inter_transaction']))
				{
					echo link_user($t['inter_transaction']['id_from'],
						$t['inter_schema'],
						$s_inter_schema_check[$t['inter_schema']]);
				}
				else
				{
					echo $t['real_from'];
				}

				echo '</dd>';
			}
			else
			{
				echo link_user($t['id_from']);
			}
		}

		echo '</td>';
		echo '</tr>';
	}
}
else
{
	foreach($transactions as $t)
	{
		echo '<tr>';
		echo '<td>';
		echo aphp('transactions', ['id' => $t['id']], $t['description']);
		echo '</td>';

		echo '<td>';
		echo $t['amount'];
		echo '</td>';

		echo '<td>';
		echo $app['date_format']->get($t['cdate']);
		echo '</td>';

		echo '<td>';

		if ($t['real_from'])
		{
			echo '<span class="btn btn-default btn-xs"><i class="fa fa-share-alt"></i></span> ';

			if (isset($t['inter_transaction']))
			{
				echo link_user($t['inter_transaction']['id_from'],
					$t['inter_schema'],
					$s_inter_schema_check[$t['inter_schema']]);
			}
			else
			{
				echo $t['real_from'];
			}

			echo '</dd>';
		}
		else
		{
			echo link_user($t['id_from']);
		}

		echo '</td>';

		echo '<td>';

		if ($t['real_to'])
		{
			echo '<span class="btn btn-default btn-xs"><i class="fa fa-share-alt"></i></span> ';

			if (isset($t['inter_transaction']))
			{
				echo link_user($t['inter_transaction']['id_to'],
					$t['inter_schema'],
					$s_inter_schema_check[$t['inter_schema']]);
			}
			else
			{
				echo $t['real_to'];
			}

			echo '</dd>';
		}
		else
		{
			echo link_user($t['id_to']);
		}

		echo '</td>';

		echo '</tr>';
	}
}
echo '</table></div></div>';

$app['pagination']->render();

if ($inline)
{
	echo '</div></div>';
}
else
{
	echo '<ul><small><i>';

	if ($app['config']->get('currencyratio') > 0)
	{
		echo '<li>Valuatie: <span class="num">';
		echo $app['config']->get('currencyratio');
		echo '</span> ' . $app['config']->get('currency') . ' per uur.</li>';
	}

	echo '</i></small></ul>';

	include __DIR__ . '/include/footer.php';
}

function cancel($id = null)
{
	$params = [];

	if ($id)
	{
		$params['id'] = $id;
	}

	header('Location: ' . generate_url('transactions', $params));
	exit;
}
