<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class transactions_edit
{
    public function transactions_edit(Request $request, app $app, int $id):Response
    {
        $intersystem_account_schemas = $app['intersystems']->get_eland_accounts_schemas($app['tschema']);

        $s_inter_schema_check = array_merge($app['intersystems']->get_eland($app['tschema']),
            [$app['s_schema'] => true]);

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


        if (!$inter_transaction && ($transaction['real_from'] || $transaction['real_to']))
        {
            $app['alert']->error('De omschrijving van een transactie
                naar een interSysteem dat draait op eLAS kan
                niet aangepast worden.');
            $app['link']->redirect('transactions_show', $app['pp_ary'], ['id' => $id]);
        }

        if ($request->isMethod('POST'))
        {
            $description = trim($request->request->get('description', ''));

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
                    ['id' => $id]);

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

                $app['link']->redirect('transactions_show', $app['pp_ary'], ['id' => $id]);
            }

            $app['alert']->error($errors);
        }

        $app['heading']->add('Omschrijving transactie aanpassen');
        $app['heading']->fa('exchange');

        $out = '<i><ul>';
        $out .= '<li>Enkel Admins kunnen de omschrijving van ';
        $out .= 'een transactie aanpassen.</li>';
        $out .= '<li>Pas de omschrijving van een transactie ';
        $out .= 'enkel aan wanneer het echt noodzakelijk is! ';
        $out .= 'Dit om verwarring te vermijden.</li>';
        $out .= '<li>Transacties kunnen nooit ongedaan ';
        $out .= 'gemaakt worden. Doe een tegenboeking ';
        $out .= 'bij vergissing.</li>';
        $out .= '</ul></i>';

        $out .= '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form  method="post" autocomplete="off">';

        // copied from "show a transaction"

        $out .= '<dl>';

        $out .= '<dt>Tijdstip</dt>';
        $out .= '<dd>';
        $out .= $app['date_format']->get($transaction['cdate'], 'min', $app['tschema']);
        $out .= '</dd>';

        $out .= '<dt>Transactie ID</dt>';
        $out .= '<dd>';
        $out .= $transaction['transid'];
        $out .= '</dd>';

        if ($transaction['real_from'])
        {
            $out .= '<dt>Van interSysteem account</dt>';
            $out .= '<dd>';

            if ($app['pp_admin'])
            {
                $out .= $app['account']->link($transaction['id_from'], $app['pp_ary']);
            }
            else
            {
                $out .= $app['account']->str($transaction['id_from'], $app['tschema']);
            }

            $out .= '</dd>';

            $out .= '<dt>Van interSysteem gebruiker</dt>';
            $out .= '<dd>';
            $out .= '<span class="btn btn-default">';
            $out .= '<i class="fa fa-share-alt"></i></span> ';

            if ($inter_transaction)
            {
                if (isset($s_inter_schema_check[$inter_schema]))
                {
                    $out .= $app['account']->inter_link($inter_transaction['id_from'],
                        $inter_schema);
                }
                else
                {
                    $out .= $app['account']->str($inter_transaction['id_from'],
                        $inter_schema);
                }
            }
            else
            {
                $out .= $transaction['real_from'];
            }

            $out .= '</dd>';
        }
        else
        {
            $out .= '<dt>Van gebruiker</dt>';
            $out .= '<dd>';
            $out .= $app['account']->link($transaction['id_from'], $app['pp_ary']);
            $out .= '</dd>';
        }

        if ($transaction['real_to'])
        {
            $out .= '<dt>Naar interSysteem account</dt>';
            $out .= '<dd>';

            if ($app['pp_admin'])
            {
                $out .= $app['account']->link($transaction['id_to'], $app['pp_ary']);
            }
            else
            {
                $out .= $app['account']->str($transaction['id_to'], $app['tschema']);
            }

            $out .= '</dd>';

            $out .= '<dt>Naar interSysteem gebruiker</dt>';
            $out .= '<dd>';
            $out .= '<span class="btn btn-default"><i class="fa fa-share-alt"></i></span> ';

            if ($inter_transaction)
            {
                if (isset($s_inter_schema_check[$inter_schema]))
                {
                    $out .= $app['account']->inter_link($inter_transaction['id_to'],
                        $inter_schema);
                }
                else
                {
                    $out .= $app['account']->str($inter_transaction['id_to'],
                        $inter_schema);
                }
            }
            else
            {
                $out .= $transaction['real_to'];
            }

            $out .= '</dd>';
        }
        else
        {
            $out .= '<dt>Naar gebruiker</dt>';
            $out .= '<dd>';
            $out .= $app['account']->link($transaction['id_to'], $app['pp_ary']);
            $out .= '</dd>';
        }

        $out .= '<dt>Waarde</dt>';
        $out .= '<dd>';
        $out .= $transaction['amount'] . ' ';
        $out .= $app['config']->get('currency', $app['tschema']);
        $out .= '</dd>';

        $out .= '<dt>Omschrijving</dt>';
        $out .= '<dd>';
        $out .= $transaction['description'];
        $out .= '</dd>';

        $out .= '</dl>';

        $out .= '<div class="form-group">';
        $out .= '<label for="description" class="control-label">';
        $out .= 'Nieuwe omschrijving</label>';
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

        $out .= $app['link']->btn_cancel('transactions_show', $app['pp_ary'], ['id' => $id]);

        $out .= '&nbsp;';
        $out .= '<input type="submit" name="zend" ';
        $out .= 'value="Aanpassen" class="btn btn-primary">';
        $out .= $app['form_token']->get_hidden_input();
        $out .= '<input type="hidden" name="transid" ';
        $out .= 'value="';
        $out .= $transaction['transid'];
        $out .= '">';

        $out .= '</form>';
        $out .= '</div>';
        $out .= '</div>';

        $app['menu']->set('transactions');

        return $app->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $app['tschema'],
        ]);
    }
}
