<?php declare(strict_types=1);

namespace App\Controller\Transactions;

use App\Cnst\BulkCnst;
use App\Cnst\MessageTypeCnst;
use App\Render\AccountRender;
use App\Render\LinkRender;
use App\Repository\AccountRepository;
use App\Service\AlertService;
use App\Service\AssetsService;
use App\Service\AutoMinLimitService;
use App\Service\ConfigService;
use App\Service\FormTokenService;
use App\Service\IntersystemsService;
use App\Service\MailTransactionService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\SystemsService;
use App\Service\TransactionService;
use App\Service\TypeaheadService;
use App\Service\UserCacheService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class TransactionsAddController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/transactions/add',
        name: 'transactions_add',
        methods: ['GET', 'POST'],
        priority: 10,
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.user%',
        ],
        defaults: [
            'module'        => 'transactions',
        ],
    )]

    public function __invoke(
        Request $request,
        Db $db,
        AccountRepository $account_repository,
        LoggerInterface $logger,
        AccountRender $account_render,
        AlertService $alert_service,
        AssetsService $assets_service,
        ConfigService $config_service,
        FormTokenService $form_token_service,
        IntersystemsService $intersystems_service,
        LinkRender $link_render,
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
        if (!$config_service->get_bool('transactions.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Transactions module not enabled.');
        }

        $errors = [];

        $mid = (int) $request->query->get('mid', 0);
        $tuid = (int) $request->query->get('tuid', 0);
        $tus = $request->query->get('tus', '');

        $currency = $config_service->get_str('transactions.currency.name', $pp->schema());
        $currency_ratio = $config_service->get_int('transactions.currency.per_hour_ratio', $pp->schema());
        $timebased_enabled = $config_service->get_bool('transactions.currency.timebased_en', $pp->schema());
        $system_name = $config_service->get_str('system.name', $pp->schema());
        $system_min_limit = $config_service->get_int('accounts.limits.global.min', $pp->schema());
        $system_max_limit = $config_service->get_int('accounts.limits.global.max', $pp->schema());
        $balance_equilibrium = $config_service->get_int('accounts.equilibrium', $pp->schema()) ?? 0;
        $service_stuff_enabled = $config_service->get_bool('transactions.fields.service_stuff.enabled', $pp->schema());
        $new_user_days = $config_service->get_int('users.new.days', $pp->schema()) ?? 0;

        if ($request->isMethod('POST'))
        {
            if ($error_token = $form_token_service->get_error())
            {
                $errors[] = $error_token;
            }

            $transid = $transaction_service->generate_transid($su->id(), $pp->system());
            $description = trim($request->request->get('description', ''));
            $real_from = $request->request->get('real_from');
            $service_stuff = $request->request->get('service_stuff', '');

            [$code_from] = explode(' ', trim($request->request->get('code_from', '')));
            [$code_to] = explode(' ', trim($request->request->get('code_to', '')));

            $amount = (int) $request->request->get('amount', 0);

            $group_id = trim($request->request->get('group_id', ''));

            if (strlen($description) > 60)
            {
                $errors[] = 'De omschrijving mag maximaal 60 tekens lang zijn.';
            }

            if ($group_id != 'self')
            {
                $group = $db->fetchAssociative('select *
                    from ' . $pp->schema() . '.letsgroups
                    where id = ?',
                [$group_id], [\PDO::PARAM_INT]);

                if (!isset($group) || $group === false)
                {
                    $errors[] =  'InterSysteem niet gevonden.';
                }
                else
                {
                    $group['domain'] = strtolower(parse_url($group['url'] ?? '', PHP_URL_HOST) ?? '');
                }
            }

            if ($pp->is_user() && !$su->is_master())
            {
                $from_user = $db->fetchAssociative('select *
                    from ' . $pp->schema() . '.users
                    where id = ?',
                    [$su->id()], [\PDO::PARAM_INT]);
            }
            else
            {
                $from_user = $db->fetchAssociative('select *
                    from ' . $pp->schema() . '.users
                    where code = ?',
                    [$code_from], [\PDO::PARAM_STR]);
            }

            $code_to_self = $group_id == 'self' ? $code_to : $group['localletscode'];

            $to_user = $db->fetchAssociative('select *
                from ' . $pp->schema() . '.users
                where code = ?',
                [$code_to_self], [\PDO::PARAM_STR]);

            if(!is_array($from_user))
            {
                $errors[] = 'De "Van Account Code" bestaat niet';
            }
            else
            {
                $from_id = $from_user['id'];
            }

            if (!strlen($code_to))
            {
                $errors[] = 'Geen bestemmings Account (Aan Account Code) ingevuld';
            }

            if(!count($errors) && !is_array($to_user))
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

            if (!count($errors))
            {
                $to_id = $to_user['id'];
            }

            if (!count($errors) && $group_id == 'self')
            {
                if ($to_user['status'] == 7)
                {
                    $errors[] = 'Je kan niet rechtstreeks naar een interSysteem rekening overschrijven.';
                }
            }

            if (!count($errors) && $from_user['status'] == 7)
            {
                if ($group_id != 'self')
                {
                    $errors[] = 'Transacties tussen accounts beide van het type "interSysteem" zijn niet mogelijk.';
                }
            }
            else
            {
                unset($real_from);
            }

            if (!$description)
            {
                $errors[]= 'De omschrijving is niet ingevuld';
            }

            if ($service_stuff_enabled)
            {
                if (!$service_stuff)
                {
                    $errors[] = 'Selecteer diensten of spullen';
                }
                else if (!in_array($service_stuff, ['service', 'stuff']))
                {
                    throw new BadRequestHttpException('Wrong value for service_stuff: ' . $service_stuff);
                }
            }

            if (!$amount)
            {
                $errors[] = 'Bedrag is niet ingevuld';
            }
            else if (!count($errors) && !(ctype_digit((string) $amount)))
            {
                $errors[] = 'Het bedrag is geen geldig getal';
            }

            if (!$pp->is_admin() && !count($errors))
            {
                $from_user_min_limit = $account_repository->get_min_limit($from_id, $pp->schema());
                $from_user_balance = $account_repository->get_balance($from_id, $pp->schema());

                if (!isset($from_user_min_limit))
                {
                    if(isset($system_min_limit) && ($from_user_balance - $amount) < $system_min_limit)
                    {
                        $err = 'Je beschikbaar saldo laat deze transactie niet toe. ';
                        $err .= 'Je saldo bedraagt ' . $from_user_balance . ' ' . $currency . ' ';
                        $err .= 'en de minimum Systeemslimiet bedraagt ';
                        $err .= $system_min_limit . ' ' . $currency;
                        $errors[] = $err;
                    }
                }
                else
                {
                    if(($from_user_balance - $amount) < $from_user_min_limit)
                    {
                        $err = 'Je beschikbaar saldo laat deze transactie niet toe. ';
                        $err .= 'Je saldo bedraagt ' . $from_user_balance . ' ';
                        $err .= $currency . ' en je minimum limiet bedraagt ';
                        $err .= $from_user_min_limit . ' ' . $currency . '.';
                        $errors[] = $err;
                    }
                }
            }

            if(!count($errors) && ($from_user['code'] == $to_user['code']))
            {
                $errors[] = 'Van en Aan Account Code kunnen niet hetzelfde zijn.';
            }

            if (!$pp->is_admin() && !count($errors))
            {
                $to_user_max_limit = $account_repository->get_max_limit($to_id, $pp->schema());
                $to_user_balance = $account_repository->get_balance($to_id, $pp->schema());

                if (!isset($to_user_max_limit))
                {
                    if(isset($system_max_limit) && ($to_user_balance + $amount) > $system_max_limit)
                    {
                        $err = 'Het ';
                        $err .= $group_id == 'self' ? 'bestemmings Account (Aan Account Code)' : 'interSysteem Account (in dit Systeem)';
                        $err .= ' heeft haar maximum limiet bereikt. ';
                        $err .= 'Het saldo bedraagt ' . $to_user_balance . ' ' . $currency;
                        $err .= ' en de maximum ';
                        $err .= 'Systeemslimiet bedraagt ' . $system_max_limit . ' ' . $currency . '.';
                        $errors[] = $err;
                    }
                }
                else
                {
                    if(($to_user_balance + $amount) > $to_user_max_limit)
                    {
                        $err = 'Het ';
                        $err .= $group_id == 'self' ? 'bestemmings Account (Aan Account Code)' : 'interSysteem Account (in dit Systeem)';
                        $err .= ' heeft haar maximum limiet bereikt. ';
                        $err .= 'Het saldo bedraagt ' . $to_user_balance . ' ' . $currency;
                        $err .= ' en de Maximum Account ';
                        $err .= 'Limiet bedraagt ' . $to_user_max_limit . ' ' . $currency . '.';
                        $errors[] = $err;
                    }
                }
            }

            if(!count($errors) && $group_id == 'self'
                && !$pp->is_admin()
                && !($to_user['status'] == '1' || $to_user['status'] == '2')
            )
            {
                $errors[] = 'Het bestemmings Account (Aan Account Code) is niet actief';
            }

            if ($pp->is_user() && !count($errors))
            {
                if (($from_user['status'] == 2) && (($from_user_balance - $amount) < $balance_equilibrium))
                {
                    $err = 'Als Uitstapper kan je geen ';
                    $err .= $amount;
                    $err .= ' ';
                    $err .= $currency;
                    $err .= ' uitgeven.';
                    $errors[] = $err;
                }

                if (($to_user['status'] == 2) && (($to_user_balance + $amount) > $balance_equilibrium))
                {
                    $err = 'Het ';
                    $err .= $group_id === 'self' ? 'bestemmings Account (Aan Account Code)' : 'interSysteem Account (op dit Systeem)';
                    $err .= ' heeft de status \'Uitstapper\' en kan geen ';
                    $err .= $amount . ' ';
                    $err .= $currency;
                    $err .= ' ontvangen.';
                    $errors[] = $err;
                }
            }

            $contact_admin = $pp->is_admin() ? '' : ' Contacteer een admin.';

            if (isset($group['url']))
            {
                $group_domain = strtolower(parse_url($group['url'] ?? '', PHP_URL_HOST) ?? '');
            }
            else
            {
                $group_domain = false;
            }

            if(!count($errors))
            {
                $transaction = [
                    'id_from'       => $from_id,
                    'id_to'         => $to_id,
                    'amount'        => $amount,
                    'description'   => $description,
                    'transid'       => $transid,
                ];

                if (!$su->is_master())
                {
                    $transaction['created_by'] = $su->id();
                }

                if ($service_stuff_enabled)
                {
                    $transaction['service_stuff'] = $service_stuff;
                }

                if (isset($real_from) && $real_from !== '')
                {
                    $transaction['real_from'] = $real_from;
                }
            }

            if (!count($errors) && $group_id === 'self')
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

            if (!count($errors) && $group['apimethod'] === 'mail')
            {
                $transaction['real_to'] = trim($request->request->get('code_to', ''));

                if ($id = $transaction_service->insert($transaction, $pp->schema()))
                {
                    $transaction['id'] = $id;
                    $transaction['code_to'] = $code_to;

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

            if (!count($errors) && $group['apimethod'] !== 'elassoap')
            {
                $alert_service->error('InterSysteem ' .
                    $group['groupname'] .
                    ' heeft geen geldige Api Methode.' . $contact_admin);
                $link_render->redirect('transactions', $pp->ary(), []);
            }

            if (!count($errors) && !$group_domain)
            {
                $alert_service->error('Geen URL ingesteld voor interSysteem ' .
                    $group['groupname'] . '. ' . $contact_admin);
                $link_render->redirect('transactions', $pp->ary(), []);
            }

            if (!count($errors) && !$systems_service->get_schema_from_legacy_eland_origin($group['url']))
            {
                // Previously eLAS intersystem

                $alert_service->error('Geen verbinding met interSysteem ' . $group['groupname']).
                $link_render->redirect('transactions', $pp->ary(), []);
            }

            if (!count($errors))
            {
                // the interSystem group is on the same server (eLAND)

                $remote_schema = $systems_service->get_schema_from_legacy_eland_origin($group['url']);

                if (!$config_service->get_bool('transactions.enabled', $remote_schema))
                {
                    $errors[] = 'De transactie module is niet actief in het andere systeem.';
                }
            }

            if (!count($errors))
            {
                $to_remote_user = $db->fetchAssociative('select *
                    from ' . $remote_schema . '.users
                    where code = ?',
                    [$code_to], [\PDO::PARAM_STR]);

                if (!$to_remote_user)
                {
                    $errors[] = 'Het bestemmings Account ("Aan Account Code") in het andere Systeem bestaat niet.';
                }
                else if (!in_array($to_remote_user['status'], ['1', '2']))
                {
                    $errors[] = 'Het bestemmings Account ("Aan Account Code") in het andere Systeem is niet actief.';
                }
                else
                {
                    $to_remote_id = $to_remote_user['id'];
                }

                $legacy_eland_origin = $systems_service->get_legacy_eland_origin($pp->schema());

                $remote_group = $db->fetchAssociative('select *
                    from ' . $remote_schema . '.letsgroups
                    where url = ?',
                    [$legacy_eland_origin], [\PDO::PARAM_STR]);

                if (!count($errors) && !$remote_group)
                {
                    $err = 'Het andere Systeem heeft dit Systeem (';
                    $err .= $system_name;
                    $err .= ') niet geconfigureerd als interSysteem.';
                    $errors[] = $err;
                }

                if (!count($errors) && !$remote_group['localletscode'])
                {
                    $errors[] = 'Er is geen interSysteem Account gedefiniëerd in het andere Systeem.';
                }

                $from_remote_user = $db->fetchAssociative('select *
                    from ' . $remote_schema . '.users
                    where code = ?',
                    [$remote_group['localletscode']], [\PDO::PARAM_STR]);

                if (!count($errors) && !$from_remote_user)
                {
                    $errors[] = 'Er is geen interSysteem Account in het andere Systeem.';
                }

                if (!count($errors) && $from_remote_user['role'] !== 'guest')
                {
                    $errors[] = 'Het Account in het andere Systeem is niet ingesteld met rol "Gast".';
                }

                if (!count($errors) && !in_array($from_remote_user['status'], [1, 2, 7]))
                {
                    $errors[] = 'Het interSysteem Account in het andere Systeem heeft geen actieve status.';
                }

                if (!count($errors))
                {
                    $from_remote_id = $from_remote_user['id'];
                }

                $remote_currency = $config_service->get_str('transactions.currency.name', $remote_schema);
                $remote_currency_ratio = $config_service->get_int('transactions.currency.per_hour_ratio', $remote_schema);
                $remote_balance_equilibrium = $config_service->get_int('accounts.equilibrium', $remote_schema) ?? 0;
                $remote_system_min_limit = $config_service->get_int('accounts.limits.global.min', $remote_schema);
                $remote_system_max_limit = $config_service->get_int('accounts.limits.global.max', $remote_schema);

                if (!count($errors) && $currency_ratio < 1)
                {
                    $errors[] = 'De Currency Ratio is niet correct ingesteld. ' . $contact_admin;
                }

                if (!count($errors) && $remote_currency_ratio < 1)
                {
                    $errors[] = 'De Currency Ratio van het andere Systeem is niet correct ingesteld. ' . $contact_admin;
                }

                $remote_amount = (int) round(($amount * $remote_currency_ratio) / $currency_ratio);

                if (!count($errors) && ($remote_amount < 1))
                {
                    $errors[] = 'Het bedrag is te klein want het kan niet uitgedrukt worden in de gebruikte munt van het andere Systeem.';
                }

                if (!count($errors))
                {
                    $from_remote_min_limit = $account_repository->get_min_limit($from_remote_id, $remote_schema);
                    $from_remote_balance = $account_repository->get_balance($from_remote_id, $remote_schema);

                    if (!isset($from_remote_min_limit))
                    {
                        if(isset($remote_system_min_limit) && ($from_remote_balance - $remote_amount) < $remote_system_min_limit)
                        {
                            $err = 'Het interSysteem Account van dit Systeem ';
                            $err .= 'in het andere Systeem heeft onvoldoende saldo ';
                            $err .= 'beschikbaar. Het saldo bedraagt ';
                            $err .= $from_remote_balance . ' ';
                            $err .= $remote_currency . ' ';
                            $err .= 'en de Minimum Systeemslimiet ';
                            $err .= 'in het andere Systeem bedraagt ';
                            $err .= $remote_system_min_limit . ' ';
                            $err .= $remote_currency . '.';
                            $errors[] = $err;
                        }
                    }
                    else
                    {
                        if(($from_remote_balance - $remote_amount) < $from_remote_min_limit)
                        {
                            $err = 'Het interSysteem Account van dit Systeem in het andere Systeem heeft onvoldoende balance ';
                            $err .= 'beschikbaar. Het saldo bedraagt ' . $from_remote_balance . ' ';
                            $err .= $remote_currency . ' ';
                            $err .= 'en de Minimum Limiet van het Account in het andere Systeem ';
                            $err .= 'bedraagt ' . $from_remote_min_limit . ' ';
                            $err .= $remote_currency . '.';
                            $errors[] = $err;
                        }
                    }
                }

                if (!count($errors)
                    && ($from_remote_user['status'] == 2)
                    && (($from_remote_balance - $remote_amount) < $remote_balance_equilibrium)
                )
                {
                    $err = 'Het interSysteem Account van dit Systeem in het andere Systeem ';
                    $err .= 'heeft de status uitstapper ';
                    $err .= 'en kan geen ';
                    $err .= $remote_amount . ' ';
                    $err .= $remote_currency . ' uitgeven ';
                    $err .= '(' . $amount . ' ';
                    $err .= $currency;
                    $err .= ').';
                    $errors[] = $err;
                }

                if (!count($errors))
                {
                    $to_remote_max_limit = $account_repository->get_max_limit($to_remote_id, $remote_schema);
                    $to_remote_balance = $account_repository->get_balance($to_remote_id, $remote_schema);

                    if (!isset($to_remote_max_limit))
                    {
                        if(isset($remote_system_max_limit) && ($to_remote_balance + $remote_amount) > $remote_system_max_limit)
                        {
                            $err = 'Het bestemmings-Account in het andere Systeem ';
                            $err .= 'heeft de maximum Systeemslimiet bereikt. ';
                            $err .= 'Het saldo bedraagt ';
                            $err .= $to_remote_balance;
                            $err .= ' ';
                            $err .= $remote_currency;
                            $err .= ' en de maximum ';
                            $err .= 'Systeemslimiet bedraagt ';
                            $err .= $remote_system_max_limit . ' ' . $remote_currency . '.';
                            $errors[] = $err;
                        }
                    }
                    else
                    {
                        if(($to_remote_balance + $remote_amount) > $to_remote_max_limit)
                        {
                            $err = 'Het bestemmings-Account in het andere Systeem ';
                            $err .= 'heeft de maximum limiet bereikt. ';
                            $err .= 'Het saldo bedraagt ' . $to_remote_balance . ' ' . $remote_currency;
                            $err .= ' en de maximum ';
                            $err .= 'limiet voor het Account bedraagt ' . $to_remote_max_limit . ' ' . $remote_currency . '.';
                            $errors[] = $err;
                        }
                    }
                }

                if (!count($errors)
                    && ($to_remote_user['status'] == 2)
                    && (($to_remote_balance + $remote_amount) > $remote_balance_equilibrium)
                )
                {
                    $err = 'Het bestemmings-Account heeft status uitstapper ';
                    $err .= 'en kan geen ' . $remote_amount . ' ';
                    $err .= $remote_currency . ' ontvangen (';
                    $err .= $amount . ' ';
                    $err .= $currency;
                    $err .= ').';
                    $errors[] = $err;
                }

                if (!count($errors))
                {
                    $transaction['real_to'] = $to_remote_user['code'] . ' ' . $to_remote_user['name'];

                    $db->beginTransaction();

                    $db->insert($pp->schema() . '.transactions', $transaction);
                    $id = $db->lastInsertId($pp->schema() . '.transactions_id_seq');
                    $transaction['id'] = $id;
                    $account_repository->update_balance($to_id, $amount, $pp->schema());
                    $account_repository->update_balance($from_id, -$amount, $pp->schema());

                    $remote_transaction = [
                        'id_from'       => $from_remote_id,
                        'id_to'         => $to_remote_id,
                        'amount'        => $remote_amount,
                        'real_from'     => $account_render->str($from_id, $pp->schema()),
                        'description'   => $description,
                        'transid'       => $transid,
                    ];

                    if ($service_stuff_enabled)
                    {
                        $remote_transaction['service_stuff'] = $service_stuff;
                    }

                    $db->insert($remote_schema . '.transactions', $remote_transaction);
                    $remote_id = $db->lastInsertId($remote_schema . '.transactions_id_seq');
                    $remote_transaction['id'] = $remote_id;
                    $account_repository->update_balance($to_remote_id, $remote_amount, $remote_schema);
                    $account_repository->update_balance($from_remote_id, -$remote_amount, $remote_schema);

                    $db->commit();

                    // to eLAND interSystem
                    $mail_transaction_service->queue($transaction, $pp->schema());
                    $mail_transaction_service->queue($remote_transaction, $remote_schema);

                    $logger->info('direct interSystem transaction ' . $transid . ' amount: ' .
                        $amount . ' from user: ' .  $account_render->str_id($from_id, $pp->schema()) .
                        ' to user: ' . $account_render->str_id($to_id, $pp->schema()),
                        ['schema' => $pp->schema()]);

                    $logger->info('direct interSystem transaction (receiving) ' . $transid.
                        ' amount: ' . $remote_amount . ' from user: ' . $from_remote_user['code'] . ' ' .
                        $from_remote_user['name'] . ' to user: ' . $to_remote_user['code'] . ' ' .
                        $to_remote_user['name'], ['schema' => $remote_schema]);

                    $autominlimit_service->init($pp->schema())
                        ->process($from_id, $to_id, $amount);

                    $alert_service->success('InterSysteem transactie uitgevoerd.');
                    $link_render->redirect('transactions', $pp->ary(), []);
                }
            }

            // At least one error

            $alert_service->error($errors);

            $code_to = $request->request->get('code_to', '');
            $code_from = $pp->is_admin() || $su->is_master()
                ? $request->request->get('code_from', '')
                : $account_render->str($su->id(), $pp->schema());
        }

        if ($request->isMethod('GET'))
        {
            $code_from = $su->is_master() ? '' : $account_render->str($su->id(), $pp->schema());
            $group_id = 'self';
            $service_stuff = null;

            if ($tus)
            {
                if ($systems_service->get_legacy_eland_origin($tus))
                {
                    $origin_from_tus = $systems_service->get_legacy_eland_origin($tus);

                    $group_id = $db->fetchOne('select id
                        from ' . $pp->schema() . '.letsgroups
                        where url = ?',
                        [$origin_from_tus], [\PDO::PARAM_STR]);

                    if ($mid)
                    {
                        $row = $db->fetchAssociative('select
                                m.subject, m.amount, m.user_id,
                                m.service_stuff,
                                u.code, u.name
                            from ' . $tus . '.messages m,
                                '. $tus . '.users u
                            where u.id = m.user_id
                                and u.status in (1, 2)
                                and m.id = ?',
                            [$mid], [\PDO::PARAM_INT]);

                        if ($row)
                        {
                            $tus_currency_ratio = $config_service->get_int('transactions.currency.per_hour_ratio', $tus);

                            $code_to = $row['code'] . ' ' . $row['name'];
                            $description =  substr($row['subject'], 0, 60);

                            if (isset($tus_currency_ratio) && $tus_currency_ratio >  0)
                            {
                                $amount = $row['amount'];
                                $amount = ($currency_ratio * $amount) / $tus_currency_ratio;
                                $amount = (int) round($amount);
                            }

                            $tus_messages_service_stuff_enabled = $config_service->get_bool('messages.fields.service_stuff.enabled', $tus);

                            if ($tus_messages_service_stuff_enabled)
                            {
                                $service_stuff = $row['service_stuff'];
                            }
                        }
                    }
                    else if ($tuid)
                    {
                        $tuid_to_user = $user_cache_service->get($tuid, $tus);

                        if (in_array($tuid_to_user['status'], [1, 2]))
                        {
                            $code_to = $account_render->str($tuid, $tus);
                        }
                    }
                }
            }
            else if ($mid)
            {
                $row = $db->fetchAssociative('select
                        m.subject, m.amount, m.user_id,
                        m.service_stuff,
                        u.code, u.name, u.status
                    from ' . $pp->schema() . '.messages m,
                        '. $pp->schema() . '.users u
                    where u.id = m.user_id
                        and m.id = ?',
                    [$mid], [\PDO::PARAM_INT]);

                if ($row)
                {
                    $messages_service_stuff_enabled = $config_service->get_bool('messages.fields.service_stuff.enabled', $pp->schema());

                    if ($row['status'] === 1 || $row['status'] === 2)
                    {
                        $code_to = $row['code'] . ' ' . $row['name'];
                        $description =  substr($row['subject'], 0, 60);
                        $amount = $row['amount'];

                        if ($messages_service_stuff_enabled)
                        {
                            $service_stuff = $row['service_stuff'];
                        }
                    }

                    if ($su->id() === $row['user_id'])
                    {
                        if ($pp->is_admin())
                        {
                            $code_from = '';
                        }
                        else
                        {
                            $code_to = '';
                            $description = '';
                            $amount = '';
                        }
                    }
                }
            }
            else if ($tuid)
            {
                $tuid_to_user = $user_cache_service->get($tuid, $pp->schema());

                if (in_array($tuid_to_user['status'], [1, 2]) || $pp->is_admin())
                {
                    $code_to = $account_render->str($tuid, $pp->schema());
                }

                if ($tuid === $su->id())
                {
                    if ($pp->is_admin())
                    {
                        $code_from = '';
                    }
                    else
                    {
                        $code_to = '';
                    }
                }
            }
        }

        $assets_service->add([
            'transaction_add.js',
        ]);

        $systems = [];

        $systems[] = [
            'groupname' => $system_name,
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

                if (!$config_service->get_bool('transactions.enabled', $sys['remote_schema']))
                {
                    continue;
                }

                $sys['groupname'] = $config_service->get_str('system.name', $sys['remote_schema']);
                $systems[] = $sys;
            }
        }

        if ($config_service->get_intersystem_en($pp->schema()))
        {
            $mail_systems = $db->executeQuery('select l.id, l.groupname
                from ' . $pp->schema() . '.letsgroups l, ' .
                    $pp->schema() . '.users u
                where l.apimethod = \'mail\'
                    and u.code = l.localletscode
                    and u.status in (1, 2, 7)');

            foreach ($mail_systems as $sys)
            {
                $sys['mail'] = true;
                $systems[] = $sys;
            }
        }

        $systems_en = count($systems) > 1
            && isset($currency_ratio)
            && $currency_ratio > 0;

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form  method="post" autocomplete="off">';

        $out .= '<div class="form-group"';
        $out .= $pp->is_admin() ? '' : ' disabled" ';
        $out .= '>';
        $out .= '<label for="code_from" class="control-label">';
        $out .= 'Van Account Code';
        $out .= '</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-user"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="code_from" name="code_from" ';

        if ($pp->is_admin())
        {
            $out .= 'data-typeahead="';

            $out .= $typeahead_service->ini($pp->ary())
                ->add('accounts', ['status' => 'active'])
                ->add('accounts', ['status' => 'inactive'])
                ->add('accounts', ['status' => 'ip'])
                ->add('accounts', ['status' => 'im'])
                ->add('intersystem_mail_accounts', [])
                ->str([
                    'filter'        => 'accounts',
                    'newuserdays'   => $new_user_days,
                ]);

             $out .= '" ';
        }

        $out .= 'value="';
        $out .= $code_from ?? '';
        $out .= '" required';
        $out .= $pp->is_admin() ? '' : ' disabled';
        $out .= '>';
        $out .= '</div>';
        $out .= '</div>';

        if ($pp->is_admin())
        {
            $out .= '<div class="form-group" hidden data-real-from>';
            $out .= '<label for="real_from" class="control-label">';
            $out .= 'Van Gebruiker/Code in Bovenstaand Remote Systeem';
            $out .= '</label>';
            $out .= '<div class="input-group">';
            $out .= '<span class="input-group-addon">';
            $out .= '<i class="fa fa-user"></i>';
            $out .= '</span>';
            $out .= '<input type="text" class="form-control" ';
            $out .= 'id="real_from" name="real_from" ';
            $out .= 'value="';
            $out .= $real_from ?? '';
            $out .= '">';
            $out .= '</div>';
            $out .= '<p>Dit veld geeft geen autosuggesties</p>';
            $out .= '</div>';
        }

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
                else if (isset($sys['mail']))
                {
                    unset($config_schema);
                }

                $typeahead_process_ary = ['filter' => 'accounts'];

                if (isset($config_schema))
                {
                    $out .= ' data-minlimit="';
                    $out .= $config_service->get_int('accounts.limits.global.min', $config_schema);
                    $out .= '"';
                    $out .= ' data-maxlimit="';
                    $out .= $config_service->get_int('accounts.limits.global.max', $config_schema);
                    $out .= '"';
                    $out .= ' data-currency="';
                    $out .= $config_service->get_str('transactions.currency.name', $config_schema);
                    $out .= '"';
                    $out .= ' data-currencyratio="';
                    $out .= $config_service->get_int('transactions.currency.per_hour_ratio', $config_schema);
                    $out .= '"';
                    $out .= ' data-balance-equilibrium="';
                    $out .= $config_service->get_int('accounts.equilibrium', $config_schema);
                    $out .= '"';

                    $typeahead_process_ary['newuserdays'] = $config_service->get_int('users.new.days', $config_schema);
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
                else if (isset($sys['eland']))
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
        $out .= '<label for="code_to" class="control-label">';
        $out .= 'Aan Account Code';
        $out .= '</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-user"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="code_to" name="code_to" ';

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
                'newuserdays'	=> $new_user_days,
            ]);

            $out .= '" ';
        }

        $out .= 'value="';
        $out .= $code_to ?? '';
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
        $out .= $currency;
        $out .= '</span>';
        $out .= '<input type="number" class="form-control" ';
        $out .= 'id="amount" name="amount" ';
        $out .= 'value="';
        $out .= $amount ?? '';
        $out .= '" min="1" required>';
        $out .= '</div>';

        $out .= '<ul>';

        $out .= TransactionsController::get_valuation($config_service, $pp->schema());

        $out .= '<li id="info_remote_amount_unknown" ';
        $out .= 'class="hidden">De omrekening ';
        $out .= 'naar de externe tijdvaluta ';
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

        if ($timebased_enabled && $currency_ratio > 0)
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
        $out .= $description ?? '';
        $out .= '" required maxlength="60">';
        $out .= '</div>';
        $out .= '</div>';

        if ($service_stuff_enabled)
        {
            $out .= '<div class="form-group">';
            $out .= '<div class="custom-radio">';

            foreach (MessageTypeCnst::SERVICE_STUFF_TPL_ARY as $key => $render_data)
            {
                if ($key === 'null-service-stuff')
                {
                    continue;
                }

                $out .= strtr(BulkCnst::TPL_RADIO_INLINE,[
                    '%name%'    => 'service_stuff',
                    '%value%'   => $key,
                    '%attr%'    => ' required' . ($service_stuff === $key ? ' checked' : ''),
                    '%label%'   => '<span class="btn btn-' . $render_data['btn_class'] . '">' . $render_data['label'] . '</span>',
                ]);
            }

            $out .= '</div>';
            $out .= '</div>';
        }

        $out .= $link_render->btn_cancel('transactions', $pp->ary(), []);

        $out .= '&nbsp;';
        $out .= '<input type="submit" name="zend" ';
        $out .= 'value="Overschrijven" class="btn btn-success btn-lg">';
        $out .= $form_token_service->get_hidden_input();

        $out .= '</form>';
        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('transactions');

        return $this->render('transactions/transactions_add.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
