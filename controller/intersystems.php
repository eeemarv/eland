<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Response;

class intersystems
{
    public function intersystems(app $app):Response
    {
        $intersystems = $app['db']->fetchAll('select *
            from ' . $app['tschema'] . '.letsgroups');

        $letscodes = [];

        foreach ($intersystems as $key => $sys)
        {
            $sys_host = strtolower(parse_url($sys['url'], PHP_URL_HOST) ?? '');

            $letscodes[] = $sys['localletscode'];

            $sys_schema = $app['systems']->get_schema_from_legacy_eland_origin($sys['url']);

            if ($sys_schema)
            {
                $groups[$key]['eland'] = true;
                $groups[$key]['schema'] = $sys_schema;

                $groups[$key]['user_count'] = $app['db']->fetchColumn('select count(*)
                    from ' . $sys_schema . '.users
                    where status in (1, 2)');
            }
            else if ($sys['apimethod'] == 'internal')
            {
                $groups[$key]['user_count'] = $app['db']->fetchColumn('select count(*)
                    from ' . $app['tschema'] . '.users
                    where status in (1, 2)');
            }
            else
            {
                $groups[$key]['user_count'] = $app['predis']->get($sys_host . '_active_user_count');
            }
        }

        $users_letscode = [];

        $intersystem_users = $app['db']->executeQuery('select id, status, letscode, accountrole
            from ' . $app['tschema'] . '.users
            where letscode in (?)',
            [$letscodes],
            [\Doctrine\DBAL\Connection::PARAM_INT_ARRAY]);

        foreach ($intersystem_users as $u)
        {
            $users_letscode[$u['letscode']] = [
                'id'			=> $u['id'],
                'status'		=> $u['status'],
                'accountrole'	=> $u['accountrole'],
            ];
        }

        $app['btn_top']->add('intersystems_add', $app['pp_ary'],
            [], 'InterSysteem toevoegen');

        $app['heading']->add('eLAS/eLAND InterSysteem');
        $app['heading']->fa('share-alt');

        $out = '<p>';
        $out .= 'Een eLAS/eLAND interSysteem verbinding laat intertrading toe tussen ';
        $out .= 'je eigen Systeem en een ander Systeem dat draait op eLAS of eLAND software.';
        $out .= 'Beide Systemen dienen hiervoor een munteenheid te hebben die gebaseerd is ';
        $out .= 'op tijd. Ze zijn dus Tijdsbanken en dienen zo ';
        $out .= 'geconfigureerd te zijn (Zie Admin > Instellingen > Systeem). ';
        $out .= 'Wanneer je deze pagina kan zien is dit reeds het geval.';
        $out .= '</p>';

        if (count($groups))
        {
            $out .= '<div class="panel panel-primary printview">';

            $out .= '<div class="table-responsive">';
            $out .= '<table class="table table-bordered table-hover table-striped footable">';
            $out .= '<thead>';
            $out .= '<tr>';
            $out .= '<th data-sort-initial="true">Account</th>';
            $out .= '<th>Systeem</th>';
            $out .= '<th data-hide="phone">leden</th>';
            $out .= '<th data-hide="phone, tablet" data-sort-ignore="true">api</th>';
            $out .= '</tr>';
            $out .= '</thead>';

            $out .= '<tbody>';

            foreach($groups as $g)
            {
                $out .= '<tr>';
                $out .= '<td>';

                if ($g['apimethod'] == 'elassoap')
                {
                    $user = $users_letscode[$g['localletscode']] ?? [];

                    if ($user)
                    {
                        $out .= $app['link']->link($app['r_users_show'], $app['pp_ary'],
                            ['id' => $user['id']], $g['localletscode'],
                            [
                                'class'	=> 'btn btn-default',
                                'title'	=> 'Ga naar het interSysteem account',
                            ]);

                        if (!in_array($user['status'], [1, 2, 7]))
                        {
                            $out .= ' ';
                            $out .= $app['link']->link_fa($app['r_users_show'], $app['pp_ary'],
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
                            $out .= $app['link']->link_fa($app['r_users_show'], $app['pp_ary'],
                                ['edit' => $user['id']], 'Rol!',
                                [
                                    'class'	=> 'btn btn-danger btn-xs',
                                    'title'	=> 'Het interSysteem Account heeft een ongeldige rol. De rol moet van het type interSysteem zijn.',
                                ],
                                'fa-exclamation-triangle');
                        }
                    }
                    else
                    {
                        $out .= $g['localletscode'];

                        if ($g['apimethod'] != 'internal' && !$user)
                        {
                            $out .= ' <span class="label label-danger" title="Er is geen account gevonden met deze code">';
                            $out .= '<i class="fa fa-exclamation-triangle"></i> Account</span>';
                        }
                    }
                }
                $out .= '</td>';

                $out .= '<td>';

                $out .= $app['link']->link_no_attr('intersystems_show', $app['pp_ary'],
                    ['id' => $g['id']], $g['groupname']);

                if (isset($g['eland']))
                {
                    $out .= ' <span class="label label-info" title="Dit Systeem bevindt zich op dezelfde eland-server">';
                    $out .= 'eLAND</span>';

                    if (!$app['config']->get('template_lets', $g['schema']))
                    {
                        $out .= ' <span class="label label-danger" ';
                        $out .= 'title="Dit Systeem is niet geconfigureerd als Tijdsbank.">';
                        $out .= '<i class="fa fa-exclamation-triangle"></i> ';
                        $out .= 'geen Tijdsbank</span>';
                    }

                    if (!$app['config']->get('interlets_en', $g['schema']))
                    {
                        $out .= ' <span class="label label-danger" ';
                        $out .= 'title="InterSysteem-mogelijkheid is niet ';
                        $out .= 'ingeschakeld in de configuratie van dit systeem.">';
                        $out .= '<i class="fa fa-exclamation-triangle"></i> ';
                        $out .= 'geen interSysteem</span>';
                    }
                }

                $out .= '</td>';

                $out .= '<td>';
                $out .= $g['user_count'];
                $out .= '</td>';

                $out .= '<td>';
                $out .= $g['apimethod'];
                $out .= '</td>';
                $out .= '</tr>';
            }

            $out .= '</tbody>';
            $out .= '</table>';
            $out .= '</div></div>';
        }
        else
        {
            $out .= '<div class="panel panel-primary">';
            $out .= '<div class="panel-heading">';
            $out .= '<p>Er zijn nog geen interSysteem-verbindingen.</p>';
            $out .= '</div></div>';
        }

        $out .= self::get_schemas_groups($app);

        $app['tpl']->add($out);
        $app['tpl']->menu('intersystems');

        return $app['tpl']->get();
    }

    public static function get_schemas_groups(app $app):string
    {
        $out = '<div class="panel panel-default"><div class="panel-heading">';
        $out .= '<h3>Een interSysteem verbinding aanmaken met een Systeem dat draait op eLAS. ';
        $out .= 'Zie <a href="https://eland.letsa.net/elas-intersysteem-koppeling-maken.html">hier</a> ';
        $out .= 'voor de procedure.</h3>';
        $out .= '<p>Voor het aanmaken van interSysteem verbindingen ';
        $out .= 'in deze eLAND-server zie onder!</p>';
        $out .= '</div>';
        $out .= '<ul>';
        $out .= '<li> Kies \'elassoap\' als API methode.</li>';
        $out .= '<li> De API Key moet je aanvragen bij de beheerder van het andere Systeem. ';
        $out .= 'Het is een sleutel die je eigen Systeem toelaat om met het andere Systeem (in eLAS) te communiceren. </li>';
        $out .= '<li> De Lokale Account Code is de Account Code waarmee het andere Systeem in dit Systeem bekend is. ';
        $out .= 'Dit account moet al bestaan.</li>';
        $out .= '<li> De Remote Account Code is de Account Code waarmee dit Systeem bij het ';
        $out .= 'andere Systeem bekend is. Deze moet in het andere Systeem aangemaakt zijn.</li>';
        $out .= '<li> De URL is de weblocatie van het andere Systeem. </li>';
        $out .= '<li> De Preshared Key is een gedeelde sleutel waarmee de interSysteem ';
        $out .= 'transacties ondertekend worden.  Deze moet identiek zijn aan de Preshared Key ';
        $out .= 'in het Account van dit Systeem bij het andere Systeem.</li>';
        $out .= '</ul>';
        $out .= '</div>';

        $out .= '<div class="panel panel-default">';
        $out .= '<div class="panel-heading">';
        $out .= '<h3>Een interSysteem Verbinding aanmaken met een ander Systeem op deze eLAND server.</h3>';
        $out .= '</div>';
        $out .= '<ul>';
        $out .= '<li> Je kan een ander Tijdsbank-Systeem dat dezelfde eLAND-server gebruikt ';
        $out .= 'op vereenvoudigde manier verbinding leggen zonder ';
        $out .= 'het uitwisselen van Api Key, Preshared Key en Remote Account Code. ';
        $out .= '</li>';
        $out .= '<li> ';
        $out .= 'Contacteer altijd eerst vooraf de beheerders van het andere Systeem ';
        $out .= 'waarmee je een interSysteem verbinding wil opzetten. ';
        $out .= 'En verifiëer of zij ook een Tijdsbank-Systeem hebben en of zij geïnteresseerd zijn.</li>';
        $out .= '<li> Voor het leggen van een InterSysteem-verbinding, kijk in de tabel hieronder. ';
        $out .= 'Maak het interSysteem aan door op \'Creëer\' in ';
        $out .= 'kolom \'lok.interSysteem\' te klikken en vervolgens op Toevoegen. ';
        $out .= 'Dan, weer in de tabel onder, ';
        $out .= 'klik je op knop \'Creëer\' in de kolom \'lok.Account\'. ';
        $out .= 'Vul een postcode in en klik op \'Toevoegen\'. ';
        $out .= 'Nu het interSysteem en haar Account aangemaakt zijn wil dat zeggen dat jouw Systeem toestemming ';
        $out .= 'geeft aan het andere Systeem voor de interSysteem verbinding. Wanneer ';
        $out .= 'het andere Systeem op dezelfde wijze een interSysteem en Account aanmaakt ';
        $out .= 'is de InterSysteem-verbinding compleet. ';
        $out .= 'In alle vier kolommen (lok.interSysteem, lok.Account, rem.interSysteem, rem.Account) zie je dan het ';
        $out .= '<span class="btn btn-success btn-xs">OK</span>-teken.</li>';
        $out .= '</ul>';

        $url_ary = [];

        foreach ($app['systems']->get_schemas() as $sys_schema)
        {
            $url_ary[] = $app['systems']->get_legacy_eland_origin($sys_schema);
        }

        $loc_group_ary = $loc_account_ary = [];
        $rem_group_ary =  $rem_account_ary = $group_user_count_ary = [];
        $loc_letscode_ary = [];

        $groups = $app['db']->executeQuery('select localletscode, url, id
            from ' . $app['tschema'] . '.letsgroups
            where url in (?)',
            [$url_ary],
            [\Doctrine\DBAL\Connection::PARAM_STR_ARRAY]);

        foreach ($groups as $group)
        {
            $loc_letscode_ary[] = $group['localletscode'];
            $h = strtolower(parse_url($group['url'], PHP_URL_HOST));
            $loc_group_ary[$h] = $group;
        }

        $interlets_accounts = $app['db']->executeQuery('select id, letscode, status, accountrole
            from ' . $app['tschema'] . '.users
            where letscode in (?)',
            [$loc_letscode_ary],
            [\Doctrine\DBAL\Connection::PARAM_STR_ARRAY]);

        foreach ($interlets_accounts as $u)
        {
            $loc_account_ary[$u['letscode']] = $u;
        }

        foreach ($app['systems']->get_schemas() as $rem_schema)
        {
            $rem_group = $app['db']->fetchAssoc('select localletscode, url, id
                from ' . $rem_schema . '.letsgroups
                where url = ?', [$app['systems']->get_legacy_eland_origin($rem_schema)]);

            $group_user_count_ary[$rem_schema] = $app['db']->fetchColumn('select count(*)
                from ' . $rem_schema . '.users
                where status in (1, 2)');

            if ($rem_group)
            {
                $rem_origin = $app['systems']->get_legacy_eland_origin($rem_schema);

                $rem_group_ary[$rem_origin] = $rem_group;

                if ($rem_group['localletscode'])
                {
                    $rem_account = $app['db']->fetchAssoc('select id, letscode, status, accountrole
                        from ' . $rem_schema . '.users where letscode = ?', [$rem_group['localletscode']]);

                    if ($rem_account)
                    {
                        $rem_account_ary[$rem_origin] = $rem_account;
                    }
                }
            }
        }

        $out .= '<div class="panel-heading">';
        $out .= '<h3>Systemen op deze eLAND server</h3>';
        $out .= '</div>';

        $out .= '<table class="table table-bordered ';
        $out .= 'table-hover table-striped footable">';
        $out .= '<thead>';
        $out .= '<tr>';
        $out .= '<th data-sort-initial="true">Systeem Naam</th>';
        $out .= '<th data-hide="phone, tablet">Domein</th>';
        $out .= '<th data-hide="phone, tablet">Leden</th>';
        $out .= '<th>lok.interSysteem</th>';
        $out .= '<th>lok.Account</th>';
        $out .= '<th>rem.interSysteem</th>';
        $out .= '<th>rem.Account</th>';
        $out .= '</tr>';
        $out .= '</thead>';

        $out .= '<tbody>';

        $unavailable_explain = false;

        foreach($app['systems']->get_schemas() as $rem_schema)
        {
            $rem_origin = $app['systems']->get_legacy_eland_origin($rem_schema);

            $out .= '<tr';

            if (!$app['config']->get('template_lets', $rem_schema)
                || !$app['config']->get('interlets_en', $rem_schema))
            {
                $out .= ' class="danger"';

                $unavailable_explain = true;
            }

            $out .= '>';

            $out .= '<td>';
            $out .= $app['config']->get('systemname', $rem_schema);

            if (!$app['config']->get('template_lets', $rem_schema))
            {
                $out .= ' <span class="label label-danger" ';
                $out .= 'title="Dit Systeem is niet ';
                $out .= 'geconfigureerd als Tijdsbank.">';
                $out .= '<i class="fa fa-exclamation-triangle">';
                $out .= '</i></span>';
            }

            if (!$app['config']->get('interlets_en', $rem_schema))
            {
                $out .= ' <span class="label label-danger" ';
                $out .= 'title="interSysteem is niet ';
                $out .= 'ingeschakeld in de configuratie">';
                $out .= '<i class="fa fa-exclamation-triangle">';
                $out .= '</i></span>';
            }

            $out .= '</td>';

            $out .= '<td>';
            $out .= $rem_origin;
            $out .= '</td>';

            $out .= '<td>';
            $out .= $group_user_count_ary[$rem_schema];
            $out .= '</td>';

            if ($app['tschema'] === $rem_schema)
            {
                $out .= '<td colspan="4">';
                $out .= 'Eigen Systeem';
                $out .= '</td>';
            }
            else
            {
                $out .= '<td>';

                if (isset($loc_group_ary[$rem_origin])
                    && is_array($loc_group_ary[$rem_origin]))
                {
                    $loc_group = $loc_group_ary[$rem_origin];

                    $out .= $app['link']->link('intersystems_show', $app['pp_ary'],
                        ['id' => $loc_group['id']], 'OK',
                        ['class'	=> 'btn btn-success btn-xs']);
                }
                else
                {
                    if ($app['config']->get('template_lets', $rem_schema)
                        && $app['config']->get('interlets_en', $rem_schema))
                    {
                        $out .= $app['link']->link('intersystems_add', $app['pp_ary'],
                            ['add_schema' => $rem_schema], 'Creëer',
                            ['class' => 'btn btn-default']);
                    }
                    else
                    {
                        $out .= '<i class="fa fa-times text-danger"></i>';
                    }
                }

                $out .= '</td>';
                $out .= '<td>';

                if (isset($loc_group_ary[$rem_origin]))
                {
                    $loc_group = $loc_group_ary[$rem_origin];

                    if (is_array($loc_acc = $loc_account_ary[$loc_group['localletscode']]))
                    {
                        if ($loc_acc['accountrole'] != 'interlets')
                        {
                            $out .= $app['link']->link($app['r_users_show'], $app['pp_ary'],
                                ['edit' => $loc_acc['id']], 'rol',
                                [
                                    'class'	=> 'btn btn-warning btn-xs',
                                    'title'	=> 'De rol van het account moet van het type interSysteem zijn.',
                                ]);
                        }
                        else if (!in_array($loc_acc['status'], [1, 2, 7]))
                        {
                            $out .= $app['link']->link($app['r_users_show'], $app['pp_ary'],
                                ['edit' => $loc_acc['id']], 'status',
                                [
                                    'class'	=> 'btn btn-warning btn-xs',
                                    'title'	=> 'De status van het account moet actief, uitstapper of extern zijn.',
                                ]);
                        }
                        else
                        {
                            $out .= $app['link']->link($app['r_users_show'], $app['pp_ary'],
                                ['id' => $loc_acc['id']], 'OK',
                                ['class' => 'btn btn-success btn-xs']);
                        }
                    }
                    else
                    {
                        $out .= $app['link']->link('users_add', $app['pp_ary'],
                            ['intersystem_code' => $loc_group['localletscode']],
                            'Creëer',
                            [
                                'class'	=> 'btn btn-default text-danger',
                                'title'	=> 'Creëer een interSysteem-account met gelijke Accunt Code en status extern.',
                            ]);
                    }
                }
                else
                {
                    $out .= '<i class="fa fa-times text-danger"></i>';
                }

                $out .= '</td>';
                $out .= '<td>';

                if (isset($rem_group_ary[$rem_origin]))
                {
                    $out .= '<span class="btn btn-success btn-xs">OK</span>';
                }
                else
                {
                    $out .= '<i class="fa fa-times text-danger"></i>';
                }

                $out .= '</td>';
                $out .= '<td>';

                if (isset($rem_account_ary[$rem_origin]))
                {
                    $rem_acc = $rem_account_ary[$rem_origin];

                    if ($rem_acc['accountrole'] != 'interlets')
                    {
                        $out .= '<span class="btn btn-warning btn-xs" title="De rol van het Account ';
                        $out .= 'moet van het type interSysteem zijn.">rol</span>';
                    }
                    else if (!in_array($rem_acc['status'], [1, 2, 7]))
                    {
                        $out .= '<span class="btn btn-warning btn-xs" title="De status van het Account ';
                        $out .= 'moet actief, uitstapper of extern zijn.">rol</span>';
                    }
                    else
                    {
                        $out .= '<span class="btn btn-success btn-xs">OK</span>';
                    }
                }
                else
                {
                    $out .= '<i class="fa fa-times text-danger"></i>';
                }

                $out .= '</td>';

                $out .= '</tr>';
            }
        }
        $out .= '</tbody>';
        $out .= '</table>';

        if ($unavailable_explain)
        {
            $out .= '<ul class="list-group">';
            $out .= '<li class="list-group-item danger"><span class="bg-danger">';
            $out .= 'Systemen gemarkeerd in Rood ';
            $out .= 'zijn niet beschikbaar voor ';
            $out .= 'interSysteem verbindingen.</span></li>';
            $out .= '</ul>';
        }

        $out .= '</div></div>';

        return $out;
    }
}
