<?php

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class contact_types_add
{
    public function match(Request $request, app $app):Response
    {
        if ($request->isMethod('POST'))
        {
            if ($error_token = $app['form_token']->get_error())
            {
                $app['alert']->error($error_token);

                $app['link']->redirect('contact_types', $app['pp_ary'], []);
            }

            $tc = [];
            $tc['name'] = $request->request->get('name', '');
            $tc['abbrev'] = $request->request->get('abbrev', '');

            $error = (empty($tc['name'])) ? 'Geen naam ingevuld! ' : '';
            $error .= (empty($tc['abbrev'])) ? 'Geen afkorting ingevuld! ' : $error;

            if (!$error)
            {
                if ($app['db']->insert($app['tschema'] . '.type_contact', $tc))
                {
                    $app['alert']->success('Contact type toegevoegd.');
                }
                else
                {
                    $app['alert']->error('Fout bij het opslaan');
                }

                $app['link']->redirect('contact_types', $app['pp_ary'], []);
            }

            $app['alert']->error('Corrigeer één of meerdere velden.');
        }

        $app['heading']->add('Contact type toevoegen');
        $app['heading']->fa('circle-o-notch');

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';
        $out .= '<form method="post">';

        $out .= '<div class="form-group">';
        $out .= '<label for="name" class="control-label">';
        $out .= 'Naam</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon" id="name_addon">';
        $out .= '<span class="fa fa-circle-o-notch"></span></span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="name" name="name" maxlength="20" ';
        $out .= 'value="';
        $out .= $tc['name'] ?? '';
        $out .= '" required>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="abbrev" class="control-label">';
        $out .= 'Afkorting</label>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="abbrev" name="abbrev" maxlength="11" ';
        $out .= 'value="';
        $out .= $tc['abbrev'] ?? '';
        $out .= '" required>';
        $out .= '</div>';

        $out .= $app['link']->btn_cancel('contact_types', $app['pp_ary'], []);

        $out .= '&nbsp;';
        $out .= '<input type="submit" name="zend" ';
        $out .= 'value="Opslaan" class="btn btn-success">';
        $out .= $app['form_token']->get_hidden_input();

        $out .= '</form>';
        $out .= '</div>';
        $out .= '</div>';

        $app['tpl']->add($out);
        $app['tpl']->menu('contact_types');

        return $app['tpl']->get($request);
    }
}