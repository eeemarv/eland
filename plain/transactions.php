<?php declare(strict_types=1);

if ($app['s_anonymous'])
{
	exit;
}

$id = $_GET['id'] ?? false;
$add = isset($_GET['add']);
$edit = $_GET['edit'] ?? false;

$mid = $_GET['mid'] ?? false;
$tuid = $_GET['tuid'] ?? false;
$tus = $_GET['tus'] ?? false;
$fuid = $_GET['fuid'] ?? false;

$filter = $app['request']->query->get('f', []);
$pag = $app['request']->query->get('p', []);
$sort = $app['request']->query->get('s', []);

$currency = $app['config']->get('currency', $app['tschema']);

/**
 * add
 */
if ($add)
{
	if ($app['s_guest'])
	{
		$app['alert']->error('Je hebt geen rechten om een transactie toe te voegen.');
		$app['link']->redirect('transactions', $app['pp_ary'], []);
	}

	$transaction = [];

	$redis_transid_key = $app['tschema'] . '_transid_u_' . $app['s_id'];

	if ($app['request']->isMethod('POST'))
	{
		$stored_transid = $app['predis']->get($redis_transid_key);

		if (!$stored_transid)
		{
			$errors[] = 'Formulier verlopen.';
		}

		$transaction['transid'] = trim($_POST['transid']);
		$transaction['description'] = trim($_POST['description']);

		[$letscode_from] = explode(' ', trim($_POST['letscode_from']));
		[$letscode_to] = explode(' ', trim($_POST['letscode_to']));

		$transaction['amount'] = $amount = ltrim($_POST['amount'], '0 ');;
		$transaction['date'] = gmdate('Y-m-d H:i:s');
		$transaction['creator'] = $app['s_master'] ? 0 : $app['s_id'];

		$group_id = trim($_POST['group_id']);

		if ($stored_transid != $transaction['transid'])
		{
			$errors[] = 'Fout transactie id.';
		}

		if ($app['db']->fetchColumn('select transid
			from ' . $app['tschema'] . '.transactions
			where transid = ?', [$stored_transid]))
		{
			$errors[] = 'Een herinvoer van de transactie werd voorkomen.';
		}

		if (strlen($transaction['description']) > 60)
		{
			$errors[] = 'De omschrijving mag maximaal 60 tekens lang zijn.';
		}

		if ($group_id != 'self')
		{
			$group = $app['db']->fetchAssoc('select *
				from ' . $app['tschema'] . '.letsgroups
				where id = ?', [$group_id]);

			if (!isset($group))
			{
				$errors[] =  'InterSysteem niet gevonden.';
			}
			else
			{
				$group['domain'] = strtolower(parse_url($group['url'], PHP_URL_HOST));
			}
		}

		if ($app['pp_user'] && !$app['s_master'])
		{
			$fromuser = $app['db']->fetchAssoc('select *
				from ' . $app['tschema'] . '.users
				where id = ?', [$app['s_id']]);
		}
		else
		{
			$fromuser = $app['db']->fetchAssoc('select *
				from ' . $app['tschema'] . '.users
				where letscode = ?', [$letscode_from]);
		}

		$letscode_touser = $group_id == 'self' ? $letscode_to : $group['localletscode'];

		$touser = $app['db']->fetchAssoc('select *
			from ' . $app['tschema'] . '.users
			where letscode = ?', [$letscode_touser]);

		if(empty($fromuser))
		{
			$errors[] = 'De "Van Account Code" bestaat niet';
		}

		if (!strlen($letscode_to))
		{
			$errors[] = 'Geen bestemmings Account (Aan Account Code) ingevuld';
		}

		if(empty($touser) && !count($errors))
		{
			if ($group_id == 'self')
			{
				$errors[] = 'Bestemmings Account (Aan Account Code) bestaat niet';
			}
			else
			{
				$errors[] = 'De interSysteem rekening (in dit Systeem) bestaat niet';
			}
		}

		if ($group_id == 'self' && !count($errors))
		{
			if ($touser['status'] == 7)
			{
				$errors[] = 'Je kan niet rechtstreeks naar een interSysteem rekening overschrijven.';
			}
		}

		if ($fromuser['status'] == 7 && !count($errors))
		{
			$errors[] = 'Je kan niet rechtstreeks van een interSysteem rekening overschrijven.';
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

		if (!$app['pp_admin'] && !count($errors))
		{
			if ($fromuser['minlimit'] === -999999999)
			{
				$minlimit = $app['config']->get('minlimit', $app['tschema']);

				if(($fromuser['saldo'] - $amount) < $minlimit && $minlimit !== '')
				{
					$err = 'Je beschikbaar saldo laat deze transactie niet toe. ';
					$err .= 'Je saldo bedraagt ' . $fromuser['saldo'] . ' ' . $currency . ' ';
					$err .= 'en de minimum Systeemslimiet bedraagt ';
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
			$errors[] = 'Van en Aan Account Code kunnen hetzelfde zijn.';
		}

		if (!$app['pp_admin'] && !count($errors))
		{
			if ($touser['maxlimit'] === 999999999)
			{
				$maxlimit = $app['config']->get('maxlimit', $app['tschema']);

				if(($touser['saldo'] + $transaction['amount']) > $maxlimit && $maxlimit !== '')
				{
					$err = 'Het ';
					$err .= $group_id == 'self' ? 'bestemmings Account (Aan Account Code)' : 'interSysteem Account (in dit Systeem)';
					$err .= ' heeft haar maximum limiet bereikt. ';
					$err .= 'Het saldo bedraagt ' . $touser['saldo'] . ' ' . $currency;
					$err .= ' en de maximum ';
					$err .= 'Systeemslimiet bedraagt ' . $maxlimit . ' ' . $currency . '.';
					$errors[] = $err;
				}
			}
			else
			{
				if(($touser['saldo'] + $transaction['amount']) > $touser['maxlimit'])
				{
					$err = 'Het ';
					$err .= $group_id == 'self' ? 'bestemmings Account (Aan Account Code)' : 'interSysteem Account (in dit Systeem)';
					$err .= ' heeft haar maximum limiet bereikt. ';
					$err .= 'Het saldo bedraagt ' . $touser['saldo'] . ' ' . $currency;
					$err .= ' en de Maximum Account ';
					$err .= 'Limiet bedraagt ' . $touser['maxlimit'] . ' ' . $currency . '.';
					$errors[] = $err;
				}
			}
		}

		if($group_id == 'self'
			&& !$app['pp_admin']
			&& !($touser['status'] == '1' || $touser['status'] == '2')
			&& !count($errors))
		{
			$errors[] = 'Het bestemmings Account (Aan Account Code) is niet actief';
		}

		if ($app['pp_user'] && !count($errors))
		{
			$balance_eq = $app['config']->get('balance_equilibrium', $app['tschema']);

			if (($fromuser['status'] == 2) && (($fromuser['saldo'] - $amount) < $balance_eq))
			{
				$err = 'Als Uitstapper kan je geen ';
				$err .= $amount;
				$err .= ' ';
				$err .= $app['config']->get('currency', $app['tschema']);
				$err .= ' uitgeven.';
				$errors[] = $err;
			}

			if (($touser['status'] == 2) && (($touser['saldo'] + $amount) > $balance_eq))
			{
				$err = 'Het ';
				$err .= $group_id === 'self' ? 'bestemmings Account (Aan Account Code)' : 'interSysteem Account (op dit Systeem)';
				$err .= ' heeft de status \'Uitstapper\' en kan geen ';
				$err .= $amount . ' ';
				$err .= $app['config']->get('currency', $app['tschema']);
				$err .= ' ontvangen.';
				$errors[] = $err;
			}
		}

		if ($error_token = $app['form_token']->get_error())
		{
			$errors[] = $error_token;
		}

		$contact_admin = $app['pp_admin'] ? '' : ' Contacteer een admin.';

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
			if ($id = $app['transaction']->insert($transaction, $app['tschema']))
			{
				$transaction['id'] = $id;
				$app['mail_transaction']->queue($transaction, $app['tschema']);
				$app['alert']->success('Transactie opgeslagen');
			}
			else
			{
				$app['alert']->error('Gefaalde transactie');
			}

			$app['link']->redirect('transactions', $app['pp_ary'], []);
		}
		else if ($group['apimethod'] == 'mail')
		{
			$transaction['real_to'] = $letscode_to;

			if ($id = $app['transaction']->insert($transaction, $app['tschema']))
			{
				$transaction['id'] = $id;
				$transaction['letscode_to'] = $letscode_to;

				$app['mail_transaction']->queue_mail_type($transaction, $app['tschema']);

				$app['alert']->success('InterSysteem transactie opgeslagen. Een E-mail werd
					verstuurd naar de administratie van het andere Systeem om de transactie aldaar
					manueel te verwerken.');
			}
			else
			{
				$app['alert']->error('Gefaalde interSysteem transactie');
			}

			$app['link']->redirect('transactions', $app['pp_ary'], []);
		}
		else if ($group['apimethod'] != 'elassoap')
		{
			$app['alert']->error('InterSysteem ' .
				$group['groupname'] .
				' heeft geen geldige Api Methode.' . $contact_admin);
			$app['link']->redirect('transactions', $app['pp_ary'], []);
		}
		else if (!$group_domain)
		{
			$app['alert']->error('Geen URL ingesteld voor interSysteem ' .
				$group['groupname'] . '. ' . $contact_admin);
			$app['link']->redirect('transactions', $app['pp_ary'], []);
		}
		else if (!$app['systems']->get_schema_from_legacy_eland_origin($group['url']))
		{
			// The interSysteem group uses eLAS or is on another server

			if (!$group['remoteapikey'])
			{
				$errors[] = 'Geen Remote Apikey voor dit interSysteem ingesteld.' . $contact_admin;
			}

			if (!$group['presharedkey'])
			{
				$errors[] = 'Geen Preshared Key voor dit interSysteem ingesteld.' . $contact_admin;
			}

			if (!$group['myremoteletscode'])
			{
				$errors[] = 'Geen Remote Account Code ingesteld voor dit interSysteem.' . $contact_admin;
			}

			$currencyratio = $app['config']->get('currencyratio', $app['tschema']);

			if (!$currencyratio || !ctype_digit((string) $currencyratio) || $currencyratio < 1)
			{
				$errors[] = 'De Currency Ratio is niet correct ingesteld. ' . $contact_admin;
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
						$errors[] = 'Er werd geen naam gevonden voor het Account van het interSysteem.';
					}
				}
				else
				{
					$errors[] = 'Er werd geen Account gevonden met Code ' . $letscode_to;
				}
			}

			if (count($errors))
			{
				$app['alert']->error($errors);
				$app['link']->redirect('transactions', $app['pp_ary'], []);
			}

			$trans = $transaction;

			$trans['amount'] = $trans['amount'] / $currencyratio;
			$trans['amount'] = (float) $trans['amount'];
			$trans['amount'] = round($trans['amount'], 5);

			$trans['letscode_to'] = $letscode_to;

			$soapurl = $group['elassoapurl'] ?: $group['url'] . '/soap';
			$soapurl .= '/wsdlelas.php?wsdl';

			$client = new nusoap_client($soapurl, true);

			$error = $client->getError();

			if ($error)
			{
				$app['alert']->error('eLAS soap error: ' . $error . ' <br>' . $contact_admin);
				$app['link']->redirect('transactions', $app['pp_ary'], []);
			}

			$result = $client->call('dopayment', [
				'apikey' 		=> $group['remoteapikey'],
				'from' 			=> $group['myremoteletscode'],
				'real_from' 	=> $app['account']->str($fromuser['id'], $app['tschema']),
				'to' 			=> $letscode_to,
				'description' 	=> $trans['description'],
				'amount' 		=> $trans['amount'],
				'transid' 		=> $trans['transid'],
				'signature' 	=> $app['transaction']->sign($trans, trim($group['presharedkey']), $app['tschema']),
			]);

			$error = $client->getError();

			if ($error)
			{
				$app['alert']->error('eLAS soap error: ' . $error . ' <br>' . $contact_admin);
				$app['link']->redirect('transactions', $app['pp_ary'], []);
			}

			if ($result == 'OFFLINE')
			{
				$errors[] = 'Het andere Systeem is offline. Probeer het later opnieuw. ';
			}

			if ($result == 'FAILED')
			{
				$errors[] = 'De interSysteem transactie is gefaald.' . $contact_admin;
			}

			if ($result == 'SIGFAIL')
			{
				$errors[] = 'De signatuur van de interSysteem transactie is gefaald. ' . $contact_admin;
			}

			if ($result == 'DUPLICATE')
			{
				$errors[] = 'De transactie bestaat reeds in het andere Systeem. ' . $contact_admin;
			}

			if ($result == 'NOUSER')
			{
				$errors[] = 'Het Account in het andere Systeem werd niet gevonden. ';
			}

			if ($result == 'APIKEYFAIL')
			{
				$errors[] = 'De Apikey is niet correct. ' . $contact_admin;
			}

			if (!count($errors) && $result != 'SUCCESS')
			{
				$errors[] = 'De interSysteem transactie kon niet verwerkt worden. ' . $contact_admin;
			}

			if (count($errors))
			{
				$app['alert']->error($errors);
				$app['link']->redirect('transactions', $app['pp_ary'], []);
			}

			$transaction['real_to'] = $letscode_to . ' ' . $real_name_to;

			$app['monolog']->debug('insert transation: --  ' .
				http_build_query($transaction) .
				' --', ['schema' => $app['tschema']]);

			$id = $app['transaction']->insert($transaction, $app['tschema']);

			if (!$id)
			{
				$app['queue.mail']->queue([
					'schema'		=> $app['tschema'],
					'to' 			=> $app['mail_addr_system']->get_admin($app['tschema']),
					'template'		=> 'transaction/intersystem_fail',
					'vars'			=> [
						'remote_system_name'	=> $group['groupname'],
						'transaction'			=> $transaction,
					],
				], 9000);

				$app['alert']->error('De lokale commit van de interSysteem
					transactie is niet geslaagd. ' .
					$contact_admin);

				$app['link']->redirect('transactions', $app['pp_ary'], []);
			}

			$transaction['id'] = $id;

			// to eLAS intersystem
			$app['mail_transaction']->queue($transaction, $app['tschema']);

			$app['alert']->success('De interSysteem transactie werd verwerkt.');
			$app['link']->redirect('transactions', $app['pp_ary'], []);
		}
		else
		{
			// the interSystem group is on the same server (eLAND)

			$remote_schema = $app['systems']->get_schema_from_legacy_eland_origin($group['url']);

			$to_remote_user = $app['db']->fetchAssoc('select *
				from ' . $remote_schema . '.users
				where letscode = ?', [$letscode_to]);

			if (!$to_remote_user)
			{
				$errors[] = 'Het bestemmings Account ("Aan Account Code") in het andere Systeem bestaat niet.';
			}
			else if (!in_array($to_remote_user['status'], ['1', '2']))
			{
				$errors[] = 'Het bestemmings Account ("Aan Account Code") in het andere Systeem is niet actief.';
			}

			$remote_group = $app['db']->fetchAssoc('select *
				from ' . $remote_schema . '.letsgroups
				where url = ?', [$app['base_url']]);

			if (!$remote_group && !count($errors))
			{
				$err = 'Het andere Systeem heeft dit Systeem (';
				$err .= $app['config']->get('systemname', $app['tschema']);
				$err .= ') niet geconfigureerd als interSysteem.';
				$errors[] = $err;
			}

			if (!$remote_group['localletscode'] && !count($errors))
			{
				$errors[] = 'Er is geen interSysteem Account gedefiniëerd in het andere Systeem.';
			}

			$remote_interlets_account = $app['db']->fetchAssoc('select *
				from ' . $remote_schema . '.users
				where letscode = ?', [$remote_group['localletscode']]);

			if (!$remote_interlets_account && !count($errors))
			{
				$errors[] = 'Er is geen interSysteem Account in het andere Systeem.';
			}

			if ($remote_interlets_account['accountrole'] !== 'interlets' && !count($errors))
			{
				$errors[] = 'Het Account in het andere Systeem is niet ingesteld met rol "interSysteem".';
			}

			if (!in_array($remote_interlets_account['status'], [1, 2, 7]) && !count($errors))
			{
				$errors[] = 'Het interSysteem Account in het andere Systeem heeft geen actieve status.';
			}

			$remote_currency = $app['config']->get('currency', $remote_schema);
			$remote_currencyratio = $app['config']->get('currencyratio', $remote_schema);
			$remote_balance_eq = $app['config']->get('balance_equilibrium', $remote_schema);
			$currencyratio = $app['config']->get('currencyratio', $app['tschema']);

			if ((!$currencyratio || !ctype_digit((string) $currencyratio) || $currencyratio < 1)
				&& !count($errors))
			{
				$errors[] = 'De Currency Ratio is niet correct ingesteld. ' . $contact_admin;
			}

			if ((!$remote_currencyratio ||
				!ctype_digit((string) $remote_currencyratio)
				|| $remote_currencyratio < 1) && !count($errors))
			{
				$errors[] = 'De Currency Ratio van het andere Systeem is niet correct ingesteld. ' . $contact_admin;
			}
			$remote_amount = round(($transaction['amount'] * $remote_currencyratio) / $currencyratio);

			if (($remote_amount < 1) && !count($errors))
			{
				$errors[] = 'Het bedrag is te klein want het kan niet uitgedrukt worden in de gebruikte munt van het andere Systeem.';
			}

			if (!count($errors))
			{
				if ($remote_interlets_account['minlimit'] === -999999999)
				{
					$minlimit = $app['config']->get('minlimit', $remote_schema);

					if(($remote_interlets_account['saldo'] - $remote_amount) < $minlimit && $minlimit !== '')
					{
						$err = 'Het interSysteem Account van dit Systeem ';
						$err .= 'in het andere Systeem heeft onvoldoende saldo ';
						$err .= 'beschikbaar. Het saldo bedraagt ';
						$err .= $remote_interlets_account['saldo'] . ' ';
						$err .= $remote_currency . ' ';
						$err .= 'en de Minimum Systeemslimiet ';
						$err .= 'in het andere Systeem bedraagt ';
						$err .= $minlimit . ' ';
						$err .= $remote_currency . '.';
						$errors[] = $err;
					}
				}
				else
				{
					if(($remote_interlets_account['saldo'] - $remote_amount) < $remote_interlets_account['minlimit'])
					{
						$err = 'Het interSysteem Account van dit Systeem in het andere Systeem heeft onvoldoende saldo ';
						$err .= 'beschikbaar. Het saldo bedraagt ' . $remote_interlets_account['saldo'] . ' ';
						$err .= $remote_currency . ' ';
						$err .= 'en de Minimum Limiet van het Account in het andere Systeem ';
						$err .= 'bedraagt ' . $remote_interlets_account['minlimit'] . ' ';
						$err .= $remote_currency . '.';
						$errors[] = $err;
					}
				}
			}

			if (($remote_interlets_account['status'] == 2)
				&& (($remote_interlets_account['saldo'] - $remote_amount) < $remote_balance_eq)
				&& !count($errors))
			{
				$err = 'Het interSysteem Account van dit Systeem in het andere Systeem ';
				$err .= 'heeft de status uitstapper ';
				$err .= 'en kan geen ';
				$err .= $remote_amount . ' ';
				$err .= $remote_currency . ' uitgeven ';
				$err .= '(' . $amount . ' ';
				$err .= $app['config']->get('currency', $app['tschema']);
				$err .= ').';
				$errors[] = $err;
			}

			if (!count($errors))
			{
				if ($to_remote_user['maxlimit'] === 999999999)
				{
					$maxlimit = $app['config']->get('maxlimit', $remote_schema);

					if(($to_remote_user['saldo'] + $remote_amount) > $maxlimit && $maxlimit !== '')
					{
						$err = 'Het bestemmings-Account in het andere Systeem ';
						$err .= 'heeft de maximum Systeemslimiet bereikt. ';
						$err .= 'Het saldo bedraagt ';
						$err .= $to_remote_user['saldo'];
						$err .= ' ';
						$err .= $remote_currency;
						$err .= ' en de maximum ';
						$err .= 'Systeemslimiet bedraagt ';
						$err .= $maxlimit . ' ' . $remote_currency . '.';
						$errors[] = $err;
					}
				}
				else
				{
					if(($to_remote_user['saldo'] + $remote_amount) > $to_remote_user['maxlimit'])
					{
						$err = 'Het bestemmings-Account in het andere Systeem ';
						$err .= 'heeft de maximum limiet bereikt. ';
						$err .= 'Het saldo bedraagt ' . $to_remote_user['saldo'] . ' ' . $remote_currency;
						$err .= ' en de maximum ';
						$err .= 'limiet voor het Account bedraagt ' . $to_remote_user['maxlimit'] . ' ' . $remote_currency . '.';
						$errors[] = $err;
					}
				}
			}

			if (($to_remote_user['status'] == 2)
				&& (($to_remote_user['saldo'] + $remote_amount) > $remote_balance_eq)
				&& !count($errors))
			{
				$err = 'Het bestemmings-Account heeft status uitstapper ';
				$err .= 'en kan geen ' . $remote_amount . ' ';
				$err .= $remote_currency . ' ontvangen (';
				$err .= $amount . ' ';
				$err .= $app['config']->get('currency', $app['tschema']);
				$err .= ').';
				$errors[] = $err;
			}

			if (count($errors))
			{
				$app['alert']->error($errors);
			}
			else
			{
				$transaction['creator'] = $app['s_master'] ? 0 : $app['s_id'];
				$transaction['cdate'] = gmdate('Y-m-d H:i:s');
				$transaction['real_to'] = $to_remote_user['letscode'] . ' ' . $to_remote_user['name'];

				$app['db']->beginTransaction();

				try
				{
					$app['db']->insert($app['tschema'] . '.transactions', $transaction);
					$id = $app['db']->lastInsertId($app['tschema'] . '.transactions_id_seq');
					$app['db']->executeUpdate('update ' . $app['tschema'] . '.users
						set saldo = saldo + ? where id = ?',
						[$transaction['amount'], $transaction['id_to']]);
					$app['db']->executeUpdate('update ' . $app['tschema'] . '.users
						set saldo = saldo - ? where id = ?',
						[$transaction['amount'], $transaction['id_from']]);

					$trans_org = $transaction;
					$trans_org['id'] = $id;

					$transaction['creator'] = 0;
					$transaction['amount'] = $remote_amount;
					$transaction['id_from'] = $remote_interlets_account['id'];
					$transaction['id_to'] = $to_remote_user['id'];
					$transaction['real_from'] = $app['account']->str($fromuser['id'], $app['tschema']);

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

				$app['user_cache']->clear($fromuser['id'], $app['tschema']);
				$app['user_cache']->clear($touser['id'], $app['tschema']);

				$app['user_cache']->clear($remote_interlets_account['id'], $remote_schema);
				$app['user_cache']->clear($to_remote_user['id'], $remote_schema);

				// to eLAND interSystem
				$app['mail_transaction']->queue($trans_org, $app['tschema']);
				$app['mail_transaction']->queue($transaction, $remote_schema);

				$app['monolog']->info('direct interSystem transaction ' . $transaction['transid'] . ' amount: ' .
					$amount . ' from user: ' .  $app['account']->str_id($fromuser['id'], $app['tschema']) .
					' to user: ' . $app['account']->str_id($touser['id'], $app['tschema']),
					['schema' => $app['tschema']]);

				$app['monolog']->info('direct interSystem transaction (receiving) ' . $transaction['transid'] .
					' amount: ' . $remote_amount . ' from user: ' . $remote_interlets_account['letscode'] . ' ' .
					$remote_interlets_account['name'] . ' to user: ' . $to_remote_user['letscode'] . ' ' .
					$to_remote_user['name'], ['schema' => $remote_schema]);

				$app['autominlimit']->init($app['tschema'])
					->process($transaction['id_from'], $transaction['id_to'], $transaction['amount']);

				$app['alert']->success('InterSysteem transactie uitgevoerd.');
				$app['link']->redirect('transactions', $app['pp_ary'], []);
			}
		}

		$transaction['letscode_to'] = $_POST['letscode_to'];
		$transaction['letscode_from'] = $app['pp_admin'] || $app['s_master']
			? $_POST['letscode_from']
			: $app['account']->str($app['s_id'], $app['tschema']);
	}
	else
	{
		//GET form

		$transid = $app['transaction']->generate_transid($app['s_id'], $app['pp_system']);

		$app['predis']->set($redis_transid_key, $transid);
		$app['predis']->expire($redis_transid_key, 3600);

		$transaction = [
			'date'			=> gmdate('Y-m-d H:i:s'),
			'letscode_from'	=> $app['s_master'] ? '' : $app['account']->str($app['s_id'], $app['tschema']),
			'letscode_to'	=> '',
			'amount'		=> '',
			'description'	=> '',
			'transid'		=> $transid,
		];

		$group_id = 'self';

		if ($tus)
		{
			if ($app['systems']->get_legacy_eland_origin($tus))
			{
				$origin_from_tus = $app['systems']->get_legacy_eland_origin($tus);

				$group_id = $app['db']->fetchColumn('select id
					from ' . $app['tschema'] . '.letsgroups
					where url = ?', [$origin_from_tus]);

				if ($mid)
				{
					$row = $app['db']->fetchAssoc('select
							m.content, m.amount, m.id_user,
							u.letscode, u.name
						from ' . $tus . '.messages m,
							'. $tus . '.users u
						where u.id = m.id_user
							and u.status in (1, 2)
							and m.id = ?', [$mid]);

					if ($row)
					{
						$transaction['letscode_to'] = $row['letscode'] . ' ' . $row['name'];
						$transaction['description'] =  substr($row['content'], 0, 60);
						$amount = $row['amount'];
						$amount = ($app['config']->get('currencyratio', $app['tschema']) * $amount) / $app['config']->get('currencyratio', $tus);
						$transaction['amount'] = round($amount);
					}
				}
				else if ($tuid)
				{
					$to_user = $app['user_cache']->get($tuid, $tus);

					if (in_array($to_user['status'], [1, 2]))
					{
						$transaction['letscode_to'] = $app['account']->str($tuid, $tus);
					}
				}
			}
		}
		else if ($mid)
		{
			$row = $app['db']->fetchAssoc('select
					m.content, m.amount, m.id_user,
					u.letscode, u.name, u.status
				from ' . $app['tschema'] . '.messages m,
					'. $app['tschema'] . '.users u
				where u.id = m.id_user
					and m.id = ?', [$mid]);

			if ($row)
			{
				if ($row['status'] === 1 || $row['status'] === 2)
				{
					$transaction['letscode_to'] = $row['letscode'] . ' ' . $row['name'];
					$transaction['description'] =  substr($row['content'], 0, 60);
					$transaction['amount'] = $row['amount'];
				}

				if ($app['s_id'] === $row['id_user'])
				{
					if ($app['pp_admin'])
					{
						$transaction['letscode_from'] = '';
					}
					else
					{
						$transaction['letscode_to'] = '';
						$transaction['description'] = '';
						$transaction['amount'] = '';
					}
				}
			}
		}
		else if ($tuid)
		{
			$to_user = $app['user_cache']->get($tuid, $app['tschema']);

			if (in_array($to_user['status'], [1, 2]) || $app['pp_admin'])
			{
				$transaction['letscode_to'] = $app['account']->str($tuid, $app['tschema']);
			}

			if ($tuid === $app['s_id'])
			{
				if ($app['pp_admin'])
				{
					$transaction['letscode_from'] = '';
				}
				else
				{
					$transaction['letscode_to'] = '';
				}
			}
		}
	}

	$app['assets']->add([
		'transaction_add.js',
	]);

	$balance = $app['session_user']['saldo'];

	$systems = [];

	$systems[] = [
		'groupname' => $app['config']->get('systemname', $app['tschema']),
		'id'		=> 'self',
	];

	if (count($app['intersystem_ary']['eland']))
	{
		$eland_urls = [];

		foreach ($app['intersystem_ary']['eland'] as $remote_eland_schema => $host)
		{
			$eland_url = $app['protocol'] . $host;
			$eland_urls[] = $eland_url;
			$map_eland_schema_url[$eland_url] = $remote_eland_schema;
		}

		$eland_systems = $app['db']->executeQuery('select id,
				groupname, url
			from ' . $app['tschema'] . '.letsgroups
			where apimethod = \'elassoap\'
				and url in (?)',
				[$eland_urls],
				[\Doctrine\DBAL\Connection::PARAM_STR_ARRAY]);

		foreach ($eland_systems as $sys)
		{
			$sys['eland'] = true;
			$sys['remote_schema'] = $map_eland_schema_url[$sys['url']];
			$systems[] = $sys;
		}
	}

	if (count($app['intersystem_ary']['elas']))
	{
		$ids = [];

		foreach ($app['intersystem_ary']['elas'] as $key => $name)
		{
			$ids[] = $key;
		}

		$elas_systems = $app['db']->executeQuery('select id, groupname
			from ' . $app['tschema'] . '.letsgroups
			where apimethod = \'elassoap\'
				and id in (?)',
				[$ids],
				[\Doctrine\DBAL\Connection::PARAM_INT_ARRAY]);

		foreach ($elas_systems as $sys)
		{
			$sys['elas'] = true;
			$systems[] = $sys;
		}
	}

	if ($app['intersystem_en'])
	{
		$mail_systems = $app['db']->executeQuery('select l.id, l.groupname
			from ' . $app['tschema'] . '.letsgroups l, ' .
				$app['tschema'] . '.users u
			where l.apimethod = \'mail\'
				and u.letscode = l.localletscode
				and u.status in (1, 2, 7)');

		foreach ($mail_systems as $sys)
		{
			$sys['mail'] = true;
			$systems[] = $sys;
		}
	}

	$systems_en = count($systems) > 1
		&& $app['config']->get('currencyratio', $app['tschema']) > 0;

	$app['heading']->add('Nieuwe transactie');
	$app['heading']->fa('exchange');

	include __DIR__ . '/../include/header.php';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<form  method="post" autocomplete="off">';

	echo '<div class="form-group"';
	echo $app['pp_admin'] ? '' : ' disabled" ';
	echo '>';
	echo '<label for="letscode_from" class="control-label">';
	echo 'Van Account Code';
	echo '</label>';
	echo '<div class="input-group">';
	echo '<span class="input-group-addon">';
	echo '<i class="fa fa-user"></i>';
	echo '</span>';
	echo '<input type="text" class="form-control" ';
	echo 'id="letscode_from" name="letscode_from" ';
	echo 'data-typeahead-source="';
	echo $systems_en ? 'group_self' : 'letscode_to';
	echo '" ';
	echo 'value="';
	echo $transaction['letscode_from'];
	echo '" required';
	echo $app['pp_admin'] ? '' : ' disabled';
	echo '>';
	echo '</div>';
	echo '</div>';

	if ($systems_en)
	{
		echo '<div class="form-group">';
		echo '<label for="group_id" class="control-label">';
		echo 'Aan Systeem</label>';
		echo '<div class="input-group">';
		echo '<span class="input-group-addon">';
		echo '<i class="fa fa-share-alt"></i>';
		echo '</span>';

		echo '<select type="text" class="form-control" ';
		echo 'id="group_id" name="group_id">';

		foreach ($systems as $sys)
		{
			echo '<option value="';
			echo $sys['id'];
			echo '" ';

			$app['typeahead']->ini($app['pp_ary']);

			if ($sys['id'] == 'self')
			{
				echo 'id="group_self" ';

				$app['typeahead']->add('accounts', ['status' => 'active']);

				if ($app['pp_admin'])
				{
					$app['typeahaed']->add('accounts', ['status' => 'inactive'])
						->add('accounts', ['status' => 'ip'])
						->add('accounts', ['status' => 'im']);
				}

				$config_schema = $app['tschema'];
			}
			else if (isset($sys['eland']))
			{
				$remote_schema = $sys['remote_schema'];

				$app['typeahead']->add('eland_intersystem_accounts', [
					'remote_schema'	=> $remote_schema,
				]);

				$config_schema = $remote_schema;
			}
			else if (isset($sys['elas']))
			{
				$app['typeahead']->add('elas_intersystem_accounts', [
					'group_id'	=> $sys['id'],
				]);

				unset($config_schema);
			}
			else if (isset($sys['mail']))
			{
				unset($config_schema);
			}

			$typeahead_process_ary = ['filter' => 'accounts'];

			if (isset($config_schema))
			{
				echo ' data-minlimit="';
				echo $app['config']->get('minlimit', $config_schema) . '"';
				echo ' data-maxlimit="';
				echo $app['config']->get('maxlimit', $config_schema) . '"';
				echo ' data-currency="';
				echo $app['config']->get('currency', $config_schema) . '"';
				echo ' data-currencyratio="';
				echo $app['config']->get('currencyratio', $config_schema) . '"';
				echo ' data-balance-equilibrium="';
				echo $app['config']->get('balance_equilibrium', $config_schema) . '"';

				$typeahead_process_ary['newuserdays'] = $app['config']->get('newuserdays', $config_schema);
			}

			$typeahead = $app['typeahead']->str($typeahead_process_ary);

			if ($typeahead)
			{
				echo ' data-typeahead="' . $typeahead . '"';
			}

			echo $sys['id'] == $group_id ? ' selected="selected"' : '';
			echo '>';
			echo htmlspecialchars($sys['groupname'], ENT_QUOTES);

			if ($sys['id'] === 'self')
			{
				echo ': eigen Systeem';
			}
			else if (isset($sys['eland']) || isset($sys['elas']))
			{
				echo ': interSysteem';
			}
			else if (isset($sys['mail']))
			{
				echo ': manueel interSysteem';
			}

			echo '</option>';
		}

		echo '</select>';
		echo '</div>';
		echo '</div>';
	}
	else
	{
		echo '<input type="hidden" id="group_id" ';
		echo 'name="group_id" value="self">';
	}

	echo '<div class="form-group">';
	echo '<label for="letscode_to" class="control-label">';
	echo 'Aan Account Code';
	echo '</label>';
	echo '<div class="input-group">';
	echo '<span class="input-group-addon">';
	echo '<i class="fa fa-user"></i>';
	echo '</span>';
	echo '<input type="text" class="form-control" ';
	echo 'id="letscode_to" name="letscode_to" ';

	if ($systems_en)
	{
		echo 'data-typeahead-source="group_id" ';
	}
	else
	{
		echo 'data-typeahead="';

		$app['typeahead']->ini($app['pp_ary'])
			->add('accounts', ['status' => 'active']);

		if ($app['pp_admin'])
		{
			$app['typeahead']->add('accounts', ['status' => 'inactive'])
				->add('accounts', ['status' => 'ip'])
				->add('accounts', ['status' => 'im']);
		}

		echo $app['typeahead']->str([
			'filter'		=> 'accounts',
			'newuserdays'	=> $app['config']->get('newuserdays', $app['tschema']),
		]);

		echo '" ';
	}

	echo 'value="';
	echo $transaction['letscode_to'];
	echo '" required>';
	echo '</div>';

	echo '<ul class="account-info" id="account_info">';

	echo '<li id="info_typeahead">Dit veld geeft autosuggesties door ';
	echo 'Naam of Account Code te typen. ';

	if (count($systems) > 1)
	{
		echo 'Indien je een interSysteem transactie doet, ';
		echo 'kies dan eerst het juiste interSysteem om de ';
		echo 'juiste suggesties te krijgen.';
	}

	echo '</li>';
	echo '<li class="hidden" id="info_no_typeahead">';
	echo 'Dit veld geeft GEEN autosuggesties aangezien het geselecteerde ';
	echo 'interSysteem manueel is. Er is geen automatische data-uitwisseling ';
	echo 'met dit Systeem. Transacties worden manueel verwerkt ';
	echo 'door de administratie in het andere Systeem.';
	echo '</li>';
	echo '</ul>';

	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="amount" class="control-label">';
	echo 'Aantal</label>';
	echo '<div class="row">';
	echo '<div class="col-sm-12" id="amount_container">';

	echo '<div class="input-group">';
	echo '<span class="input-group-addon">';
	echo $app['config']->get('currency', $app['tschema']);
	echo '</span>';
	echo '<input type="number" class="form-control" ';
	echo 'id="amount" name="amount" ';
	echo 'value="';
	echo $transaction['amount'];
	echo '" min="1" required>';
	echo '</div>';

	echo '<ul>';

	echo get_valuation($app['tschema']);

	echo '<li id="info_remote_amount_unknown" ';
	echo 'class="hidden">De omrekening ';
	echo 'naar de externe tijdsvaluta ';
	echo 'is niet gekend omdat het andere ';
	echo 'Systeem zich niet op dezelfde ';
	echo 'eLAND-server bevindt.</li>';

	if ($app['pp_admin'])
	{
		echo '<li id="info_admin_limit">';
		echo 'Admins kunnen over en onder limieten gaan';

		if ($app['intersystem_en'])
		{
			echo ' in het eigen Systeem.';
		}

		echo '</li>';
	}

	echo '</ul>';

	echo '</div>'; // amount_container

	echo '<div class="col-sm-6 collapse" ';
	echo 'id="remote_amount_container">';

	echo '<div class="input-group">';
	echo '<span class="input-group-addon">';
	echo '</span>';
	echo '<input type="number" class="form-control" ';
	echo 'id="remote_amount" name="remote_amount" ';
	echo 'value="" min="1">';
	echo '</div>';

	echo '<ul>';

	if ($app['config']->get('template_lets', $app['tschema'])
		&& $app['config']->get('currencyratio', $app['tschema']) > 0)
	{
		echo '<li id="info_ratio">Valuatie: <span class="num">';
		echo '</span> per uur</li>';
	}

	echo '</ul>';

	echo '</div>'; // remote_amount
	echo '</div>'; // form-group

	echo '<div class="form-group">';
	echo '<label for="description" class="control-label">';
	echo 'Omschrijving</label>';
	echo '<div class="input-group">';
	echo '<span class="input-group-addon">';
	echo '<i class="fa fa-pencil"></i>';
	echo '</span>';
	echo '<input type="text" class="form-control" ';
	echo 'id="description" name="description" ';
	echo 'value="';
	echo $transaction['description'];
	echo '" required maxlength="60">';
	echo '</div>';
	echo '</div>';

	echo $app['link']->btn_cancel('transactions', $app['pp_ary'], []);

	echo '&nbsp;';
	echo '<input type="submit" name="zend" ';
	echo 'value="Overschrijven" class="btn btn-success">';
	echo $app['form_token']->get_hidden_input();
	echo '<input type="hidden" name="transid" ';
	echo 'value="';
	echo $transaction['transid'];
	echo '">';

	echo '</form>';
	echo '</div>';
	echo '</div>';

	include __DIR__ . '/../include/footer.php';
	exit;
}

/*
 * interSystem accounts schemas needed for interlinking users.
 */

$intersystem_account_schemas = $app['intersystems']->get_eland_accounts_schemas($app['tschema']);

$s_inter_schema_check = array_merge($app['intersystems']->get_eland($app['tschema']),
	[$app['s_schema'] => true]);

/**
 * show a transaction
 */

if ($id || $edit)
{
	$id = $edit ?: $id;

	$transaction = $app['db']->fetchAssoc('select t.*
		from ' . $app['tschema'] . '.transactions t
		where t.id = ?', [$id]);

	$inter_schema = false;

	if (isset($intersystem_account_schemas[$transaction['id_from']]))
	{
		$inter_schema = $intersystem_account_schemas[$transaction['id_from']];
	}
	else if (isset($intersystem_account_schemas[$transaction['id_to']]))
	{
		$inter_schema = $intersystem_account_schemas[$transaction['id_to']];
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
	if (!$app['pp_admin'])
	{
		$app['alert']->error('Je hebt onvoldoende rechten om
			een omschrijving van een transactie aan te passen.');
		$app['link']->redirect('transactions', $app['pp_ary'], ['id' => $edit]);
	}

	if (!$inter_transaction && ($transaction['real_from'] || $transaction['real_to']))
	{
		$app['alert']->error('De omschrijving van een transactie
			naar een interSysteem dat draait op eLAS kan
			niet aangepast worden.');
		$app['link']->redirect('transactions', $app['pp_ary'], ['id' => $edit]);
	}

	if ($app['request']->isMethod('POST'))
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
			$app['db']->update($app['tschema'] . '.transactions',
				['description' => $description],
				['id' => $edit]);

			if ($inter_transaction)
			{
				$app['db']->update($inter_schema . '.transactions',
					['description' => $description],
					['id' => $inter_transaction['id']]);
			}

			$app['monolog']->info('Transaction description edited from "' . $transaction['description'] .
				'" to "' . $description . '", transid: ' .
				$transaction['transid'], ['schema' => $app['tschema']]);

			$app['alert']->success('Omschrijving transactie aangepast.');

			$app['link']->redirect('transactions', $app['pp_ary'], ['id' => $edit]);
		}

		$app['alert']->error($errors);
	}

	$app['heading']->add('Omschrijving transactie aanpassen');
	$app['heading']->fa('exchange');

	include __DIR__ . '/../include/header.php';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<form  method="post" autocomplete="off">';

	// copied from "show a transaction"

	echo '<dl>';

	echo '<dt>Tijdstip</dt>';
	echo '<dd>';
	echo $app['date_format']->get($transaction['cdate'], 'min', $app['tschema']);
	echo '</dd>';

	echo '<dt>Transactie ID</dt>';
	echo '<dd>';
	echo $transaction['transid'];
	echo '</dd>';

	if ($transaction['real_from'])
	{
		echo '<dt>Van interSysteem account</dt>';
		echo '<dd>';

		if ($app['pp_admin'])
		{
			echo $app['account']->link($transaction['id_from'], $app['pp_ary']);
		}
		else
		{
			echo $app['account']->str($transaction['id_from'], $app['tschema']);
		}

		echo '</dd>';

		echo '<dt>Van interSysteem gebruiker</dt>';
		echo '<dd>';
		echo '<span class="btn btn-default">';
		echo '<i class="fa fa-share-alt"></i></span> ';

		if ($inter_transaction)
		{
			if (isset($s_inter_schema_check[$inter_schema]))
			{
				echo $app['account']->inter_link($inter_transaction['id_from'],
					$inter_schema);
			}
			else
			{
				echo $app['account']->str($inter_transaction['id_from'],
					$inter_schema);
			}
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
		echo $app['account']->link($transaction['id_from'], $app['pp_ary']);
		echo '</dd>';
	}

	if ($transaction['real_to'])
	{
		echo '<dt>Naar interSysteem account</dt>';
		echo '<dd>';

		if ($app['pp_admin'])
		{
			echo $app['account']->link($transaction['id_to'], $app['pp_ary']);
		}
		else
		{
			echo $app['account']->str($transaction['id_to'], $app['tschema']);
		}

		echo '</dd>';

		echo '<dt>Naar interSysteem gebruiker</dt>';
		echo '<dd>';
		echo '<span class="btn btn-default"><i class="fa fa-share-alt"></i></span> ';

		if ($inter_transaction)
		{
			if (isset($s_inter_schema_check[$inter_schema]))
			{
				echo $app['account']->inter_link($inter_transaction['id_to'],
					$inter_schema);
			}
			else
			{
				echo $app['account']->str($inter_transaction['id_to'],
					$inter_schema);
			}
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
		echo $app['account']->link($transaction['id_to'], $app['pp_ary']);
		echo '</dd>';
	}

	echo '<dt>Waarde</dt>';
	echo '<dd>';
	echo $transaction['amount'] . ' ';
	echo $app['config']->get('currency', $app['tschema']);
	echo '</dd>';

	echo '<dt>Omschrijving</dt>';
	echo '<dd>';
	echo $transaction['description'];
	echo '</dd>';

	echo '</dl>';

	echo '<div class="form-group">';
	echo '<label for="description" class="control-label">';
	echo 'Nieuwe omschrijving</label>';
	echo '<div class="input-group">';
	echo '<span class="input-group-addon">';
	echo '<i class="fa fa-pencil"></i>';
	echo '</span>';
	echo '<input type="text" class="form-control" ';
	echo 'id="description" name="description" ';
	echo 'value="';
	echo $transaction['description'];
	echo '" required maxlength="60">';
	echo '</div>';
	echo '</div>';

	echo $app['link']->btn_cancel('transactions', $app['pp_ary'], ['id' => $edit]);

	echo '&nbsp;';
	echo '<input type="submit" name="zend" ';
	echo 'value="Aanpassen" class="btn btn-primary">';
	echo $app['form_token']->get_hidden_input();
	echo '<input type="hidden" name="transid" ';
	echo 'value="';
	echo $transaction['transid'];
	echo '">';

	echo '</form>';
	echo '</div>';
	echo '</div>';

	echo '<ul><i>';
	echo '<li>Omdat dat transacties binnen het netwerk ';
	echo 'zichtbaar zijn voor iedereen kan ';
	echo 'de omschrijving aangepast worden door ';
	echo 'admins in het geval deze ongewenste ';
	echo 'informatie bevat (bvb. een opmerking ';
	echo 'die beledigend is).</li>';
	echo '<li>Pas de omschrijving van een transactie ';
	echo 'enkel aan wanneer het echt noodzakelijk is! ';
	echo 'Dit om verwarring te vermijden.</li>';
	echo '<li>Transacties kunnen nooit ongedaan ';
	echo 'gemaakt worden. Doe een tegenboeking ';
	echo 'bij vergissing.</li>';
	echo '</i></ul>';

	include __DIR__ . '/../include/footer.php';
	exit;
}

/**
 * show a transaction
 */

if ($id)
{
	$next = $app['db']->fetchColumn('select id
		from ' . $app['tschema'] . '.transactions
		where id > ?
		order by id asc
		limit 1', [$id]);

	$prev = $app['db']->fetchColumn('select id
		from ' . $app['tschema'] . '.transactions
		where id < ?
		order by id desc
		limit 1', [$id]);

	if ($app['pp_admin']
		&& ($inter_transaction
			|| !($transaction['real_from']
				|| $transaction['real_to'])))
	{
		$app['btn_top']->edit('transactions', $app['pp_ary'],
			['edit' => $id], 'Omschrijving aanpassen');
	}

	$app['btn_nav']->nav('transactions', $app['pp_ary'],
		$prev_ary, $next_ary, true);

	$app['btn_nav']->nav_list('transactions', $app['pp_ary'],
		[], 'Lijst', 'exchange');

	$app['heading']->add('Transactie');
	$app['heading']->fa('exchange');

	include __DIR__ . '/../include/header.php';

	$real_to = $transaction['real_to'] ? true : false;
	$real_from = $transaction['real_from'] ? true : false;

	$intersystem_trans = ($real_from || $real_to) && $app['intersystem_en'];

	echo '<div class="panel panel-';
	echo $intersystem_trans ? 'warning' : 'default';
	echo ' printview">';
	echo '<div class="panel-heading">';

	echo '<dl>';

	echo '<dt>Tijdstip</dt>';
	echo '<dd>';
	echo $app['date_format']->get($transaction['cdate'], 'min', $app['tschema']);
	echo '</dd>';

	echo '<dt>Transactie ID</dt>';
	echo '<dd>';
	echo $transaction['transid'];
	echo '</dd>';

	if ($real_from)
	{
		echo '<dt>Van interSysteem Account (in dit Systeem)</dt>';
		echo '<dd>';

		if ($app['pp_admin'])
		{
			echo $app['account']->link($transaction['id_from'], $app['pp_ary']);
		}
		else
		{
			echo $app['account']->str($transaction['id_from'], $app['tschema']);
		}

		echo '</dd>';

		echo '<dt>Van Account in het andere Systeem</dt>';
		echo '<dd>';
		echo '<span class="btn btn-default">';
		echo '<i class="fa fa-share-alt"></i></span> ';

		if ($inter_transaction)
		{
			if ($s_inter_schema_check[$inter_schema])
			{
				$user_from = $app['account']->inter_link($inter_transaction['id_from'],
					$inter_schema);
			}
			else
			{
				$user_from = $app['account']->str($inter_transaction['id_from'],
					$inter_schema);
			}
		}
		else
		{
			$user_from = $transaction['real_from'];
		}

		echo $user_from;

		echo '</dd>';
	}
	else
	{
		echo '<dt>Van Account</dt>';
		echo '<dd>';
		echo $app['account']->link($transaction['id_from'], $app['pp_ary']);
		echo '</dd>';
	}

	if ($real_to)
	{
		echo '<dt>Naar interSysteem Account (in dit Systeem)</dt>';
		echo '<dd>';

		if ($app['pp_admin'])
		{
			echo $app['account']->link($transaction['id_to'], $app['pp_ary']);
		}
		else
		{
			echo $app['account']->str($transaction['id_to'], $app['tschema']);
		}

		echo '</dd>';

		echo '<dt>Naar Account in het andere Systeem</dt>';
		echo '<dd>';
		echo '<span class="btn btn-default">';
		echo '<i class="fa fa-share-alt"></i></span> ';

		if ($inter_transaction)
		{
			if ($s_inter_schema_check[$inter_schema])
			{
				$user_to = $app['account']->inter_link($inter_transaction['id_to'],
					$inter_schema);
			}
			else
			{
				$user_to = $app['account']->str($inter_transaction['id_to'],
					$inter_schema);
			}
		}
		else
		{
			$user_to = $transaction['real_to'];
		}

		echo $user_to;

		echo '</dd>';
	}
	else
	{
		echo '<dt>Naar Account</dt>';
		echo '<dd>';
		echo $app['account']->link($transaction['id_to'], $app['pp_ary']);
		echo '</dd>';
	}

	echo '<dt>Waarde</dt>';
	echo '<dd>';
	echo $transaction['amount'] . ' ';
	echo $app['config']->get('currency', $app['tschema']);
	echo '</dd>';

	echo '<dt>Omschrijving</dt>';
	echo '<dd>';
	echo $transaction['description'];
	echo '</dd>';

	echo '</dl>';

	if ($intersystem_trans)
	{
		echo '<div class="row">';
		echo '<div class="col-md-12">';
		echo '<h2>';
		echo 'Dit is een interSysteem transactie ';
		echo $real_from ? 'vanuit' : 'naar';
		echo ' een Account in ander Systeem';
		echo '</h2>';
		echo '<p>';
		echo 'Een interSysteem transactie bestaat ';
		echo 'altijd uit twee gekoppelde transacties, die ';
		echo 'elks binnen hun eigen Systeem plaatsvinden, ';
		echo 'elks uitgedrukt in de eigen tijdsmunt, maar met ';
		echo 'gelijke tijdswaarde in beide transacties. ';
		echo 'De zogenaamde interSysteem Accounts ';
		echo '(in stippellijn) ';
		echo 'doen dienst als intermediair.';
		echo '</p>';
		echo '</div>';
		echo '</div>';

		echo '<div class="row">';

		echo '<div class="col-md-6">';
		echo '<div class="thumbnail">';
		echo '<img src="gfx/';
		echo $real_from ? 'there-from' : 'here-to';
		echo '-inter.png">';
		echo '</div>';
		echo '<div class="caption">';
		echo '<ul>';
		echo '<li>';
		echo '<strong>Acc-1</strong> ';
		echo 'Het Account in ';
		echo $real_from ? 'het andere' : 'dit';
		echo ' Systeem dat de ';
		echo 'transactie initiëerde. ';
		echo '(';

		if ($real_from)
		{
			echo '<span class="btn btn-default">';
			echo '<i class="fa fa-share-alt"></i></span> ';
			echo $user_from;
		}
		else
		{
			echo $app['account']->link($transaction['id_from'], $app['pp_ary']);
		}

		echo ')';
		echo '</li>';
		echo '<li>';
		echo '<strong>Tr-1</strong> ';

		if ($real_from)
		{
			$str = 'De transactie in het andere ';
			$str .= 'Systeem uitgedrukt ';
			$str .= 'in de eigen tijdsmunt.';

			if ($inter_transaction
				&& isset($app['intersystem_ary']['eland'][$inter_schema]))
			{
				echo $app['link']->link_no_attr('transactions', [
						'system'		=> $app['systems']->get_system($inter_schema),
						'role_short'	=> 'g',
					], ['id' => $inter_transaction['id']], $str);
			}
			else
			{
				echo $str;
			}
		}
		else
		{
			echo 'De transactie in dit ';
			echo 'Systeem uitgedrukt ';
			echo 'in de eigen tijdsmunt';
			echo ' (';
			echo $transaction['amount'];
			echo ' ';
			echo $app['config']->get('currency', $app['tschema']);
			echo ').';
		}

		echo '</li>';
		echo '<li>';
		echo '<strong>iAcc-1</strong> ';

		if ($real_from)
		{
			echo 'Het interSysteem Account van dit Systeem in het ';
			echo 'andere Systeem.';
		}
		else
		{
			echo 'Het interSysteem Account van het andere Systeem ';
			echo 'in dit Systeem. (';

			if ($app['pp_admin'])
			{
				echo $app['account']->link($transaction['id_to'],
					$app['pp_ary']);
			}
			else
			{
				echo $app['account']->str($transaction['id_to'],
					$app['tschema']);
			}

			echo ')';
		}

		echo '</li>';
		echo '</ul>';
		echo '</div>';
		echo '</div>';

		echo '<div class="col-md-6">';
		echo '<div class="thumbnail">';
		echo '<img src="gfx/';
		echo $real_from ? 'here-from' : 'there-to';
		echo '-inter.png">';
		echo '</div>';
		echo '<div class="caption bg-warning">';
		echo '<ul>';
		echo '<li>';
		echo '<strong>iAcc-2</strong> ';

		if ($real_from)
		{
			echo 'Het interSysteem Account van ';
			echo 'het andere Systeem in dit ';
			echo 'Systeem. ';
			echo '(';

			if ($app['pp_admin'])
			{
				echo $app['account']->link($transaction['id_from'],
					$app['pp_ary']);
			}
			else
			{
				echo $app['account']->str($transaction['id_from'],
					$app['tschema']);
			}

			echo ')';
		}
		else
		{
			echo 'Het interSysteem Account van dit Systeem in het ';
			echo 'andere Systeem.';
		}

		echo '</li>';
		echo '<li>';
		echo '<strong>Tr-2</strong> ';

		if ($real_from)
		{
			echo 'De transactie in dit Systeem uitgedrukt ';
			echo 'in de eigen tijdsmunt ';
			echo '(';
			echo $transaction['amount'] . ' ';
			echo $app['config']->get('currency', $app['tschema']);
			echo ') ';
			echo 'met gelijke tijdswaarde als Tr-1.';
		}
		else
		{
			$str = 'De transactie in het andere ';
			$str .= 'Systeem uitgedrukt ';
			$str .= 'in de eigen tijdsmunt ';
			$str .= 'met gelijke tijdswaarde als Tr-1.';

			if ($inter_transaction
				&& isset($app['intersystem_ary']['eland'][$inter_schema]))
			{
				echo $app['link']->link_no_attr('transactions', [
						'system'	=> $app['systems']->get_system($inter_schema),
						'role_short'	=> 'g',
					], ['id' => $inter_transaction['id']], $str);
			}
			else
			{
				echo $str;
			}
		}

		echo '</li>';
		echo '<li>';
		echo '<strong>Acc-2</strong> ';

		if ($real_from)
		{
			echo 'Het bestemmings Account in dit Systeem ';
			echo '(';
			echo $app['account']->link($transaction['id_to'], $app['pp_ary']);
			echo ').';
		}
		else
		{
			echo 'Het bestemmings Account in het andere ';
			echo 'Systeem ';
			echo '(';
			echo '<span class="btn btn-default">';
			echo '<i class="fa fa-share-alt"></i></span> ';
			echo $user_to;
			echo ').';
		}

		echo '</li>';
		echo '</ul>';
		echo '</div>';
		echo '</div>';

		echo '</div>';
	}

	echo '</div></div>';

	include __DIR__ . '/../include/footer.php';
	exit;
}

/**
 * list
 */
$pp_inline = $app['request']->query->get('inline', false) ? true : false;

$s_owner = !$app['s_guest']
	&& $app['s_system_self']
	&& isset($filter['uid'])
	&& $app['s_id'] == $filter['uid'];

$params_sql = $where_sql = $where_code_sql = [];

$params = [
	's'	=> [
		'orderby'	=> $sort['orderby'] ?? 'cdate',
		'asc'		=> $sort['asc'] ?? 0,
	],
	'p'	=> [
		'start'		=> $pag['start'] ?? 0,
		'limit'		=> $pag['limit'] ?? 25,
	],
];

if (isset($filter['uid']))
{
 	$filter['fcode'] = $app['account']->str($filter['uid'], $app['tschema']);
	$filter['tcode'] = $filter['fcode'];
	$filter['andor'] = 'or';
	$params['f']['uid'] = $filter['uid'];
}

if (isset($filter['q']) && $filter['q'])
{
	$where_sql[] = 't.description ilike ?';
	$params_sql[] = '%' . $filter['q'] . '%';
	$params['f']['q'] = $filter['q'];
}

if (isset($filter['fcode']) && $filter['fcode'])
{
	[$fcode] = explode(' ', trim($filter['fcode']));
	$fcode = trim($fcode);

	$fuid = $app['db']->fetchColumn('select id
		from ' . $app['tschema'] . '.users
		where letscode = ?', [$fcode]);

	if ($fuid)
	{
		$fuid_sql = 't.id_from ';
		$fuid_sql .= $filter['andor'] === 'nor' ? '<>' : '=';
		$fuid_sql .= ' ?';
		$where_code_sql[] = $fuid_sql;
		$params_sql[] = $fuid;

		$fcode = $app['account']->str($fuid, $app['tschema']);
	}
	else if ($filter['andor'] !== 'nor')
	{
		$where_code_sql[] = '1 = 2';
	}

	$params['f']['fcode'] = $fcode;
}

if (isset($filter['tcode']) && $filter['tcode'])
{
	[$tcode] = explode(' ', trim($filter['tcode']));

	$tuid = $app['db']->fetchColumn('select id
		from ' . $app['tschema'] . '.users
		where letscode = \'' . $tcode . '\'');

	if ($tuid)
	{
		$tuid_sql = 't.id_to ';
		$tuid_sql .= $filter['andor'] === 'nor' ? '<>' : '=';
		$tuid_sql .= ' ?';
		$where_code_sql[] = $tuid_sql;
		$params_sql[] = $tuid;

		$tcode = $app['account']->str($tuid, $app['tschema']);
	}
	else if ($filter['andor'] !== 'nor')
	{
		$where_code_sql[] = '1 = 2';
	}

	$params['f']['tcode'] = $tcode;
}

if (count($where_code_sql) > 1 && $filter['andor'] === 'or')
{
	$where_code_sql = [' ( ' . implode(' or ', $where_code_sql) . ' ) '];
}

$where_sql = array_merge($where_sql, $where_code_sql);

if (isset($filter['fdate']) && $filter['fdate'])
{
	$fdate_sql = $app['date_format']->reverse($filter['fdate'], $app['tschema']);

	if ($fdate_sql === '')
	{
		$app['alert']->warning('De begindatum is fout geformateerd.');
	}
	else
	{
		$where_sql[] = 't.date >= ?';
		$params_sql[] = $fdate_sql;
		$params['f']['fdate'] = $fdate = $filter['fdate'];
	}
}

if (isset($filter['tdate']) && $filter['tdate'])
{
	$tdate_sql = $app['date_format']->reverse($filter['tdate'], $app['tschema']);

	if ($tdate_sql === '')
	{
		$app['alert']->warning('De einddatum is fout geformateerd.');
	}
	else
	{
		$where_sql[] = 't.date <= ?';
		$params_sql[] = $tdate_sql;
		$params['f']['tdate'] = $tdate = $filter['tdate'];
	}
}

if (count($where_sql))
{
	$where_sql = ' where ' . implode(' and ', $where_sql) . ' ';
	$params['f']['andor'] = $filter['andor'];
}
else
{
	$where_sql = '';
}

$query = 'select t.*
	from ' . $app['tschema'] . '.transactions t ' .
	$where_sql . '
	order by t.' . $params['s']['orderby'] . ' ';
$query .= $params['s']['asc'] ? 'asc ' : 'desc ';
$query .= ' limit ' . $params['p']['limit'];
$query .= ' offset ' . $params['p']['start'];

$transactions = $app['db']->fetchAll($query, $params_sql);

foreach ($transactions as $key => $t)
{
	if (!($t['real_from'] || $t['real_to']))
	{
		continue;
	}

	$inter_schema = false;

	if (isset($intersystem_account_schemas[$t['id_from']]))
	{
		$inter_schema = $intersystem_account_schemas[$t['id_from']];
	}
	else if (isset($intersystem_account_schemas[$t['id_to']]))
	{
		$inter_schema = $intersystem_account_schemas[$t['id_to']];
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

$row = $app['db']->fetchAssoc('select count(t.*), sum(t.amount)
	from ' . $app['tschema'] . '.transactions t ' .
	$where_sql, $params_sql);

$row_count = $row['count'];
$amount_sum = $row['sum'];

$app['pagination']->init('transactions', $app['pp_ary'],
	$row_count, $params, $pp_inline);

$asc_preset_ary = [
	'asc'	=> 0,
	'fa' 	=> 'sort',
];

$tableheader_ary = [
	'description' => array_merge($asc_preset_ary, [
		'lbl' => 'Omschrijving']),
	'amount' => array_merge($asc_preset_ary, [
		'lbl' => $app['config']->get('currency', $app['tschema'])]),
	'cdate'	=> array_merge($asc_preset_ary, [
		'lbl' 		=> 'Tijdstip',
		'data_hide' => 'phone'])
];

if (isset($filter['uid']))
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

$tableheader_ary[$params['s']['orderby']]['asc']
	= $params['s']['asc'] ? 0 : 1;
$tableheader_ary[$params['s']['orderby']]['fa']
	= $params['s']['asc'] ? 'sort-asc' : 'sort-desc';

if (!$pp_inline && ($app['pp_admin'] || $app['pp_user']))
{
	if (isset($filter['uid']))
	{
		$user_str = $app['account']->str($user['id'], $app['tschema']);

		if ($user['status'] != 7)
		{
			if ($s_owner)
			{
				$app['btn_top']->add('transactions', $app['pp_ary'],
					['add' => 1], 'Transactie toevoegen');
			}
			else
			{
				$app['btn_top']->add_trans('transactions', $app['pp_ary'],
					['add' => 1, 'tuid' => $user['id']],
					'Transactie naar ' . $user_str);
			}
		}
	}
	else
	{
		$app['btn_top']->add('transactions', $app['pp_ary'],
			['add' => 1], 'Transactie toevoegen');
	}
}

if ($app['pp_admin'])
{
	$app['btn_nav']->csv();
}

$filtered = !isset($filter['uid']) && (
	(isset($filter['q']) && $filter['q'] !== '')
	|| (isset($filter['fcode']) && $filter['fcode'] !== '')
	|| (isset($filter['tcode']) && $filter['tcode'] !== '')
	|| (isset($filter['fdate']) && $filter['fdate'] !== '')
	|| (isset($filter['tdate']) && $filter['tdate'] !== ''));

if (isset($filter['uid']))
{
	if ($s_owner && !$pp_inline)
	{
		$app['heading']->add('Mijn transacties');
	}
	else
	{
		$app['heading']->add($app['link']->link_no_attr('transactions', $app['pp_ary'],
			['f' => ['uid' => $filter['uid']]], 'Transacties'));

		$app['heading']->add(' van ');
		$app['heading']->add($app['account']->link($filter['uid'], $app['pp_ary']));
	}
}
else
{
	$app['heading']->add('Transacties');
	$app['heading']->add_filtered($filtered);
}

$app['heading']->fa('exchange');

if (!$pp_inline)
{
	$app['heading']->btn_filter();

	$app['assets']->add(['datepicker']);

	include __DIR__ . '/../include/header.php';

	echo '<div class="panel panel-info';
	echo $filtered ? '' : ' collapse';
	echo '" id="filter">';
	echo '<div class="panel-heading">';

	echo '<form method="get" class="form-horizontal">';

	echo '<div class="row">';

	echo '<div class="col-sm-12">';
	echo '<div class="input-group margin-bottom">';
	echo '<span class="input-group-addon">';
	echo '<i class="fa fa-search"></i>';
	echo '</span>';
	echo '<input type="text" class="form-control" id="q" value="';
	echo $filter['q'] ?? '';
	echo '" name="f[q]" placeholder="Zoekterm">';
	echo '</div>';
	echo '</div>';

	echo '</div>';

	echo '<div class="row">';

	echo '<div class="col-sm-5">';
	echo '<div class="input-group margin-bottom">';
	echo '<span class="input-group-addon" id="fcode_addon">Van ';
	echo '<span class="fa fa-user"></span></span>';

	$app['typeahead']->ini($app['pp_ary'])
		->add('accounts', ['status' => 'active']);

	if (!$app['s_guest'])
	{
		$app['typeahead']->add('accounts', ['status' => 'extern']);
	}

	if ($app['pp_admin'])
	{
		$app['typeahead']->add('accounts', ['status' => 'inactive']);
		$app['typeahead']->add('accounts', ['status' => 'ip']);
		$app['typeahead']->add('accounts', ['status' => 'im']);
	}

	echo '<input type="text" class="form-control" ';
	echo 'aria-describedby="fcode_addon" ';

	echo 'data-typeahead="';
	echo $app['typeahead']->str([
		'filter'		=> 'accounts',
		'newuserdays'	=> $app['config']->get('newuserdays', $app['tschema']),
	]);
	echo '" ';

	echo 'name="f[fcode]" id="fcode" placeholder="Account Code" ';
	echo 'value="';
	echo $fcode ?? '';
	echo '">';

	echo '</div>';
	echo '</div>';

	$andor_options = [
		'and'	=> 'EN',
		'or'	=> 'OF',
		'nor'	=> 'NOCH',
	];

	echo '<div class="col-sm-2">';
	echo '<select class="form-control margin-bottom" name="f[andor]">';
	echo $app['select']->get_options($andor_options, $filter['andor'] ?? 'and');
	echo '</select>';
	echo '</div>';

	echo '<div class="col-sm-5">';
	echo '<div class="input-group margin-bottom">';
	echo '<span class="input-group-addon" id="tcode_addon">Naar ';
	echo '<span class="fa fa-user"></span></span>';
	echo '<input type="text" class="form-control margin-bottom" ';
	echo 'data-typeahead-source="fcode" ';
	echo 'placeholder="Account Code" ';
	echo 'aria-describedby="tcode_addon" ';
	echo 'name="f[tcode]" value="';
	echo $tcode ?? '';
	echo '">';
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

	echo 'id="fdate" name="f[fdate]" ';
	echo 'value="';
	echo $fdate ?? '';
	echo '" ';
	echo 'data-provide="datepicker" ';
	echo 'data-date-format="';
	echo $app['date_format']->datepicker_format($app['tschema']);
	echo '" ';
	echo 'data-date-default-view-date="-1y" ';
	echo 'data-date-end-date="0d" ';
	echo 'data-date-language="nl" ';
	echo 'data-date-today-highlight="true" ';
	echo 'data-date-autoclose="true" ';
	echo 'data-date-immediate-updates="true" ';
	echo 'data-date-orientation="bottom" ';
	echo 'placeholder="';
	echo $app['date_format']->datepicker_placeholder($app['tschema']);
	echo '">';

	echo '</div>';
	echo '</div>';

	echo '<div class="col-sm-5">';
	echo '<div class="input-group margin-bottom">';
	echo '<span class="input-group-addon" id="tdate_addon">Tot ';
	echo '<span class="fa fa-calendar"></span></span>';
	echo '<input type="text" class="form-control margin-bottom" ';
	echo 'aria-describedby="tdate_addon" ';

	echo 'id="tdate" name="f[tdate]" ';
	echo 'value="';
	echo $tdate ?? '';
	echo '" ';
	echo 'data-provide="datepicker" ';
	echo 'data-date-format="';
	echo $app['date_format']->datepicker_format($app['tschema']);
	echo '" ';
	echo 'data-date-end-date="0d" ';
	echo 'data-date-language="nl" ';
	echo 'data-date-today-highlight="true" ';
	echo 'data-date-autoclose="true" ';
	echo 'data-date-immediate-updates="true" ';
	echo 'data-date-orientation="bottom" ';
	echo 'placeholder="';
	echo $app['date_format']->datepicker_placeholder($app['tschema']);
	echo '">';

	echo '</div>';
	echo '</div>';

	echo '<div class="col-sm-2">';
	echo '<input type="submit" value="Toon" ';
	echo 'class="btn btn-default btn-block">';
	echo '</div>';

	echo '</div>';

	$params_form = $params;
	unset($params_form['f']);
	unset($params_form['uid']);
	unset($params_form['p']['start']);

	$params_form = http_build_query($params_form, 'prefix', '&');
	$params_form = urldecode($params_form);
	$params_form = explode('&', $params_form);

	foreach ($params_form as $param)
	{
		[$name, $value] = explode('=', $param);

		if (!isset($value) || $value === '')
		{
			continue;
		}

		echo '<input name="' . $name . '" ';
		echo 'value="' . $value . '" type="hidden">';
	}

	echo '</form>';

	echo '</div>';
	echo '</div>';
}
else
{
	echo '<div class="row">';
	echo '<div class="col-md-12">';

	$app['heading']->add_inline_btn($app['btn_top']->get());
	echo $app['heading']->get_h3();
}

echo $app['pagination']->get();

if (!count($transactions))
{
	echo '<br>';
	echo '<div class="panel panel-default">';
	echo '<div class="panel-body">';
	echo '<p>Er zijn geen resultaten.</p>';
	echo '</div></div>';
	echo $app['pagination']->get();

	if (!$pp_inline)
	{
		include __DIR__ . '/../include/footer.php';
	}
	exit;
}

echo '<div class="panel panel-primary printview">';
echo '<div class="table-responsive">';
echo '<table class="table table-bordered table-striped ';
echo 'table-hover footable csv transactions" ';
echo 'data-sort="false">';
echo '<thead>';
echo '<tr>';

foreach ($tableheader_ary as $key_orderby => $data)
{
	echo '<th';

	if (isset($data['data_hide']))
	{
		echo ' data-hide="';
		echo $data['data_hide'];
		echo '"';
	}

	echo '>';

	if (isset($data['no_sort']))
	{
		echo $data['lbl'];
	}
	else
	{
		$h_params = $params;

		$h_params['s'] = [
			'orderby' 	=> $key_orderby,
			'asc'		=> $data['asc'],
		];

		echo $app['link']->link_fa('transactions', $app['pp_ary'],
			$h_params, $data['lbl'], [], $data['fa']);
	}

	echo '</th>';
}

echo '</tr>';
echo '</thead>';
echo '<tbody>';

if (isset($filter['uid']))
{
	foreach($transactions as $t)
	{
		echo '<tr';

		if ($app['intersystem_en'] && ($t['real_to'] || $t['real_from']))
		{
			echo ' class="warning"';
		}

		echo '>';
		echo '<td>';

		echo $app['link']->link_no_attr('transactions', $app['pp_ary'],
			['id' => $t['id']], $t['description']);

		echo '</td>';

		echo '<td>';
		echo '<span class="text-';

		if ($t['id_from'] == $filter['uid'])
		{
			echo 'danger">-';
		}
		else
		{
			echo 'success">+';
		}

		echo $t['amount'];
		echo '</span></td>';

		echo '<td>';
		echo $app['date_format']->get($t['cdate'], 'min', $app['tschema']);
		echo '</td>';

		echo '<td>';

		if ($t['id_from'] == $filter['uid'])
		{
			if ($t['real_to'])
			{
				echo '<span class="btn btn-default">';
				echo '<i class="fa fa-share-alt"></i></span> ';

				if (isset($t['inter_transaction']))
				{
					if ($s_inter_schema_check[$t['inter_schema']])
					{
						echo $app['account']->inter_link($t['inter_transaction']['id_to'],
							$t['inter_schema']);
					}
					else
					{
						echo $app['account']->str($t['inter_transaction']['id_to'],
							$t['inter_schema']);
					}
				}
				else
				{
					echo $t['real_to'];
				}

				echo '</dd>';
			}
			else
			{
				echo $app['account']->link($t['id_to'], $app['pp_ary']);
			}
		}
		else
		{
			if ($t['real_from'])
			{
				echo '<span class="btn btn-default">';
				echo '<i class="fa fa-share-alt"></i></span> ';

				if (isset($t['inter_transaction']))
				{
					if ($s_inter_schema_check[$t['inter_schema']])
					{
						echo $app['account']->inter_link($t['inter_transaction']['id_from'],
							$t['inter_schema']);
					}
					else
					{
						echo $app['account']->str($t['inter_transaction']['id_from'],
							$t['inter_schema']);
					}
				}
				else
				{
					echo $t['real_from'];
				}

				echo '</dd>';
			}
			else
			{
				echo $app['account']->link($t['id_from'], $app['pp_ary']);
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
		echo '<tr';

		if ($app['intersystem_en'] && ($t['real_to'] || $t['real_from']))
		{
			echo ' class="warning"';
		}

		echo '>';
		echo '<td>';
		echo $app['link']->link_no_attr('transactions', $app['pp_ary'],
			['id' => $t['id']], $t['description']);
		echo '</td>';

		echo '<td>';
		echo $t['amount'];
		echo '</td>';

		echo '<td>';
		echo $app['date_format']->get($t['cdate'], 'min', $app['tschema']);
		echo '</td>';

		echo '<td>';

		if ($t['real_from'])
		{
			echo '<span class="btn btn-default">';
			echo '<i class="fa fa-share-alt"></i></span> ';

			if (isset($t['inter_transaction']))
			{
				if ($s_inter_schema_check[$t['inter_schema']])
				{
					echo $app['account']->inter_link($t['inter_transaction']['id_from'],
						$t['inter_schema']);
				}
				else
				{
					echo $app['account']->str($t['inter_transaction']['id_from'],
						$t['inter_schema']);
				}
			}
			else
			{
				echo $t['real_from'];
			}

			echo '</dd>';
		}
		else
		{
			echo $app['account']->link($t['id_from'], $app['pp_ary']);
		}

		echo '</td>';

		echo '<td>';

		if ($t['real_to'])
		{
			echo '<span class="btn btn-default">';
			echo '<i class="fa fa-share-alt"></i></span> ';

			if (isset($t['inter_transaction']))
			{
				if ($s_inter_schema_check[$t['inter_schema']])
				{
					echo $app['account']->inter_link($t['inter_transaction']['id_to'],
						$t['inter_schema']);
				}
				else
				{
					echo $app['account']->str($t['inter_transaction']['id_to'],
						$t['inter_schema']);
				}
			}
			else
			{
				echo $t['real_to'];
			}

			echo '</dd>';
		}
		else
		{
			echo $app['account']->link($t['id_to'], $app['pp_ary']);
		}

		echo '</td>';

		echo '</tr>';
	}
}

echo '</table></div></div>';

echo $app['pagination']->get();

if ($pp_inline)
{
	echo '</div></div>';
}
else
{
	echo '<ul>';
	echo '<li>';
	echo 'Totaal: ';
	echo '<strong>';
	echo $amount_sum;
	echo '</strong> ';
	echo $app['config']->get('currency', $app['tschema']);
	echo '</li>';
	echo get_valuation($app['tschema']);
	echo '</ul>';

	include __DIR__ . '/../include/footer.php';
}

function get_valuation(string $schema):string
{
	global $app;

	$out = '';

	if ($app['config']->get('template_lets', $schema)
		&& $app['config']->get('currencyratio', $schema) > 0)
	{
		$out .= '<li id="info_ratio">Valuatie: <span class="num">';
		$out .= $app['config']->get('currencyratio', $schema);
		$out .= '</span> ';
		$out .= $app['config']->get('currency', $schema);
		$out .= ' per uur</li>';
	}

	return $out;
}
