<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class mass_transaction
{
    const STATUS_RENDER = [
        'active'	=> [
            'lbl'	=> 'Actief',
            'st'	=> 1,
            'hsh'	=> '58d267',
        ],
        'without-new-and-leaving' => [
            'lbl'	=> 'Actief zonder uit- en instappers',
            'st'	=> '123',
            'hsh'	=> '096024',
        ],
        'new'		=> [
            'lbl'	=> 'Instappers',
            'st'	=> 3,
            'hsh'	=> 'e25b92',
            'cl'	=> 'success',
        ],
        'leaving'	=> [
            'lbl'	=> 'Uitstappers',
            'st'	=> 2,
            'hsh'	=> 'ea4d04',
            'cl'	=> 'danger',
        ],
        'inactive'	=> [
            'lbl'	=> 'Inactief',
            'st'	=> 0,
            'hsh'	=> '79a240',
            'cl'	=> 'inactive',
        ],
        'info-packet'	=> [
            'lbl'	=> 'Info-pakket',
            'st'	=> 5,
            'hsh'	=> '2ed157',
            'cl'	=> 'warning',
        ],
        'info-moment'	=> [
            'lbl'	=> 'Info-moment',
            'st'	=> 6,
            'hsh'	=> '065878',
            'cl'	=> 'info',
        ],
        'all'		=> [
            'lbl'	=> 'Alle',
        ],
    ];

    const STATUS = [
        0 	=> 'inactive',
        1 	=> 'active',
        2 	=> 'leaving',
        3	=> 'new',
        5	=> 'info-packet',
        6	=> 'info-moment',
        7	=> 'extern',
        123 => 'without-new-and-leaving',
    ];

    public function mass_transaction(Request $request, app $app):Response
    {
        $q = $request->get('q', '');
        $hsh = $request->get('hsh', '096024');

        $selected_users = $request->request->get('selected_users', '');
        $selected_users = ltrim($selected_users, '.');
        $selected_users = explode('.', $selected_users);
        $selected_users = array_combine($selected_users, $selected_users);

        $users = [];

        $rs = $app['db']->prepare(
            'select id, name, letscode,
                accountrole, status, saldo,
                minlimit, maxlimit, adate,
                postcode
            from ' . $app['tschema'] . '.users
            where status IN (0, 1, 2, 5, 6)
            order by letscode');

        $rs->execute();

        while ($row = $rs->fetch())
        {

        // hack eLAS compatibility (in eLAND limits can be null)

            $row['minlimit'] = $row['minlimit'] === -999999999 ? '' : $row['minlimit'];
            $row['maxlimit'] = $row['maxlimit'] === 999999999 ? '' : $row['maxlimit'];

            $users[$row['id']] = $row;
        }

        $to_letscode = trim($request->request->get('to_letscode', ''));

        if ($to_letscode !== '')
        {
            [$to_letscode] = explode(' ', $to_letscode);
        }

        $from_letscode = trim($request->request->get('from_letscode', ''));

        if ($from_letscode !== '')
        {
            [$from_letscode] = explode(' ', $from_letscode);
        }

        $amount = $request->request->get('amount', []);
        $description = trim($request->request->get('description', ''));
        $transid = $request->request->get('transid', '');
        $mail_en = $request->request->get('mail_en', false);

        if ($request->isMethod('POST'))
        {
            if (!$request->request->get('verify', false))
            {
                $errors[] = 'Het controle nazichts-vakje is niet aangevinkt.';
            }

            if (!$description)
            {
                $errors[] = 'Vul een omschrijving in.';
            }

            if ($to_letscode && $from_letscode)
            {
                $errors[] = '\'Van Account Code\' en \'Aan Account Code\' kunnen niet beide ingevuld worden.';
            }
            else if (!($to_letscode || $from_letscode))
            {
                $errors[] = '\'Van Account Code\' OF \'Aan Account Code\' moet ingevuld worden.';
            }
            else
            {
                $to_one = $to_letscode ? true : false;
                $letscode = $to_one ? $to_letscode : $from_letscode;

                $one_uid = $app['db']->fetchColumn('select id
                    from ' . $app['tschema'] . '.users
                    where letscode = ?', [$letscode]);

                if (!$one_uid)
                {
                    $err = 'Geen bestaande Account Code in veld \'';
                    $err .= $to_one ? 'Aan': 'Van';
                    $err .= ' Account Code\'.';
                    $errors[] = $err;
                }
                else
                {
                    unset($amount[$one_uid]);
                }
            }

            $filter_options = [
                'options'	=> [
                    'min_range' => 0,
                ],
            ];

            $count = 0;

            foreach ($amount as $uid => $amo)
            {
                if (!isset($selected_users[$uid]))
                {
                    continue;
                }

                if (!$amo)
                {
                    continue;
                }

                $count++;

                if (!filter_var($amo, FILTER_VALIDATE_INT, $filter_options))
                {
                    $errors[] = 'Ongeldig bedrag ingevuld.';
                    break;
                }
            }

            if (!$count)
            {
                $errors[] = 'Er is geen enkel bedrag ingevuld.';
            }

            if (!$transid)
            {
                $errors[] = 'Geen geldig transactie id';
            }

            if ($app['db']->fetchColumn('select id
                from ' . $app['tschema'] . '.transactions
                where transid = ?', [$transid]))
            {
                $errors[] = 'Een dubbele boeking van een transactie werd voorkomen.';
            }

            if ($error_token = $app['form_token']->get_error())
            {
                $errors[] = $error_token;
            }

            if (count($errors))
            {
                $app['alert']->error($errors);
            }
            else
            {
                $transactions = [];

                $app['db']->beginTransaction();

                $cdate = gmdate('Y-m-d H:i:s');

                $alert_success = $log_many = '';
                $total_amount = 0;

                try
                {

                    foreach ($amount as $many_uid => $amo)
                    {
                        if (!isset($selected_users[$many_uid]))
                        {
                            continue;
                        }

                        if (!$amo || $many_uid == $one_uid)
                        {
                            continue;
                        }

                        $many_user = $users[$many_uid];
                        $to_id = $to_one ? $one_uid : $many_uid;
                        $from_id = $to_one ? $many_uid : $one_uid;
                        $from_user = $users[$from_id];
                        $to_user = $users[$to_id];

                        $alert_success .= 'Transactie van gebruiker ' . $from_user['letscode'] . ' ' . $from_user['name'];
                        $alert_success .= ' naar ' . $to_user['letscode'] . ' ' . $to_user['name'];
                        $alert_success .= '  met bedrag ' . $amo .' ';
                        $alert_success .= $app['config']->get('currency', $app['tschema']);
                        $alert_success .= ' uitgevoerd.<br>';

                        $log_many .= $many_user['letscode'] . ' ' . $many_user['name'] . '(' . $amo . '), ';

                        $transaction = [
                            'id_to' 		=> $to_id,
                            'id_from' 		=> $from_id,
                            'amount' 		=> $amo,
                            'description' 	=> $description,
                            'date' 			=> $cdate,
                            'cdate' 		=> $cdate,
                            'transid'		=> $transid,
                            'creator'		=> $app['s_master'] ? 0 : $app['s_id'],
                        ];

                        $app['db']->insert($app['tschema'] . '.transactions', $transaction);
                        $transaction['id'] = $app['db']->lastInsertId($app['tschema'] . '.transactions_id_seq');

                        $app['db']->executeUpdate('update ' . $app['tschema'] . '.users
                            set saldo = saldo ' . (($to_one) ? '- ' : '+ ') . '?
                            where id = ?', [$amo, $many_uid]);

                        $total_amount += $amo;

                        $transid = $app['transaction']->generate_transid($app['s_id'], $app['pp_system']);

                        $transactions[] = $transaction;
                    }

                    $app['db']->executeUpdate('update ' . $app['tschema'] . '.users
                        set saldo = saldo ' . (($to_one) ? '+ ' : '- ') . '?
                        where id = ?', [$total_amount, $one_uid]);

                    $app['db']->commit();
                }
                catch (Exception $e)
                {
                    $app['alert']->error('Fout bij het opslaan.');
                    $app['db']->rollback();
                    throw $e;
                }

                $app['autominlimit']->init($app['tschema']);

                foreach($transactions as $t)
                {
                    $app['autominlimit']->process($t['id_from'], $t['id_to'], (int) $t['amount']);
                }

                if ($to_one)
                {
                    foreach ($transactions as $t)
                    {
                        $app['predis']->del($app['tschema'] . '_user_' . $t['id_from']);
                    }

                    $app['predis']->del($app['tschema'] . '_user_' . $t['id_to']);
                }
                else
                {
                    foreach ($transactions as $t)
                    {
                        $app['predis']->del($app['tschema'] . '_user_' . $t['id_to']);
                    }

                    $app['predis']->del($app['tschema'] . '_user_' . $t['id_from']);
                }

                $alert_success .= 'Totaal: ' . $total_amount . ' ';
                $alert_success .= $app['config']->get('currency', $app['tschema']);
                $app['alert']->success($alert_success);

                $log_one = $users[$one_uid]['letscode'] . ' ';
                $log_one .= $users[$one_uid]['name'];
                $log_one .= '(Total amount: ' . $total_amount . ' ';
                $log_one .= $app['config']->get('currency', $app['tschema']);
                $log_one .= ')';

                $log_many = rtrim($log_many, ', ');
                $log_str = 'Mass transaction from ';
                $log_str .= $to_one ? $log_many : $log_one;
                $log_str .= ' to ';
                $log_str .= $to_one ? $log_one : $log_many;

                $app['monolog']->info('trans: ' . $log_str, ['schema' => $app['tschema']]);

                if ($app['s_master'])
                {
                    $app['alert']->warning('Master account: geen mails verzonden.');
                }
                else if ($mail_en)
                {
                    foreach ($transactions as $transaction)
                    {
                        $user_id = $to_one ? $transaction['id_from'] : $transaction['id_to'];

                        $vars = [
                            'transaction'	=> $transaction,
                            'from_user_id'	=> $transaction['id_from'],
                            'to_user_id'	=> $transaction['id_to'],
                            'user_id'		=> $user_id,
                        ];

                        $app['queue.mail']->queue([
                            'schema'	=> $app['tschema'],
                            'to'		=> $app['mail_addr_user']->get($user_id, $app['tschema']),
                            'template'	=> 'transaction/transaction',
                            'vars'		=> $vars,
                        ], random_int(0, 5000));
                    }

                    $vars = [
                        'transactions'	=> $transactions,
                        'total_amount'	=> $total_amount,
                        'description'	=> $description,
                    ];

                    if ($to_one)
                    {
                        $vars['to_user_id'] = $one_uid;
                    }
                    else
                    {
                        $vars['from_user_id'] = $one_uid;
                    }

                    $mail_template = 'mass_transaction/';
                    $mail_template .= $to_one ? 'many_to_one' : 'one_to_many';

                    $app['queue.mail']->queue([
                        'schema'	=> $app['tschema'],
                        'to' 		=> array_merge(
                            $app['mail_addr_system']->get_admin($app['tschema']),
                            $app['mail_addr_user']->get($app['s_id'], $app['tschema']),
                            $app['mail_addr_user']->get($one_uid, $app['tschema'])
                        ),
                        'template'	=> $mail_template,
                        'vars'		=> $vars,
                    ], 8000);

                    $app['alert']->success('Notificatie mails verzonden.');
                }

                $app['link']->redirect('mass_transaction', $app['pp_ary'], []);
            }
        }
        else
        {
            $mail_en = true;
        }

        $transid = $app['transaction']->generate_transid($app['s_id'], $app['pp_system']);

        if ($to_letscode)
        {
            if ($to_name = $app['db']->fetchColumn('select name
                from ' . $app['tschema'] . '.users
                where letscode = ?', [$to_letscode]))
            {
                $to_letscode .= ' ' . $to_name;
            }
        }
        if ($from_letscode)
        {
            if ($from_name = $app['db']->fetchColumn('select name
                from ' . $app['tschema'] . '.users
                where letscode = ?', [$from_letscode]))
            {
                $from_letscode .= ' ' . $from_name;
            }
        }

        $system_minlimit = $app['config']->get('minlimit', $app['tschema']);
        $system_maxlimit = $app['config']->get('maxlimit', $app['tschema']);

        $app['assets']->add([
            'mass_transaction.js',
            'combined_filter.js',
        ]);

        $app['heading']->add('Massa transactie');
        $app['heading']->fa('exchange');

        $out = '<div class="panel panel-warning">';
        $out .= '<div class="panel-heading">';
        $out .= '<button class="btn btn-default" ';
        $out .= 'title="Toon invul-hulp" data-toggle="collapse" ';
        $out .= 'data-target="#help" type="button">';
        $out .= '<i class="fa fa-question"></i>';
        $out .= ' Invul-hulp</button>';
        $out .= '</div>';
        $out .= '<div class="panel-heading collapse" id="help">';

        $out .= '<p>Met deze invul-hulp kan je snel alle ';
        $out .= 'bedragen van de massa-transactie invullen. ';
        $out .= 'De bedragen kan je nadien nog individueel ';
        $out .= 'aanpassen alvorens de massa transactie uit te voeren. ';
        $out .= '</p>';

        $out .= '<form class="form" id="fill_in_aid" ';

        $out .= 'data-transactions-sum-in="';
        $out .= htmlspecialchars($app['link']->context_path('transactions_sum_in',
            $app['pp_ary'], ['days' => 365]));
        $out .= '" ';

        $out .= 'data-transactions-sum-out="';
        $out .= htmlspecialchars($app['link']->context_path('transactions_sum_out',
            $app['pp_ary'], ['days' => 365]));
        $out .= '" ';

        $out .= 'data-weighted-balances="';
        $out .= htmlspecialchars($app['link']->context_path('weighted_balances',
            $app['pp_ary'], ['days' => 365]));
        $out .= '"';

        $out .= '>';

        $out .= '<div class="pan-sub bg-warning">';

        $out .= '<div class="form-group">';
        $out .= '<label for="fixed" class="control-label">';
        $out .= 'Vast bedrag</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= $app['config']->get('currency', $app['tschema']);
        $out .= '</span>';
        $out .= '<input type="number" class="form-control margin-bottom" id="fixed" ';
        $out .= 'min="0">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '</div>';

        /**/
        $out .= '<div class="pan-sub bg-warning">';

        $out .= '<h4>Variabel deel</h4>';

        $out .= '<div class="form-group">';
        $out .= '<label for="fixed" class="control-label">';
        $out .= 'Over periode</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= 'dagen</span>';
        $out .= '<input type="number" ';
        $out .= 'class="form-control margin-bottom" id="var_days" ';
        $out .= 'min="0">';
        $out .= '</div>';
        $out .= '</div>';

        //
        $out .= '<div class="form-group">';
        $out .= '<label for="var_balance" class="control-label">';
        $out .= 'Promille op saldo</label>';
        $out .= '<div class="row">';
        $out .= '<div class="col-sm-6">';

        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">&permil;</span>';
        $out .= '<input type="number" ';
        $out .= 'class="form-control margin-bottom" id="var_balance">';
        $out .= '</div>';
        $out .= '<p>Berekend op gewogen gemiddelde van saldo. ';
        $out .= 'Kan ook negatief zijn!</p>';
        $out .= '</div>';

        $out .= '<div class="col-sm-6">';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= $app['config']->get('currency', $app['tschema']);
        $out .= ': basis';
        $out .= '</span>';
        $out .= '<input type="number" class="form-control" id="var_base">';
        $out .= '</div>';
        $out .= '<p>De basis waartegenover berekend wordt. ';
        $out .= 'Kan ook afwijkend van nul zijn.</p>';
        $out .= '</div>';
        $out .= '</div>';
        $out .= '</div>';

        //
        $out .= '<div class="form-group">';
        $out .= '<label for="var_trans_in" class="control-label">';
        $out .= 'Promille op transacties in</label>';
        $out .= '<div class="row">';
        $out .= '<div class="col-sm-6">';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">&permil;</span>';
        $out .= '<input type="number" class="form-control" id="var_trans_in">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="col-sm-6">';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= 'excl. ';
        $out .= '<i class="fa fa-user"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="var_ex_code_in" ';
        $out .= 'placeholder="Account Codes">';
        $out .= '</div>';
        $out .= '<p>Exclusief tegenpartijen: ';
        $out .= 'Account Codes gescheiden door komma\'s</p>';
        $out .= '</div>';
        $out .= '</div>';
        $out .= '</div>';

        //
        $out .= '<div class="form-group">';
        $out .= '<label for="var_trans_out" class="control-label">';
        $out .= 'Promille op transacties uit</label>';
        $out .= '<div class="row">';
        $out .= '<div class="col-sm-6">';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">&permil;</span>';
        $out .= '<input type="number" class="form-control" id="var_trans_out">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="col-sm-6">';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= 'excl. ';
        $out .= '<i class="fa fa-user"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="var_ex_code_out" ';
        $out .= 'placeholder="Account Codes">';
        $out .= '</div>';
        $out .= '<p>Exclusief tegenpartijen: ';
        $out .= 'Account Codes gescheiden door komma\'s</p>';
        $out .= '</div>';
        $out .= '</div>';
        $out .= '</div>';

        //
        $out .= '<div class="form-group">';
        $out .= '<label for="var_minimum" class="control-label">';
        $out .= 'Minimum - maximum</label>';
        $out .= '<div class="row">';
        $out .= '<div class="col-sm-6">';

        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= $app['config']->get('currency', $app['tschema']);
        $out .= ': min';
        $out .= '</span>';

        $out .= '<input type="number" ';
        $out .= 'class="form-control margin-bottom" id="var_min">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="col-sm-6">';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= $app['config']->get('currency', $app['tschema']);
        $out .= ': max';
        $out .= '</span>';
        $out .= '<input type="number" class="form-control" id="var_max">';
        $out .= '</div>';
        $out .= '</div>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '</div>';
        /**/

        $out .= '<div class="form-group">';
        $out .= '<label for="respect_minlimit" class="control-label">';
        $out .= '<input type="checkbox" id="respect_minlimit" checked="checked">';
        $out .= ' Respecteer minimum limieten</label>';
        $out .= '</div>';

        if ($app['config']->get('minlimit', $app['tschema']) !== ''
            || $app['config']->get('maxlimit', $app['tschema']) !== '')
        {
            $out .= '<ul>';

            if ($app['config']->get('minlimit', $app['tschema']) !== '')
            {
                $out .= '<li>Minimum Systeemslimiet: ';
                $out .= $app['config']->get('minlimit', $app['tschema']);
                $out .= ' ';
                $out .= $app['config']->get('currency', $app['tschema']);
                $out .= '</li>';
            }

            if ($app['config']->get('maxlimit', $app['tschema']) !== '')
            {
                $out .= '<li>Maximum Systeemslimiet: ';
                $out .= $app['config']->get('maxlimit', $app['tschema']);
                $out .= ' ';
                $out .= $app['config']->get('currency', $app['tschema']);
                $out .= '</li>';
            }

            $out .= '<li>De Systeemslimieten gelden voor alle Accounts behalve de ';
            $out .= 'Accounts waarbij individuele limieten ingesteld zijn.</li>';

            $out .= '</ul>';
        }

        $out .= '<button class="btn btn-default" id="fill-in">';
        $out .= 'Vul in</button>';

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="get">';
        $out .= '<div class="row">';
        $out .= '<div class="col-xs-12">';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-search"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="q" name="q" value="';
        $out .= $q;
        $out .= '">';
        $out .= '</div>';
        $out .= '</div>';
        $out .= '</div>';
        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $out .= '<ul class="nav nav-tabs" id="nav-tabs">';

        foreach (self::STATUS_RENDER as $k => $s)
        {
            $shsh = $s['hsh'] ?? '';
            $class_li = $shsh == $hsh ? ' class="active"' : '';
            $class_a  = $s['cl'] ?? 'white';

            $out .= '<li' . $class_li . '><a href="#" class="bg-' . $class_a . '" ';
            $out .= 'data-filter="' . $shsh . '">' . $s['lbl'] . '</a></li>';
        }

        $out .= '</ul>';

        $out .= '<form method="post" autocomplete="off">';

        $out .= '<input type="hidden" value="" id="combined-filter">';
        $out .= '<input type="hidden" value="';
        $out .= $hsh;
        $out .= '" name="hsh" id="hsh">';
        $out .= '<input type="hidden" value="" ';
        $out .= 'name="selected_users" id="selected_users">';

        $out .= '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<div class="form-group">';
        $out .= '<label for="from_letscode" class="control-label">';
        $out .= 'Van Account Code';
        $out .= '</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<span class="fa fa-user"></span></span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="from_letscode" name="from_letscode" ';
        $out .= 'value="';
        $out .= $from_letscode;
        $out .= '" ';

        $out .= 'data-typeahead="';
        $out .= $app['typeahead']->ini($app['pp_ary'])
            ->add('accounts', ['status' => 'active'])
            ->add('accounts', ['status' => 'inactive'])
            ->add('accounts', ['status' => 'ip'])
            ->add('accounts', ['status' => 'im'])
            ->add('accounts', ['status' => 'extern'])
            ->str([
                'filter'        => 'accounts',
                'newuserdays'   => $app['config']->get('newuserdays', $app['tschema']),
            ]);
        $out .= '">';

        $out .= '</div>';
        $out .= '<p>Gebruik dit voor een "Eén naar veel" transactie.';
        $out .= 'Alle ingevulde bedragen hieronder ';
        $out .= 'worden van dit Account gehaald.</p>';
        $out .= '</div>';

        $out .= '</div>';

        $out .= '<table class="table table-bordered table-striped ';
        $out .= 'table-hover panel-body footable" ';
        $out .= 'data-filter="#combined-filter" data-filter-minimum="1" ';
        $out .= 'data-minlimit="';
        $out .= $system_minlimit;
        $out .= '" ';
        $out .= 'data-maxlimit="';
        $out .= $system_maxlimit;
        $out .= '"';
        $out .= '>';
        $out .= '<thead>';

        $out .= '<tr>';
        $out .= '<th data-sort-initial="true">Code</th>';
        $out .= '<th data-filter="#filter">Naam</th>';
        $out .= '<th data-sort-ignore="true">Bedrag</th>';
        $out .= '<th data-hide="phone">Saldo</th>';
        $out .= '<th data-hide="phone">Min.limit</th>';
        $out .= '<th data-hide="phone">Max.limit</th>';
        $out .= '<th data-hide="phone, tablet">Postcode</th>';
        $out .= '</tr>';

        $out .= '</thead>';
        $out .= '<tbody>';

        foreach($users as $user_id => $user)
        {
            $status_key = self::STATUS[$user['status']];

            if (isset($user['adate']))
            {
                $status_key = ($status_key == 'active' && $app['new_user_treshold'] < strtotime($user['adate'])) ? 'new' : $status_key;
            }

            $hsh = self::STATUS_RENDER[$status_key]['hsh'] ?: '';
            $hsh .= $status_key == 'leaving' || $status_key == 'new' ? self::STATUS_RENDER['active']['hsh'] : '';
            $hsh .= $status_key == 'active' ? self::STATUS_RENDER['without-new-and-leaving']['hsh'] : '';

            $class = isset(self::STATUS_RENDER[$status_key]['cl']) ? ' class="' . self::STATUS_RENDER[$status_key]['cl'] . '"' : '';

            $out .= '<tr' . $class . ' data-user-id="' . $user_id . '">';

            $out .= '<td>';

            $out .= $app['link']->link_no_attr($app['r_users_show'], $app['pp_ary'],
                ['id' => $user_id], $user['letscode']);

            $out .= '</td>';

            $out .= '<td>';

            $out .= $app['link']->link_no_attr($app['r_users_show'], $app['pp_ary'],
                ['id' => $user_id], $user['name']);

            $out .= '</td>';

            $out .= '<td data-value="' . $hsh . '">';
            $out .= '<input type="number" name="amount[' . $user_id . ']" ';
            $out .= 'class="form-control" ';
            $out .= 'value="';
            $out .= $amount[$user_id] ?? '';
            $out .= '" ';
            $out .= 'min="0" ';
            $out .= 'data-letscode="' . $user['letscode'] . '" ';
            $out .= 'data-user-id="' . $user_id . '" ';
            $out .= 'data-balance="' . $user['saldo'] . '" ';
            $out .= 'data-minlimit="' . $user['minlimit'] . '"';
            $out .= '>';
            $out .= '</td>';

            $out .= '<td>';

            $balance = $user['saldo'];

            $minlimit = $user['minlimit'] === '' ? $system_minlimit : $user['minlimit'];
            $maxlimit = $user['maxlimit'] === '' ? $system_maxlimit : $user['maxlimit'];

            if (($minlimit !== '' && $balance < $minlimit)
                || ($maxlimit !== '' && $balance > $maxlimit))
            {
                $out .= '<span class="text-danger">' . $balance . '</span>';
            }
            else
            {
                $out .= $balance;
            }

            $out .= '</td>';

            $out .= '<td>' . $user['minlimit'] . '</td>';
            $out .= '<td>' . $user['maxlimit'] . '</td>';
            $out .= '<td>' . $user['postcode'] . '</td>';

            $out .= '</tr>';
        }

        $out .= '</tbody>';
        $out .= '</table>';

        $out .= '<div class="panel-heading">';

        $out .= '<div class="form-group">';
        $out .= '<label for="total" class="control-label">Totaal';
        $out .= '</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= $app['config']->get('currency', $app['tschema']);
        $out .= '</span>';
        $out .= '<input type="number" class="form-control" id="total" readonly>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="to_letscode" class="control-label">';
        $out .= 'Aan Account Code';
        $out .= '</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<span class="fa fa-user"></span></span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="to_letscode" name="to_letscode" ';
        $out .= 'value="';
        $out .= $to_letscode;
        $out .= '" ';
        $out .= 'data-typeahead-source="from_letscode">';
        $out .= '</div>';
        $out .= '<p>Gebruik dit voor een "Veel naar één" transactie. ';
        $out .= 'Bijvoorbeeld, een ledenbijdrage. ';
        $out .= 'Alle ingevulde bedragen hierboven ';
        $out .= 'gaan naar dit Account.</p>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="description" class="control-label">';
        $out .= 'Omschrijving</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<span class="fa fa-pencil"></span></span>';
        $out .= '<input type="text" class="form-control" id="description" ';
        $out .= 'name="description" ';
        $out .= 'value="';
        $out .= $description;
        $out .= '" required>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="mail_en" class="control-label">';
        $out .= '<input type="checkbox" id="mail_en" name="mail_en" value="1"';
        $out .= $mail_en ? ' checked="checked"' : '';
        $out .= '>';
        $out .= ' Verstuur notificatie mails</label>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label>';
        $out .= '<input type="checkbox" name="verify" ';
        $out .= 'value="1" required> ';
        $out .= 'Ik heb nagekeken dat de juiste ';
        $out .= 'bedragen en de juiste "Van" of "Aan" ';
        $out .= 'Account Code ingevuld zijn.';
        $out .= '</label>';
        $out .= '</div>';

        $out .= $app['link']->btn_cancel('transactions', $app['pp_ary'], []);

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Massa transactie uitvoeren" ';
        $out .= 'name="zend" class="btn btn-success">';
        $out .= $app['form_token']->get_hidden_input();

        $out .= '</div>';
        $out .= '</div>';

        $out .= '</div>';

        $out .= '<input type="hidden" value="';
        $out .= $transid;
        $out .= '" name="transid">';

        $out .= '</form>';

        $app['menu']->set('mass_transaction');

        return $app->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $app['tschema'],
        ]);
    }
}
