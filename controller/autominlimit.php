<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class autominlimit
{
    public function autominlimit(Request $request, app $app):Response
    {
        if ($request->isMethod('POST'))
        {
            if ($error_token = $app['form_token']->get_error())
            {
                $app['alert']->error($error_token);
                $app['link']->redirect('autominlimit', $app['pp_ary'], []);
            }

            $data = [
                'enabled'			=> $request->request->get('enabled', false),
                'exclusive'			=> $request->request->get('exclusive', ''),
                'trans_percentage'	=> $request->request->get('trans_percentage', 100),
                'trans_exclusive'	=> $request->request->get('trans_exclusive', ''),
            ];

            $app['xdb']->set('setting', 'autominlimit', $data, $app['pp_schema']);

            $app['alert']->success('De automatische minimum limiet instellingen zijn aangepast.');
            $app['link']->redirect('autominlimit', $app['pp_ary'], []);
        }
        else
        {
            $row = $app['xdb']->get('setting', 'autominlimit', $app['pp_schema']);

            if ($row)
            {
                $data = $row['data'];
            }
            else
            {
                $data = [
                    'enabled'				=> false,
                    'exclusive'				=> '',
                    'trans_percentage'		=> 100,
                    'trans_exclusive'		=> '',
                ];
            }
        }

        $app['heading']->add('Automatische minimum limiet');
        $app['heading']->fa('arrows-v');

        $out = '<div class="panel panel-info">';

        $out .= '<div class="panel-heading"><p>';
        $out .= 'Met dit formulier kan een Automatische Minimum Limiet ingesteld worden. ';
        $out .= 'De individuele Minimum Limiet van Accounts zal zo automatisch lager ';
        $out .= 'worden door ontvangen transacties ';
        $out .= 'tot de ';
        $out .= $app['link']->link_no_attr('config',
            $app['pp_ary'], ['tab' => 'balance'], 'Minimum Systeemslimiet');
        $out .= ' bereikt wordt. ';
        $out .= 'De individuele Account Minimum Limiet wordt gewist wanneer de ';
        $out .= $app['link']->link_no_attr('config',
            $app['pp_ary'], ['tab' => 'balance'], 'Minimum Systeemslimiet');
        $out .= ' bereikt of onderschreden wordt.</p>';
        $out .= '<p>Wanneer geen Minimum Systeemslimiet is ingesteld, ';
        $out .= 'dan blijft de individuele Account Minimum Limiet bij elke ';
        $out .= 'transactie naar het Account telkens dalen.</p>';

        $out .= '<p>Individuele Account Minimum Limieten die ';
        $out .= 'lager zijn dan de algemene Minimum Systeemslimiet ';
        $out .= 'blijven altijd ongewijzigd.</p>';
        $out .= '<p>Wanneer de Automatische Minimum Limiet systematisch ';
        $out .= 'voor instappende leden gebruikt wordt, is het ';
        $out .= 'nuttig de ';
        $out .= $app['link']->link_no_attr('config',
            $app['pp_ary'], ['tab' => 'balance'],
            'Preset Individuele Account Minimum Limiet');
        $out .= ' ';
        $out .= 'in te vullen in de instellingen.</p>';

        $out .= '<form method="post">';

        $out .= '<div class="form-group">';
        $out .= '<label for="enabled" class="control-label">';
        $out .= '<input type="checkbox" id="enabled" name="enabled" value="1" ';
        $out .= $data['enabled'] ? ' checked="checked"' : '';
        $out .= '>';
        $out .= ' Zet de automatische minimum limiet aan</label>';
        $out .= '</div>';

        $out .= '<hr>';

        $out .= '<h3>Voor accounts</h3>';
        $out .= '<p>De automatische minimum limiet is enkel van toepassing op actieve accounts die ';
        $out .= 'rol van gewone gebruiker hebben (user) en die ';
        $out .= 'niet de status uitstapper hebben. Hieronder kunnnen nog verder individuele accounts uitgesloten ';
        $out .= 'worden.</p>';

        $out .= '<div class="form-group">';
        $out .= '<label for="exclusive" class="control-label">';
        $out .= 'Exclusief</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<span class="fa fa-user"></span></span>';
        $out .= '<input type="text" id="exclusive" name="exclusive" ';
        $out .= 'value="';
        $out .= $data['exclusive'];
        $out .= '" ';
        $out .= 'class="form-control">';
        $out .= '</div>';
        $out .= '<p>';
        $out .= 'Account Codes gescheiden door comma\'s</p>';
        $out .= '</div>';

        $out .= '<hr>';

        $out .= '<h3>Trigger voor daling van de minimum limiet.</h3>';
        $out .= '<h4>Ontvangen transacties laten de minimum limiet dalen.</h4>';

        $out .= '<div class="form-group">';
        $out .= '<label for="trans_percentage" class="control-label">';
        $out .= 'Percentage van ontvangen bedrag</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<span class="fa fa-percent"></span></span>';
        $out .= '<input type="number" id="trans_percentage" name="trans_percentage" ';
        $out .= 'value="';
        $out .= $data['trans_percentage'];
        $out .= '" ';
        $out .= 'class="form-control">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="trans_exclusive" class="control-label">';
        $out .= 'Exclusief tegenpartijen</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<span class="fa fa-user"></span></span>';
        $out .= '<input type="text" id="trans_exclusive" name="trans_exclusive" ';
        $out .= 'value="';
        $out .= $data['trans_exclusive'];
        $out .= '" ';
        $out .= 'class="form-control">';
        $out .= '</div>';
        $out .= '<p>';
        $out .= 'Account Codes gescheiden door comma\'s</p>';
        $out .= '</div>';

        $out .= '<input type="submit" value="Aanpassen" name="zend" class="btn btn-primary btn-lg">';
        $out .= $app['form_token']->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $app['menu']->set('autominlimit');

        return $app->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $app['pp_schema'],
        ]);
    }
}
