<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Response;

class transactions_show
{
    public function transactions_show(app $app, int $id):Response
    {
        $intersystem_account_schemas = $app['intersystems']->get_eland_accounts_schemas($app['pp_schema']);

        $s_inter_schema_check = array_merge($app['intersystems']->get_eland($app['pp_schema']),
            [$app['s_schema'] => true]);

        $transaction = $app['db']->fetchAssoc('select t.*
            from ' . $app['pp_schema'] . '.transactions t
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

        $next = $app['db']->fetchColumn('select id
            from ' . $app['pp_schema'] . '.transactions
            where id > ?
            order by id asc
            limit 1', [$id]);

        $prev = $app['db']->fetchColumn('select id
            from ' . $app['pp_schema'] . '.transactions
            where id < ?
            order by id desc
            limit 1', [$id]);

        if ($app['pp_admin']
            && ($inter_transaction
                || !($transaction['real_from']
                    || $transaction['real_to'])))
        {
            $app['btn_top']->edit('transactions_edit', $app['pp_ary'],
                ['id' => $id], 'Omschrijving aanpassen');
        }

        $prev_ary = $prev ? ['id' => $prev] : [];
        $next_ary = $next ? ['id' => $next] : [];

        $app['btn_nav']->nav('transactions_show', $app['pp_ary'],
            $prev_ary, $next_ary, true);

        $app['btn_nav']->nav_list('transactions', $app['pp_ary'],
            [], 'Lijst', 'exchange');

        $app['heading']->add('Transactie');
        $app['heading']->fa('exchange');

        $real_to = $transaction['real_to'] ? true : false;
        $real_from = $transaction['real_from'] ? true : false;

        $intersystem_trans = ($real_from || $real_to) && $app['intersystem_en'];

        $out = '<div class="panel panel-';
        $out .= $intersystem_trans ? 'warning' : 'default';
        $out .= ' printview">';
        $out .= '<div class="panel-heading">';

        $out .= '<dl>';

        $out .= '<dt>Tijdstip</dt>';
        $out .= '<dd>';
        $out .= $app['date_format']->get($transaction['cdate'], 'min', $app['pp_schema']);
        $out .= '</dd>';

        $out .= '<dt>Transactie ID</dt>';
        $out .= '<dd>';
        $out .= $transaction['transid'];
        $out .= '</dd>';

        if ($real_from)
        {
            $out .= '<dt>Van interSysteem Account (in dit Systeem)</dt>';
            $out .= '<dd>';

            if ($app['pp_admin'])
            {
                $out .= $app['account']->link($transaction['id_from'], $app['pp_ary']);
            }
            else
            {
                $out .= $app['account']->str($transaction['id_from'], $app['pp_schema']);
            }

            $out .= '</dd>';

            $out .= '<dt>Van Account in het andere Systeem</dt>';
            $out .= '<dd>';
            $out .= '<span class="btn btn-default">';
            $out .= '<i class="fa fa-share-alt"></i></span> ';

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

            $out .= $user_from;

            $out .= '</dd>';
        }
        else
        {
            $out .= '<dt>Van Account</dt>';
            $out .= '<dd>';
            $out .= $app['account']->link($transaction['id_from'], $app['pp_ary']);
            $out .= '</dd>';
        }

        if ($real_to)
        {
            $out .= '<dt>Naar interSysteem Account (in dit Systeem)</dt>';
            $out .= '<dd>';

            if ($app['pp_admin'])
            {
                $out .= $app['account']->link($transaction['id_to'], $app['pp_ary']);
            }
            else
            {
                $out .= $app['account']->str($transaction['id_to'], $app['pp_schema']);
            }

            $out .= '</dd>';

            $out .= '<dt>Naar Account in het andere Systeem</dt>';
            $out .= '<dd>';
            $out .= '<span class="btn btn-default">';
            $out .= '<i class="fa fa-share-alt"></i></span> ';

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

            $out .= $user_to;

            $out .= '</dd>';
        }
        else
        {
            $out .= '<dt>Naar Account</dt>';
            $out .= '<dd>';
            $out .= $app['account']->link($transaction['id_to'], $app['pp_ary']);
            $out .= '</dd>';
        }

        $out .= '<dt>Waarde</dt>';
        $out .= '<dd>';
        $out .= $transaction['amount'] . ' ';
        $out .= $app['config']->get('currency', $app['pp_schema']);
        $out .= '</dd>';

        $out .= '<dt>Omschrijving</dt>';
        $out .= '<dd>';
        $out .= $transaction['description'];
        $out .= '</dd>';

        $out .= '</dl>';

        if ($intersystem_trans)
        {
            $out .= '<div class="row">';
            $out .= '<div class="col-md-12">';
            $out .= '<h2>';
            $out .= 'Dit is een interSysteem transactie ';
            $out .= $real_from ? 'vanuit' : 'naar';
            $out .= ' een Account in ander Systeem';
            $out .= '</h2>';
            $out .= '<p>';
            $out .= 'Een interSysteem transactie bestaat ';
            $out .= 'altijd uit twee gekoppelde transacties, die ';
            $out .= 'elks binnen hun eigen Systeem plaatsvinden, ';
            $out .= 'elks uitgedrukt in de eigen tijdsmunt, maar met ';
            $out .= 'gelijke tijdswaarde in beide transacties. ';
            $out .= 'De zogenaamde interSysteem Accounts ';
            $out .= '(in stippellijn) ';
            $out .= 'doen dienst als intermediair.';
            $out .= '</p>';
            $out .= '</div>';
            $out .= '</div>';

            $out .= '<div class="row">';

            $out .= '<div class="col-md-6">';
            $out .= '<div class="thumbnail">';
            $out .= '<img src="';

            if ($real_from)
            {
                $out .= $app['assets']->get('there-from-inter.png');
            }
            else
            {
                $out .= $app['assets']->get('here-from-inter.png');
            }

            $out .= '">';
            $out .= '</div>';
            $out .= '<div class="caption">';
            $out .= '<ul>';
            $out .= '<li>';
            $out .= '<strong>Acc-1</strong> ';
            $out .= 'Het Account in ';
            $out .= $real_from ? 'het andere' : 'dit';
            $out .= ' Systeem dat de ';
            $out .= 'transactie initiëerde. ';
            $out .= '(';

            if ($real_from)
            {
                $out .= '<span class="btn btn-default">';
                $out .= '<i class="fa fa-share-alt"></i></span> ';
                $out .= $user_from;
            }
            else
            {
                $out .= $app['account']->link($transaction['id_from'], $app['pp_ary']);
            }

            $out .= ')';
            $out .= '</li>';
            $out .= '<li>';
            $out .= '<strong>Tr-1</strong> ';

            if ($real_from)
            {
                $str = 'De transactie in het andere ';
                $str .= 'Systeem uitgedrukt ';
                $str .= 'in de eigen tijdsmunt.';

                if ($inter_transaction
                    && isset($app['intersystem_ary']['eland'][$inter_schema]))
                {
                    $out .= $app['link']->link_no_attr('transactions', [
                            'system'		=> $app['systems']->get_system($inter_schema),
                            'role_short'	=> 'g',
                        ], ['id' => $inter_transaction['id']], $str);
                }
                else
                {
                    $out .= $str;
                }
            }
            else
            {
                $out .= 'De transactie in dit ';
                $out .= 'Systeem uitgedrukt ';
                $out .= 'in de eigen tijdsmunt';
                $out .= ' (';
                $out .= $transaction['amount'];
                $out .= ' ';
                $out .= $app['config']->get('currency', $app['pp_schema']);
                $out .= ').';
            }

            $out .= '</li>';
            $out .= '<li>';
            $out .= '<strong>iAcc-1</strong> ';

            if ($real_from)
            {
                $out .= 'Het interSysteem Account van dit Systeem in het ';
                $out .= 'andere Systeem.';
            }
            else
            {
                $out .= 'Het interSysteem Account van het andere Systeem ';
                $out .= 'in dit Systeem. (';

                if ($app['pp_admin'])
                {
                    $out .= $app['account']->link($transaction['id_to'],
                        $app['pp_ary']);
                }
                else
                {
                    $out .= $app['account']->str($transaction['id_to'],
                        $app['pp_schema']);
                }

                $out .= ')';
            }

            $out .= '</li>';
            $out .= '</ul>';
            $out .= '</div>';
            $out .= '</div>';

            $out .= '<div class="col-md-6">';
            $out .= '<div class="thumbnail">';
            $out .= '<img src="';

            if ($real_from)
            {
                $out .= $app['assets']->get('here-from-inter.png');
            }
            else
            {
                $out .= $app['assets']->get('there-from-inter.png');
            }

            $out .= '">';
            $out .= '</div>';
            $out .= '<div class="caption bg-warning">';
            $out .= '<ul>';
            $out .= '<li>';
            $out .= '<strong>iAcc-2</strong> ';

            if ($real_from)
            {
                $out .= 'Het interSysteem Account van ';
                $out .= 'het andere Systeem in dit ';
                $out .= 'Systeem. ';
                $out .= '(';

                if ($app['pp_admin'])
                {
                    $out .= $app['account']->link($transaction['id_from'],
                        $app['pp_ary']);
                }
                else
                {
                    $out .= $app['account']->str($transaction['id_from'],
                        $app['pp_schema']);
                }

                $out .= ')';
            }
            else
            {
                $out .= 'Het interSysteem Account van dit Systeem in het ';
                $out .= 'andere Systeem.';
            }

            $out .= '</li>';
            $out .= '<li>';
            $out .= '<strong>Tr-2</strong> ';

            if ($real_from)
            {
                $out .= 'De transactie in dit Systeem uitgedrukt ';
                $out .= 'in de eigen tijdsmunt ';
                $out .= '(';
                $out .= $transaction['amount'] . ' ';
                $out .= $app['config']->get('currency', $app['pp_schema']);
                $out .= ') ';
                $out .= 'met gelijke tijdswaarde als Tr-1.';
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
                    $out .= $app['link']->link_no_attr('transactions', [
                            'system'	=> $app['systems']->get_system($inter_schema),
                            'role_short'	=> 'g',
                        ], ['id' => $inter_transaction['id']], $str);
                }
                else
                {
                    $out .= $str;
                }
            }

            $out .= '</li>';
            $out .= '<li>';
            $out .= '<strong>Acc-2</strong> ';

            if ($real_from)
            {
                $out .= 'Het bestemmings Account in dit Systeem ';
                $out .= '(';
                $out .= $app['account']->link($transaction['id_to'], $app['pp_ary']);
                $out .= ').';
            }
            else
            {
                $out .= 'Het bestemmings Account in het andere ';
                $out .= 'Systeem ';
                $out .= '(';
                $out .= '<span class="btn btn-default">';
                $out .= '<i class="fa fa-share-alt"></i></span> ';
                $out .= $user_to;
                $out .= ').';
            }

            $out .= '</li>';
            $out .= '</ul>';
            $out .= '</div>';
            $out .= '</div>';

            $out .= '</div>';
        }

        $out .= '</div></div>';

        $app['menu']->set('transactions');

        return $app->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $app['pp_schema'],
        ]);
    }
}
