<?php

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use controller\intersystems;

class intersystems_show
{
    public function get(Request $request, app $app, int $id):Response
    {
        $group = $app['db']->fetchAssoc('select *
            from ' . $app['tschema'] . '.letsgroups
            where id = ?', [$id]);

        if (!$group)
        {
            $app['alert']->error('Systeem niet gevonden.');
            $app['link']->redirect('intersystems', $app['pp_ary'], []);
        }

        if ($group['localletscode'] === '')
        {
            $user = false;
        }
        else
        {
            $user = $app['db']->fetchAssoc('select *
                from ' . $app['tschema'] . '.users
                where letscode = ?', [$group['localletscode']]);
        }

        $app['btn_top']->edit('intersystems_edit', $app['pp_ary'],
            ['id' => $id], 'Intersysteem aanpassen');

        $app['btn_top']->del('intersystems_del', $app['pp_ary'],
            ['id' => $id], 'Intersysteem verwijderen');

        $app['btn_nav']->nav_list('intersystems', $app['pp_ary'],
            [], 'Lijst', 'share-alt');

        $app['assets']->add(['elas_soap_status.js']);

        $app['heading']->add('InterSysteem: ');
        $app['heading']->add($group['groupname']);
        $app['heading']->fa('share-alt');

        $out = '<div class="panel panel-default printview">';
        $out .= '<div class="panel-heading">';

        $out .= '<dl class="dl-horizontal">';
        $out .= '<dt>Status</dt>';

        $group_schema = $app['systems']->get_schema_from_legacy_eland_origin($group['url']);

        if ($group_schema)
        {
            $out .= '<dd><span class="btn btn-info btn-xs">eLAND server</span>';

            if (!$app['config']->get('template_lets', $group_schema))
            {
                $out .= ' <span class="btn btn-danger btn-xs">';
                $out .= '<i class="fa fa-exclamation-triangle"></i> ';
                $out .= 'Niet geconfigureerd als Tijdsbank</span>';
            }

            if (!$app['config']->get('interlets_en', $group_schema))
            {
                $out .= ' <span class="btn btn-danger btn-xs">';
                $out .= '<i class="fa fa-exclamation-triangle"></i> ';
                $out .= 'De InterSysteem-mogelijkheid is niet ingeschakeld ';
                $out .= 'in configuratie</span>';
            }

            $out .= '</dd>';
        }
        else
        {
            $out .= '<dd><i><span data-elas-soap-status="';

            $out .= htmlspecialchars($app['link']->context_path('elas_soap_status',
                $app['pp_ary'], ['group_id' => $group['id']]));

            $out .= '">';
            $out .= 'Bezig met eLAS soap status te bekomen...</span></i>';
            $out .= '</dd>';

        }

        $out .= '<dt>Systeem Naam</dt>';
        $out .= '<dd>';
        $out .= $group['groupname'];
        $out .= '</dd>';

        $out .= '<dt>API methode</dt>';
        $out .= '<dd>';
        $out .= $group['apimethod'];
        $out .= '</dd>';

        $out .= '<dt>API key</dt>';
        $out .= '<dd>';
        $out .= $group['remoteapikey'];
        $out .= '</dd>';

        $out .= '<dt>Lokale Account Code</dt>';
        $out .= '<dd>';

        if ($user)
        {
            $out .= $app['link']->link('users', $app['pp_ary'],
                ['id' => $user['id']], $group['localletscode'],
                [
                    'class' => 'btn btn-default btn-xs',
                    'title'	=> 'Ga naar het interSysteem account',
                ]);

            if (!in_array($user['status'], [1, 2, 7]))
            {
                $out .= ' ';

                $out .= $app['link']->link_fa('users', $app['pp_ary'],
                    ['edit' => $user['id']], 'Status!',
                    [
                        'class'	=> 'btn btn-danger btn-xs',
                        'title'	=> 'Het interSysteem-account heeft een ongeldige status. De status moet van het type extern, actief of uitstapper zijn.',
                    ],
                    'exclamation-triangle');
            }
            if ($user['accountrole'] != 'interlets')
            {
                $out .= ' ';
                $out .= $app['link']->link_fa('users', $app['pp_ary'],
                    ['edit' => $user['id']], 'Rol!',
                    [
                        'class'	=> 'btn btn-danger btn-xs',
                        'title'	=> 'Het interSysteem-account heeft een ongeldige rol. De rol moet van het type interSysteem zijn.',
                    ],
                    'fa-exclamation-triangle');
            }
        }
        else
        {
            $out .= $group['localletscode'];

            if ($group['apimethod'] != 'internal' && !$user)
            {
                $out .= ' <span class="label label-danger" title="Er is geen account gevonden met deze code">';
                $out .= '<i class="fa fa-exclamation-triangle"></i> Account</span>';
            }
        }

        $out .= '</dd>';

        $out .= '<dt>Remote Account Code</dt>';
        $out .= '<dd>';
        $out .= $group['myremoteletscode'];
        $out .= '</dd>';

        $out .= '<dt>URL</dt>';
        $out .= '<dd>';
        $out .= $group['url'];
        $out .= '</dd>';

        $out .= '<dt>Preshared Key</dt>';
        $out .= '<dd>';
        $out .= $group['presharedkey'];
        $out .= '</dd>';
        $out .= '</dl>';

        $out .= '</div></div>';

        $out .= intersystems::get_schemas_groups($app);

        $app['tpl']->add($out);
        $app['tpl']->menu('intersystems');

        return $app['tpl']->get($request);
    }
}
