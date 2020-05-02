<?php declare(strict_types=1);

namespace App\Controller;

use App\Render\BtnTopRender;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\ConfigService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SystemsService;
use App\Service\VarRouteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Predis\Client as Predis;

class IntersystemsController extends AbstractController
{
    public function __invoke(
        Db $db,
        Predis $predis,
        BtnTopRender $btn_top_render,
        HeadingRender $heading_render,
        ConfigService $config_service,
        LinkRender $link_render,
        PageParamsService $pp,
        VarRouteService $vr,
        SystemsService $systems_service,
        MenuService $menu_service
    ):Response
    {
        $intersystems = $db->fetchAll('select *
            from ' . $pp->schema() . '.letsgroups');

        $codes = [];

        foreach ($intersystems as $key => $sys)
        {
            $sys_host = strtolower(parse_url($sys['url'], PHP_URL_HOST) ?? '');

            $codes[] = $sys['localletscode'];

            $sys_schema = $systems_service->get_schema_from_legacy_eland_origin($sys['url']);

            if ($sys_schema)
            {
                $intersystems[$key]['eland'] = true;
                $intersystems[$key]['schema'] = $sys_schema;

                $intersystems[$key]['user_count'] = $db->fetchColumn('select count(*)
                    from ' . $sys_schema . '.users
                    where status in (1, 2)');
            }
            else if ($sys['apimethod'] == 'internal')
            {
                $intersystems[$key]['user_count'] = $db->fetchColumn('select count(*)
                    from ' . $pp->schema() . '.users
                    where status in (1, 2)');
            }
            else
            {
                $intersystems[$key]['user_count'] = $predis->get($sys_host . '_active_user_count');
            }
        }

        $users_code = [];

        $intersystem_users = $db->executeQuery('select id, status, code, role
            from ' . $pp->schema() . '.users
            where code in (?)',
            [$codes],
            [Db::PARAM_INT_ARRAY]);

        foreach ($intersystem_users as $u)
        {
            $users_code[$u['code']] = [
                'id'			=> $u['id'],
                'status'		=> $u['status'],
                'role'	        => $u['role'],
            ];
        }

        $btn_top_render->add('intersystems_add', $pp->ary(),
            [], 'InterSysteem toevoegen');

        $heading_render->add('eLAND InterSysteem');
        $heading_render->fa('share-alt');

        $out = '<p>';
        $out .= 'Een eLAND interSysteem verbinding laat intertrading toe tussen ';
        $out .= 'je eigen Systeem en een ander Systeem op deze eLAND server.';
        $out .= 'Beide Systemen dienen hiervoor een munteenheid te hebben die gebaseerd is ';
        $out .= 'op tijd. Ze zijn dus Tijdbanken en dienen zo ';
        $out .= 'geconfigureerd te zijn (Zie Admin > Instellingen > Systeem). ';
        $out .= 'Wanneer je deze pagina kan zien is dit reeds het geval.';
        $out .= '</p>';

        if (count($intersystems))
        {
            $out .= '<div class="table-responsive border border-dark rounded mb-3">';
            $out .= '<table class="table table-bordered table-hover mb-0 ';
            $out .= 'table-striped footable bg-default">';
            $out .= '<thead>';
            $out .= '<tr>';
            $out .= '<th data-sort-initial="true">Account</th>';
            $out .= '<th>Systeem</th>';
            $out .= '<th data-hide="phone">leden</th>';
            $out .= '<th data-hide="phone, tablet" data-sort-ignore="true">api</th>';
            $out .= '</tr>';
            $out .= '</thead>';

            $out .= '<tbody>';

            foreach($intersystems as $sys)
            {
                $out .= '<tr>';
                $out .= '<td>';

                if (in_array($sys['apimethod'], ['elassoap', 'mail']))
                {
                    $user = $users_code[$sys['localletscode']] ?? [];

                    if ($user)
                    {
                        $out .= $link_render->link($vr->get('users_show'), $pp->ary(),
                            ['id' => $user['id']], $sys['localletscode'],
                            [
                                'class'	=> 'btn btn-default',
                                'title'	=> 'Ga naar het interSysteem account',
                            ]);

                        if (!in_array($user['status'], [1, 2, 7]))
                        {
                            $out .= ' ';
                            $out .= $link_render->link_fa($vr->get('users_show'), $pp->ary(),
                                ['id' => $user['id']], 'Status!',
                                [
                                    'class'	=> 'btn btn-danger',
                                    'title'	=> 'Het interSysteem-account heeft een ongeldige status. De status moet van het type extern, actief of uitstapper zijn.',
                                ],
                                'exclamation-triangle');
                        }
                        if ($user['role'] != 'guest')
                        {
                            $out .= ' ';
                            $out .= $link_render->link_fa($vr->get('users_show'), $pp->ary(),
                                ['id' => $user['id']], 'Rol!',
                                [
                                    'class'	=> 'btn btn-danger',
                                    'title'	=> 'Het interSysteem Account heeft een ongeldige rol. De rol moet van het type Gast zijn.',
                                ],
                                'fa-exclamation-triangle');
                        }
                    }
                    else
                    {
                        $out .= $sys['localletscode'];

                        if ($sys['apimethod'] !== 'internal' && !$user)
                        {
                            $out .= ' <span class="label label-danger" title="Er is geen account gevonden met deze code">';
                            $out .= '<i class="fa fa-exclamation-triangle"></i> Account</span>';
                        }
                    }
                }

                $out .= '</td>';

                $out .= '<td>';

                $out .= $link_render->link_no_attr('intersystems_show', $pp->ary(),
                    ['id' => $sys['id']], $sys['groupname']);

                if (isset($sys['eland']))
                {
                    $out .= ' <span class="btn btn-info" title="Dit Systeem bevindt zich op dezelfde eland-server">';
                    $out .= 'eLAND</span>';

                    if (!$config_service->get('template_lets', $sys['schema']))
                    {
                        $out .= ' <span class="label label-danger" ';
                        $out .= 'title="Dit Systeem is niet geconfigureerd als Tijdbank.">';
                        $out .= '<i class="fa fa-exclamation-triangle"></i> ';
                        $out .= 'geen Tijdbank</span>';
                    }

                    if (!$config_service->get('interlets_en', $sys['schema']))
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
                $out .= $sys['user_count'];
                $out .= '</td>';

                $out .= '<td>';
                $out .= $sys['apimethod'];
                $out .= '</td>';
                $out .= '</tr>';
            }

            $out .= '</tbody>';
            $out .= '</table>';
            $out .= '</div>';
        }
        else
        {
            $out .= '<div class="card bg-primary">';
            $out .= '<div class="card-body">';
            $out .= '<p>Er zijn nog geen interSysteem-verbindingen.</p>';
            $out .= '</div>';
            $out .= '</div>';
        }

/*
        $out .= self::get_schemas_groups(
            $db,
            $config_service,
            $systems_service,
            $pp,
            $vr,
            $link_render
        );
*/

        $menu_service->set('intersystems');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }

    public static function get_schemas_groups(
        Db $db,
        ConfigService $config_service,
        SystemsService $systems_service,
        PageParamsService $pp,
        VarRouteService $vr,
        LinkRender $link_render
    ):string
    {
        $out = '<div class="card bg-default">';
        $out .= '<div class="card-body">';
        $out .= '<h3>Een interSysteem Verbinding aanmaken met een ander Systeem op deze eLAND server.</h3>';
        $out .= '</div>';
        $out .= '<ul>';
        $out .= '<li> ';
        $out .= 'Contacteer altijd eerst vooraf de beheerders van het andere Systeem ';
        $out .= 'waarmee je een interSysteem verbinding wil opzetten. ';
        $out .= 'En verifiëer of zij ook een Tijdbank-Systeem hebben en of zij geïnteresseerd zijn.</li>';
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
        $out .= '<span class="btn btn-success">OK</span>-teken.</li>';
        $out .= '</ul>';

        $url_ary = [];

        foreach ($systems_service->get_schemas() as $sys_schema)
        {
            $url_ary[] = $systems_service->get_legacy_eland_origin($sys_schema);
        }

        $loc_group_ary = $loc_account_ary = [];
        $rem_group_ary =  $rem_account_ary = $group_user_count_ary = [];
        $loc_letscode_ary = [];

        $groups = $db->executeQuery('select localletscode, url, id
            from ' . $pp->schema() . '.letsgroups
            where url in (?)',
            [$url_ary],
            [Db::PARAM_STR_ARRAY]);

        foreach ($groups as $group)
        {
            $loc_letscode_ary[] = $group['localletscode'];
            $h = strtolower(parse_url($group['url'], PHP_URL_HOST));
            $loc_group_ary[$h] = $group;
        }

        $interlets_accounts = $db->executeQuery('select id, code, status, role
            from ' . $pp->schema() . '.users
            where code in (?)',
            [$loc_letscode_ary],
            [Db::PARAM_STR_ARRAY]);

        foreach ($interlets_accounts as $u)
        {
            $loc_account_ary[$u['code']] = $u;
        }

        foreach ($systems_service->get_schemas() as $rem_schema)
        {
            $rem_group = $db->fetchAssoc('select localletscode, url, id
                from ' . $rem_schema . '.letsgroups
                where url = ?', [$systems_service->get_legacy_eland_origin($rem_schema)]);

            $group_user_count_ary[$rem_schema] = $db->fetchColumn('select count(*)
                from ' . $rem_schema . '.users
                where status in (1, 2)');

            if ($rem_group)
            {
                $rem_origin = $systems_service->get_legacy_eland_origin($rem_schema);

                $rem_group_ary[$rem_origin] = $rem_group;

                if ($rem_group['localletscode'])
                {
                    $rem_account = $db->fetchAssoc('select id, code, status, role
                        from ' . $rem_schema . '.users where code = ?', [$rem_group['localletscode']]);

                    if ($rem_account)
                    {
                        $rem_account_ary[$rem_origin] = $rem_account;
                    }
                }
            }
        }

        $out .= '<div class="card-body">';
        $out .= '<h3>Systemen op deze eLAND server</h3>';
        $out .= '</div>';

        $out .= '<table class="table table-bordered ';
        $out .= 'table-hover table-striped footable bg-default">';
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

        foreach($systems_service->get_schemas() as $rem_schema)
        {
            $rem_origin = $systems_service->get_legacy_eland_origin($rem_schema);

            $out .= '<tr';

            if (!$config_service->get('template_lets', $rem_schema)
                || !$config_service->get('interlets_en', $rem_schema))
            {
                $out .= ' class="danger"';

                $unavailable_explain = true;
            }

            $out .= '>';

            $out .= '<td>';
            $out .= $config_service->get('systemname', $rem_schema);

            if (!$config_service->get('template_lets', $rem_schema))
            {
                $out .= ' <span class="label label-danger" ';
                $out .= 'title="Dit Systeem is niet ';
                $out .= 'geconfigureerd als Tijdbank.">';
                $out .= '<i class="fa fa-exclamation-triangle">';
                $out .= '</i></span>';
            }

            if (!$config_service->get('interlets_en', $rem_schema))
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

            if ($pp->schema() === $rem_schema)
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

                    $out .= $link_render->link('intersystems_show', $pp->ary(),
                        ['id' => $loc_group['id']], 'OK',
                        ['class'	=> 'btn btn-success']);
                }
                else
                {
                    if ($config_service->get('template_lets', $rem_schema)
                        && $config_service->get('interlets_en', $rem_schema))
                    {
                        $out .= $link_render->link('intersystems_add', $pp->ary(),
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
                        if ($loc_acc['role'] != 'guest')
                        {
                            $out .= $link_render->link($vr->get('users_show'), $pp->ary(),
                                ['id' => $loc_acc['id']], 'rol',
                                [
                                    'class'	=> 'btn btn-warning',
                                    'title'	=> 'De rol van het account moet van het type Gast zijn.',
                                ]);
                        }
                        else if (!in_array($loc_acc['status'], [1, 2, 7]))
                        {
                            $out .= $link_render->link($vr->get('users_show'), $pp->ary(),
                                ['id' => $loc_acc['id']], 'status',
                                [
                                    'class'	=> 'btn btn-warning',
                                    'title'	=> 'De status van het account moet actief, uitstapper of extern zijn.',
                                ]);
                        }
                        else
                        {
                            $out .= $link_render->link($vr->get('users_show'), $pp->ary(),
                                ['id' => $loc_acc['id']], 'OK',
                                ['class' => 'btn btn-success']);
                        }
                    }
                    else
                    {
                        $out .= $link_render->link('users_add', $pp->ary(),
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
                    $out .= '<span class="btn btn-success">OK</span>';
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

                    if ($rem_acc['role'] != 'guest')
                    {
                        $out .= '<span class="btn btn-warning" title="De rol van het Account ';
                        $out .= 'moet van het type Gast zijn.">rol</span>';
                    }
                    else if (!in_array($rem_acc['status'], [1, 2, 7]))
                    {
                        $out .= '<span class="btn btn-warning" title="De status van het Account ';
                        $out .= 'moet actief, uitstapper of extern zijn.">rol</span>';
                    }
                    else
                    {
                        $out .= '<span class="btn btn-success">OK</span>';
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
