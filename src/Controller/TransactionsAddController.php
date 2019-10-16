<?php declare(strict_types=1);

namespace App\Controller;

use App\Queue\MailQueue;
use App\Render\AccountRender;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\AssetsService;
use App\Service\AutoMinLimitService;
use App\Service\CacheService;
use App\Service\ConfigService;
use App\Service\FormTokenService;
use App\Service\IntersystemsService;
use App\Service\MailAddrSystemService;
use App\Service\MailTransactionService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\SystemsService;
use App\Service\TransactionService;
use App\Service\TypeaheadService;
use App\Service\UserCacheService;
use Predis\Client as Predis;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use controller\transactions;
use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;

class TransactionsAddController extends AbstractController
{
    public function __invoke(
        Request $request,
        Db $db,
        Predis $predis,
        LoggerInterface $logger,
        AccountRender $account_render,
        AlertService $alert_service,
        AssetsService $assets_service,
        ConfigService $config_service,
        CacheService $cache_service,
        FormTokenService $form_token_service,
        HeadingRender $heading_render,
        IntersystemsService $intersystems_service,
        LinkRender $link_render,
        MailAddrSystemService $mail_addr_system_service,
        MailQueue $mail_queue,
        TransactionService $transaction_service,
        MailTransactionService $mail_transaction_service,
        SystemsService $systems_service,
        TypeaheadService $typeahead_service,
        AutoMinLimitService $autominlimit_service,
        UserCacheService $user_cache_service,
        PageParamsService $pp,
        SessionUserService $su,
        MenuService $menu_service
    ):Response
    {
        $errors = [];

        $mid = (int) $request->query->get('mid', 0);
        $tuid = (int) $request->query->get('tuid', 0);
        $tus = $request->query->get('tus', '');

        $currency = $config_service->get('currency', $pp->schema());

        $transaction = [];

        $redis_transid_key = $pp->schema() . '_transid_u_' . $su->id();

        if ($request->isMethod('POST'))
        {
            $stored_transid = $predis->get($redis_transid_key);

            if (!$stored_transid)
            {
                $errors[] = 'Formulier verlopen.';
            }

            $transaction['transid'] = trim($request->request->get('transid', ''));
            $transaction['description'] = trim($request->request->get('description', ''));

            [$letscode_from] = explode(' ', trim($request->request->get('letscode_from', '')));
            [$letscode_to] = explode(' ', trim($request->request->get('letscode_to', '')));

            $transaction['amount'] = $amount = ltrim($request->request->get('amount', ''), '0 ');
            $transaction['date'] = gmdate('Y-m-d H:i:s');
            $transaction['creator'] = $su->is_master() ? 0 : $su->id();

            $group_id = trim($request->request->get('group_id', ''));

            if ($stored_transid != $transaction['transid'])
            {
                $errors[] = 'Fout transactie id.';
            }

            if ($db->fetchColumn('select transid
                from ' . $pp->schema() . '.transactions
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
                $group = $db->fetchAssoc('select *
                    from ' . $pp->schema() . '.letsgroups
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

            if ($pp->is_user() && !$su->is_master())
            {
                $fromuser = $db->fetchAssoc('select *
                    from ' . $pp->schema() . '.users
                    where id = ?', [$su->id()]);
            }
            else
            {
                $fromuser = $db->fetchAssoc('select *
                    from ' . $pp->schema() . '.users
                    where letscode = ?', [$letscode_from]);
            }

            $letscode_touser = $group_id == 'self' ? $letscode_to : $group['localletscode'];

            $touser = $db->fetchAssoc('select *
                from ' . $pp->schema() . '.users
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

            if (!$pp->is_admin() && !count($errors))
            {
                if ($fromuser['minlimit'] === -999999999)
                {
                    $minlimit = $config_service->get('minlimit', $pp->schema());

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

            if (!$pp->is_admin() && !count($errors))
            {
                if ($touser['maxlimit'] === 999999999)
                {
                    $maxlimit = $config_service->get('maxlimit', $pp->schema());

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
                && !$pp->is_admin()
                && !($touser['status'] == '1' || $touser['status'] == '2')
                && !count($errors))
            {
                $errors[] = 'Het bestemmings Account (Aan Account Code) is niet actief';
            }

            if ($pp->is_user() && !count($errors))
            {
                $balance_eq = $config_service->get('balance_equilibrium', $pp->schema());

                if (($fromuser['status'] == 2) && (($fromuser['saldo'] - $amount) < $balance_eq))
                {
                    $err = 'Als Uitstapper kan je geen ';
                    $err .= $amount;
                    $err .= ' ';
                    $err .= $config_service->get('currency', $pp->schema());
                    $err .= ' uitgeven.';
                    $errors[] = $err;
                }

                if (($touser['status'] == 2) && (($touser['saldo'] + $amount) > $balance_eq))
                {
                    $err = 'Het ';
                    $err .= $group_id === 'self' ? 'bestemmings Account (Aan Account Code)' : 'interSysteem Account (op dit Systeem)';
                    $err .= ' heeft de status \'Uitstapper\' en kan geen ';
                    $err .= $amount . ' ';
                    $err .= $config_service->get('currency', $pp->schema());
                    $err .= ' ontvangen.';
                    $errors[] = $err;
                }
            }

            if ($error_token = $form_token_service->get_error())
            {
                $errors[] = $error_token;
            }

            $contact_admin = $pp->is_admin() ? '' : ' Contacteer een admin.';

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
                $alert_service->error($errors);
            }
            else if ($group_id == 'self')
            {
                if ($id = $transaction_service->insert($transaction, $pp->schema()))
                {
                    $transaction['id'] = $id;
                    $mail_transaction_service->queue($transaction, $pp->schema());
                    $alert_service->success('Transactie opgeslagen');
                }
                else
                {
                    $alert_service->error('Gefaalde transactie');
                }

                $link_render->redirect('transactions', $pp->ary(), []);
            }
            else if ($group['apimethod'] == 'mail')
            {
                $transaction['real_to'] = $letscode_to;

                if ($id = $transaction_service->insert($transaction, $pp->schema()))
                {
                    $transaction['id'] = $id;
                    $transaction['letscode_to'] = $letscode_to;

                    $mail_transaction_service->queue_mail_type($transaction, $pp->schema());

                    $alert_service->success('InterSysteem transactie opgeslagen. Een E-mail werd
                        verstuurd naar de administratie van het andere Systeem om de transactie aldaar
                        manueel te verwerken.');
                }
                else
                {
                    $alert_service->error('Gefaalde interSysteem transactie');
                }

                $link_render->redirect('transactions', $pp->ary(), []);
            }
            else if ($group['apimethod'] != 'elassoap')
            {
                $alert_service->error('InterSysteem ' .
                    $group['groupname'] .
                    ' heeft geen geldige Api Methode.' . $contact_admin);
                $link_render->redirect('transactions', $pp->ary(), []);
            }
            else if (!$group_domain)
            {
                $alert_service->error('Geen URL ingesteld voor interSysteem ' .
                    $group['groupname'] . '. ' . $contact_admin);
                $link_render->redirect('transactions', $pp->ary(), []);
            }
            else if (!$systems_service->get_schema_from_legacy_eland_origin($group['url']))
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

                $currencyratio = $config_service->get('currencyratio', $pp->schema());

                if (!$currencyratio || !ctype_digit((string) $currencyratio) || $currencyratio < 1)
                {
                    $errors[] = 'De Currency Ratio is niet correct ingesteld. ' . $contact_admin;
                }

                if (strlen($letscode_to))
                {
                    $active_users = $cache_service->get($group['domain'] . '_typeahead_data');

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
                    $alert_service->error($errors);
                    $link_render->redirect('transactions', $pp->ary(), []);
                }

                $trans = $transaction;

                $trans['amount'] = $trans['amount'] / $currencyratio;
                $trans['amount'] = (float) $trans['amount'];
                $trans['amount'] = round($trans['amount'], 5);

                $trans['letscode_to'] = $letscode_to;

                $soapurl = $group['elassoapurl'] ?: $group['url'] . '/soap';
                $soapurl .= '/wsdlelas.php?wsdl';

                $client = new \nusoap_client($soapurl, true);

                $error = $client->getError();

                if ($error)
                {
                    $alert_service->error('eLAS soap error: ' . $error . ' <br>' . $contact_admin);
                    $link_render->redirect('transactions', $pp->ary(), []);
                }

                $result = $client->call('dopayment', [
                    'apikey' 		=> $group['remoteapikey'],
                    'from' 			=> $group['myremoteletscode'],
                    'real_from' 	=> $account_render->str($fromuser['id'], $pp->schema()),
                    'to' 			=> $letscode_to,
                    'description' 	=> $trans['description'],
                    'amount' 		=> $trans['amount'],
                    'transid' 		=> $trans['transid'],
                    'signature' 	=> $transaction_service->sign($trans, trim($group['presharedkey']), $pp->schema()),
                ]);

                $error = $client->getError();

                if ($error)
                {
                    $alert_service->error('eLAS soap error: ' . $error . ' <br>' . $contact_admin);
                    $link_render->redirect('transactions', $pp->ary(), []);
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
                    $alert_service->error($errors);
                    $link_render->redirect('transactions', $pp->ary(), []);
                }

                $transaction['real_to'] = $letscode_to . ' ' . $real_name_to;

                $logger->debug('insert transation: --  ' .
                    http_build_query($transaction) .
                    ' --', ['schema' => $pp->schema()]);

                $id = $transaction_service->insert($transaction, $pp->schema());

                if (!$id)
                {
                    $mail_queue->queue([
                        'schema'		=> $pp->schema(),
                        'to' 			=> $mail_addr_system_service->get_admin($pp->schema()),
                        'template'		=> 'transaction/intersystem_fail',
                        'vars'			=> [
                            'remote_system_name'	=> $group['groupname'],
                            'transaction'			=> $transaction,
                        ],
                    ], 9000);

                    $alert_service->error('De lokale commit van de interSysteem
                        transactie is niet geslaagd. ' .
                        $contact_admin);

                    $link_render->redirect('transactions', $pp->ary(), []);
                }

                $transaction['id'] = $id;

                // to eLAS intersystem
                $mail_transaction_service->queue($transaction, $pp->schema());

                $alert_service->success('De interSysteem transactie werd verwerkt.');
                $link_render->redirect('transactions', $pp->ary(), []);
            }
            else
            {
                // the interSystem group is on the same server (eLAND)

                $remote_schema = $systems_service->get_schema_from_legacy_eland_origin($group['url']);

                $to_remote_user = $db->fetchAssoc('select *
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

                $legacy_eland_origin = $systems_service->get_legacy_eland_origin($pp->schema());

                $remote_group = $db->fetchAssoc('select *
                    from ' . $remote_schema . '.letsgroups
                    where url = ?', [$legacy_eland_origin]);

                if (!$remote_group && !count($errors))
                {
                    $err = 'Het andere Systeem heeft dit Systeem (';
                    $err .= $config_service->get('systemname', $pp->schema());
                    $err .= ') niet geconfigureerd als interSysteem.';
                    $errors[] = $err;
                }

                if (!$remote_group['localletscode'] && !count($errors))
                {
                    $errors[] = 'Er is geen interSysteem Account gedefiniëerd in het andere Systeem.';
                }

                $remote_interlets_account = $db->fetchAssoc('select *
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

                $remote_currency = $config_service->get('currency', $remote_schema);
                $remote_currencyratio = $config_service->get('currencyratio', $remote_schema);
                $remote_balance_eq = $config_service->get('balance_equilibrium', $remote_schema);
                $currencyratio = $config_service->get('currencyratio', $pp->schema());

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

                $remote_amount = (int) round(($transaction['amount'] * $remote_currencyratio) / $currencyratio);

                if (($remote_amount < 1) && !count($errors))
                {
                    $errors[] = 'Het bedrag is te klein want het kan niet uitgedrukt worden in de gebruikte munt van het andere Systeem.';
                }

                if (!count($errors))
                {
                    if ($remote_interlets_account['minlimit'] === -999999999)
                    {
                        $minlimit = $config_service->get('minlimit', $remote_schema);

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
                    $err .= $config_service->get('currency', $pp->schema());
                    $err .= ').';
                    $errors[] = $err;
                }

                if (!count($errors))
                {
                    if ($to_remote_user['maxlimit'] === 999999999)
                    {
                        $maxlimit = $config_service->get('maxlimit', $remote_schema);

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
                    $err .= $config_service->get('currency', $pp->schema());
                    $err .= ').';
                    $errors[] = $err;
                }

                if (count($errors))
                {
                    $alert_service->error($errors);
                }
                else
                {
                    $transaction['creator'] = $su->is_master() ? 0 : $su->id();
                    $transaction['cdate'] = gmdate('Y-m-d H:i:s');
                    $transaction['real_to'] = $to_remote_user['letscode'] . ' ' . $to_remote_user['name'];

                    $db->beginTransaction();

                    try
                    {
                        $db->insert($pp->schema() . '.transactions', $transaction);
                        $id = $db->lastInsertId($pp->schema() . '.transactions_id_seq');
                        $db->executeUpdate('update ' . $pp->schema() . '.users
                            set saldo = saldo + ? where id = ?',
                            [$transaction['amount'], $transaction['id_to']]);
                        $db->executeUpdate('update ' . $pp->schema() . '.users
                            set saldo = saldo - ? where id = ?',
                            [$transaction['amount'], $transaction['id_from']]);

                        $trans_org = $transaction;
                        $trans_org['id'] = $id;

                        $transaction['creator'] = 0;
                        $transaction['amount'] = $remote_amount;
                        $transaction['id_from'] = $remote_interlets_account['id'];
                        $transaction['id_to'] = $to_remote_user['id'];
                        $transaction['real_from'] = $account_render->str($fromuser['id'], $pp->schema());

                        unset($transaction['real_to']);

                        $db->insert($remote_schema . '.transactions', $transaction);
                        $id = $db->lastInsertId($remote_schema . '.transactions_id_seq');
                        $db->executeUpdate('update ' . $remote_schema . '.users
                            set saldo = saldo + ? where id = ?',
                            [$remote_amount, $transaction['id_to']]);
                        $db->executeUpdate('update ' . $remote_schema . '.users
                            set saldo = saldo - ? where id = ?',
                            [$transaction['amount'], $transaction['id_from']]);
                        $transaction['id'] = $id;

                        $db->commit();

                    }
                    catch(Exception $e)
                    {
                        $db->rollback();
                        $alert_service->error('Transactie niet gelukt.');
                        throw $e;
                        exit;
                    }

                    $user_cache_service->clear($fromuser['id'], $pp->schema());
                    $user_cache_service->clear($touser['id'], $pp->schema());

                    $user_cache_service->clear($remote_interlets_account['id'], $remote_schema);
                    $user_cache_service->clear($to_remote_user['id'], $remote_schema);

                    // to eLAND interSystem
                    $mail_transaction_service->queue($trans_org, $pp->schema());
                    $mail_transaction_service->queue($transaction, $remote_schema);

                    $logger->info('direct interSystem transaction ' . $transaction['transid'] . ' amount: ' .
                        $amount . ' from user: ' .  $account_render->str_id($fromuser['id'], $pp->schema()) .
                        ' to user: ' . $account_render->str_id($touser['id'], $pp->schema()),
                        ['schema' => $pp->schema()]);

                    $logger->info('direct interSystem transaction (receiving) ' . $transaction['transid'] .
                        ' amount: ' . $remote_amount . ' from user: ' . $remote_interlets_account['letscode'] . ' ' .
                        $remote_interlets_account['name'] . ' to user: ' . $to_remote_user['letscode'] . ' ' .
                        $to_remote_user['name'], ['schema' => $remote_schema]);

                    $autominlimit_service->init($pp->schema())
                        ->process($transaction['id_from'], $transaction['id_to'], $transaction['amount']);

                    $alert_service->success('InterSysteem transactie uitgevoerd.');
                    $link_render->redirect('transactions', $pp->ary(), []);
                }
            }

            $transaction['letscode_to'] = $request->request->get('letscode_to', '');
            $transaction['letscode_from'] = $pp->is_admin() || $su->is_master()
                ? $request->request->get('letscode_from', '')
                : $account_render->str($su->id(), $pp->schema());
        }
        else
        {
            //GET form

            $transid = $transaction_service->generate_transid(
                $su->id(), $pp->system());

            $predis->set($redis_transid_key, $transid);
            $predis->expire($redis_transid_key, 3600);

            $transaction = [
                'date'			=> gmdate('Y-m-d H:i:s'),
                'letscode_from'	=> $su->is_master() ? '' : $account_render->str($su->id(), $pp->schema()),
                'letscode_to'	=> '',
                'amount'		=> '',
                'description'	=> '',
                'transid'		=> $transid,
            ];

            $group_id = 'self';

            if ($tus)
            {
                if ($systems_service->get_legacy_eland_origin($tus))
                {
                    $origin_from_tus = $systems_service->get_legacy_eland_origin($tus);

                    $group_id = $db->fetchColumn('select id
                        from ' . $pp->schema() . '.letsgroups
                        where url = ?', [$origin_from_tus]);

                    if ($mid)
                    {
                        $row = $db->fetchAssoc('select
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
                            $amount = ($config_service->get('currencyratio', $pp->schema()) * $amount) / $config_service->get('currencyratio', $tus);
                            $amount = (int) round($amount);
                            $transaction['amount'] = $amount;
                        }
                    }
                    else if ($tuid)
                    {
                        $to_user = $user_cache_service->get($tuid, $tus);

                        if (in_array($to_user['status'], [1, 2]))
                        {
                            $transaction['letscode_to'] = $account_render->str($tuid, $tus);
                        }
                    }
                }
            }
            else if ($mid)
            {
                $row = $db->fetchAssoc('select
                        m.content, m.amount, m.id_user,
                        u.letscode, u.name, u.status
                    from ' . $pp->schema() . '.messages m,
                        '. $pp->schema() . '.users u
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

                    if ($su->id() === $row['id_user'])
                    {
                        if ($pp->is_admin())
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
                $to_user = $user_cache_service->get($tuid, $pp->schema());

                if (in_array($to_user['status'], [1, 2]) || $pp->is_admin())
                {
                    $transaction['letscode_to'] = $account_render->str($tuid, $pp->schema());
                }

                if ($tuid === $su->id())
                {
                    if ($pp->is_admin())
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

        $assets_service->add([
            'transaction_add.js',
        ]);

        $systems = [];

        $systems[] = [
            'groupname' => $config_service->get('systemname', $pp->schema()),
            'id'		=> 'self',
        ];

        if ($intersystems_service->get_eland_count($pp->schema()))
        {
            $eland_urls = [];

            foreach ($intersystems_service->get_eland($pp->schema()) as $remote_eland_schema => $host)
            {
                $eland_url = $systems_service->get_legacy_eland_origin($remote_eland_schema);
                $eland_urls[] = $eland_url;
                $map_eland_schema_url[$eland_url] = $remote_eland_schema;
            }

            $eland_systems = $db->executeQuery('select id, url
                from ' . $pp->schema() . '.letsgroups
                where apimethod = \'elassoap\'
                    and url in (?)',
                    [$eland_urls],
                    [Db::PARAM_STR_ARRAY]);

            foreach ($eland_systems as $sys)
            {
                $sys['eland'] = true;
                $sys['remote_schema'] = $map_eland_schema_url[$sys['url']];
                $sys['groupname'] = $config_service->get('systemname', $sys['remote_schema']);
                $systems[] = $sys;
            }
        }

        if ($intersystems_service->get_elas_count($pp->schema()))
        {
            $ids = [];

            foreach ($intersystems_service->get_elas($pp->schema()) as $key => $name)
            {
                $ids[] = $key;
            }

            $elas_systems = $db->executeQuery('select id, groupname
                from ' . $pp->schema() . '.letsgroups
                where apimethod = \'elassoap\'
                    and id in (?)',
                    [$ids],
                    [Db::PARAM_INT_ARRAY]);

            foreach ($elas_systems as $sys)
            {
                $sys['elas'] = true;
                $systems[] = $sys;
            }
        }

        if ($config_service->get_intersystem_en($pp->schema()))
        {
            $mail_systems = $db->executeQuery('select l.id, l.groupname
                from ' . $pp->schema() . '.letsgroups l, ' .
                    $pp->schema() . '.users u
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
            && $config_service->get('currencyratio', $pp->schema()) > 0;

        $heading_render->add('Nieuwe transactie');
        $heading_render->fa('exchange');

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form  method="post" autocomplete="off">';

        $out .= '<div class="form-group"';
        $out .= $pp->is_admin() ? '' : ' disabled" ';
        $out .= '>';
        $out .= '<label for="letscode_from" class="control-label">';
        $out .= 'Van Account Code';
        $out .= '</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-user"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="letscode_from" name="letscode_from" ';
        $out .= 'data-typeahead-source="';
        $out .= $systems_en ? 'group_self' : 'letscode_to';
        $out .= '" ';
        $out .= 'value="';
        $out .= $transaction['letscode_from'];
        $out .= '" required';
        $out .= $pp->is_admin() ? '' : ' disabled';
        $out .= '>';
        $out .= '</div>';
        $out .= '</div>';

        if ($systems_en)
        {
            $out .= '<div class="form-group">';
            $out .= '<label for="group_id" class="control-label">';
            $out .= 'Aan Systeem</label>';
            $out .= '<div class="input-group">';
            $out .= '<span class="input-group-addon">';
            $out .= '<i class="fa fa-share-alt"></i>';
            $out .= '</span>';

            $out .= '<select type="text" class="form-control" ';
            $out .= 'id="group_id" name="group_id">';

            foreach ($systems as $sys)
            {
                $out .= '<option value="';
                $out .= $sys['id'];
                $out .= '" ';

                $typeahead_service->ini($pp->ary());

                if ($sys['id'] == 'self')
                {
                    $out .= 'id="group_self" ';

                    $typeahead_service->add('accounts', ['status' => 'active']);

                    if ($pp->is_admin())
                    {
                        $typeahead_service->add('accounts', ['status' => 'inactive'])
                            ->add('accounts', ['status' => 'ip'])
                            ->add('accounts', ['status' => 'im']);
                    }

                    $config_schema = $pp->schema();
                }
                else if (isset($sys['eland']))
                {
                    $remote_schema = $sys['remote_schema'];

                    $typeahead_service->add('eland_intersystem_accounts', [
                        'remote_schema'	=> $remote_schema,
                    ]);

                    $config_schema = $remote_schema;
                }
                else if (isset($sys['elas']))
                {
                    $typeahead_service->add('elas_intersystem_accounts', [
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
                    $out .= ' data-minlimit="';
                    $out .= $config_service->get('minlimit', $config_schema) . '"';
                    $out .= ' data-maxlimit="';
                    $out .= $config_service->get('maxlimit', $config_schema) . '"';
                    $out .= ' data-currency="';
                    $out .= $config_service->get('currency', $config_schema) . '"';
                    $out .= ' data-currencyratio="';
                    $out .= $config_service->get('currencyratio', $config_schema) . '"';
                    $out .= ' data-balance-equilibrium="';
                    $out .= $config_service->get('balance_equilibrium', $config_schema) . '"';

                    $typeahead_process_ary['newuserdays'] = $config_service->get('newuserdays', $config_schema);
                }

                $typeahead = $typeahead_service->str($typeahead_process_ary);

                if ($typeahead)
                {
                    $out .= ' data-typeahead="' . $typeahead . '"';
                }

                $out .= $sys['id'] == $group_id ? ' selected="selected"' : '';
                $out .= '>';
                $out .= htmlspecialchars($sys['groupname'], ENT_QUOTES);

                if ($sys['id'] === 'self')
                {
                    $out .= ': eigen Systeem';
                }
                else if (isset($sys['eland']) || isset($sys['elas']))
                {
                    $out .= ': interSysteem';
                }
                else if (isset($sys['mail']))
                {
                    $out .= ': manueel interSysteem';
                }

                $out .= '</option>';
            }

            $out .= '</select>';
            $out .= '</div>';
            $out .= '</div>';
        }
        else
        {
            $out .= '<input type="hidden" id="group_id" ';
            $out .= 'name="group_id" value="self">';
        }

        $out .= '<div class="form-group">';
        $out .= '<label for="letscode_to" class="control-label">';
        $out .= 'Aan Account Code';
        $out .= '</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-user"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="letscode_to" name="letscode_to" ';

        if ($systems_en)
        {
            $out .= 'data-typeahead-source="group_id" ';
        }
        else
        {
            $out .= 'data-typeahead="';

            $typeahead_service->ini($pp->ary())
                ->add('accounts', ['status' => 'active']);

            if ($pp->is_admin())
            {
                $typeahead_service->add('accounts', ['status' => 'inactive'])
                    ->add('accounts', ['status' => 'ip'])
                    ->add('accounts', ['status' => 'im']);
            }

            $out .= $typeahead_service->str([
                'filter'		=> 'accounts',
                'newuserdays'	=> $config_service->get('newuserdays', $pp->schema()),
            ]);

            $out .= '" ';
        }

        $out .= 'value="';
        $out .= $transaction['letscode_to'];
        $out .= '" required>';
        $out .= '</div>';

        $out .= '<ul class="account-info" id="account_info">';

        $out .= '<li id="info_typeahead">Dit veld geeft autosuggesties door ';
        $out .= 'Naam of Account Code te typen. ';

        if (count($systems) > 1)
        {
            $out .= 'Indien je een interSysteem transactie doet, ';
            $out .= 'kies dan eerst het juiste interSysteem om de ';
            $out .= 'juiste suggesties te krijgen.';
        }

        $out .= '</li>';
        $out .= '<li class="hidden" id="info_no_typeahead">';
        $out .= 'Dit veld geeft GEEN autosuggesties aangezien het geselecteerde ';
        $out .= 'interSysteem manueel is. Er is geen automatische data-uitwisseling ';
        $out .= 'met dit Systeem. Transacties worden manueel verwerkt ';
        $out .= 'door de administratie in het andere Systeem.';
        $out .= '</li>';
        $out .= '</ul>';

        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="amount" class="control-label">';
        $out .= 'Aantal</label>';
        $out .= '<div class="row">';
        $out .= '<div class="col-sm-12" id="amount_container">';

        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= $config_service->get('currency', $pp->schema());
        $out .= '</span>';
        $out .= '<input type="number" class="form-control" ';
        $out .= 'id="amount" name="amount" ';
        $out .= 'value="';
        $out .= $transaction['amount'];
        $out .= '" min="1" required>';
        $out .= '</div>';

        $out .= '<ul>';

        $out .= TransactionsController::get_valuation($config_service, $pp->schema());

        $out .= '<li id="info_remote_amount_unknown" ';
        $out .= 'class="hidden">De omrekening ';
        $out .= 'naar de externe tijdsvaluta ';
        $out .= 'is niet gekend omdat het andere ';
        $out .= 'Systeem zich niet op dezelfde ';
        $out .= 'eLAND-server bevindt.</li>';

        if ($pp->is_admin())
        {
            $out .= '<li id="info_admin_limit">';
            $out .= 'Admins kunnen over en onder limieten gaan';

            if ($config_service->get_intersystem_en($pp->schema()))
            {
                $out .= ' in het eigen Systeem.';
            }

            $out .= '</li>';
        }

        $out .= '</ul>';

        $out .= '</div>'; // amount_container

        $out .= '<div class="col-sm-6 collapse" ';
        $out .= 'id="remote_amount_container">';

        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '</span>';
        $out .= '<input type="number" class="form-control" ';
        $out .= 'id="remote_amount" name="remote_amount" ';
        $out .= 'value="" min="1">';
        $out .= '</div>';

        $out .= '<ul>';

        if ($config_service->get('template_lets', $pp->schema())
            && $config_service->get('currencyratio', $pp->schema()) > 0)
        {
            $out .= '<li id="info_ratio">Valuatie: <span class="num">';
            $out .= '</span> per uur</li>';
        }

        $out .= '</ul>';

        $out .= '</div>'; // remote_amount
        $out .= '</div>'; // form-group

        $out .= '<div class="form-group">';
        $out .= '<label for="description" class="control-label">';
        $out .= 'Omschrijving</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-pencil"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="description" name="description" ';
        $out .= 'value="';
        $out .= $transaction['description'];
        $out .= '" required maxlength="60">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= $link_render->btn_cancel('transactions', $pp->ary(), []);

        $out .= '&nbsp;';
        $out .= '<input type="submit" name="zend" ';
        $out .= 'value="Overschrijven" class="btn btn-success btn-lg">';
        $out .= $form_token_service->get_hidden_input();
        $out .= '<input type="hidden" name="transid" ';
        $out .= 'value="';
        $out .= $transaction['transid'];
        $out .= '">';

        $out .= '</form>';
        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('transactions');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
