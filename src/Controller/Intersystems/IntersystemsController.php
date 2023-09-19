<?php declare(strict_types=1);

namespace App\Controller\Intersystems;

use App\Render\LinkRender;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SystemsService;
use Doctrine\DBAL\ArrayParameterType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Redis;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class IntersystemsController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/intersystems',
        name: 'intersystems',
        methods: ['GET'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'module'        => 'users',
            'sub_module'    => 'intersystem',
        ],
    )]

    public function __invoke(
        Db $db,
        Redis $predis,
        ConfigService $config_service,
        LinkRender $link_render,
        PageParamsService $pp,
        SystemsService $systems_service
    ):Response
    {
        if (!$config_service->get_bool('intersystem.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Intersystem submodule (users) not enabled.');
        }

        $intersystems = $db->fetchAllAssociative('select *
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

                $intersystems[$key]['user_count'] = $db->fetchOne('select count(*)
                    from ' . $sys_schema . '.users
                    where is_active
                        and remote_schema is null
                        and remote_email is null', [], []);
            }
            else if ($sys['apimethod'] == 'internal')
            {
                $intersystems[$key]['user_count'] = $db->fetchOne('select count(*)
                    from ' . $pp->schema() . '.users
                    where is_active
                        and remote_schema is null
                        and remote_email is null', [], []);
            }
            else
            {
                $intersystems[$key]['user_count'] = $predis->get($sys_host . '_active_user_count') ?: 0;
            }
        }

        $users_code = [];

        $res = $db->executeQuery('select id, status, code, role
            from ' . $pp->schema() . '.users
            where code in (?)',
            [$codes],
            [ArrayParameterType::STRING]);

        while ($u = $res->fetchAssociative())
        {
            $users_code[$u['code']] = [
                'id'			=> $u['id'],
                'status'		=> $u['status'],
                'role'	        => $u['role'],
            ];
        }

        $out = '<p>';
        $out .= 'Een eLAND interSysteem verbinding laat intertrading toe tussen ';
        $out .= 'je eigen Systeem en een ander Systeem op deze eLAND server.';
        $out .= 'Beide Systemen dienen hiervoor een munteenheid te hebben die gebaseerd is ';
        $out .= 'op tijd.  (Zie Transacties > Lokaal admin menu > Munteenheid). ';
        $out .= 'Wanneer je deze pagina kan zien is dit reeds het geval.';
        $out .= '</p>';

        if (count($intersystems))
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

            foreach($intersystems as $sys)
            {
                $out .= '<tr>';
                $out .= '<td>';

                if (in_array($sys['apimethod'], ['elassoap', 'mail']))
                {
                    $user = $users_code[$sys['localletscode']] ?? [];

                    if ($user)
                    {
                        $out .= $link_render->link('users_show', $pp->ary(),
                            ['id' => $user['id']], $sys['localletscode'],
                            [
                                'class'	=> 'btn btn-default',
                                'title'	=> 'Ga naar het interSysteem account',
                            ]);

                        if (!in_array($user['status'], [1, 2, 7]))
                        {
                            $out .= ' ';
                            $out .= $link_render->link_fa('users_show', $pp->ary(),
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
                            $out .= $link_render->link_fa('users_show', $pp->ary(),
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

                    if (!$config_service->get_bool('transactions.currency.timebased_en', $sys['schema']))
                    {
                        $out .= ' <span class="label label-danger" ';
                        $out .= 'title="Dit Systeem is niet geconfigureerd als Tijdbank.">';
                        $out .= '<i class="fa fa-exclamation-triangle"></i> ';
                        $out .= 'geen Tijdbank</span>';
                    }

                    if (!$config_service->get_bool('intersystem.enabled', $sys['schema']))
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
            $out .= '</div></div>';
        }
        else
        {
            $out .= '<div class="panel panel-primary">';
            $out .= '<div class="panel-heading">';
            $out .= '<p>Er zijn nog geen interSysteem-verbindingen.</p>';
            $out .= '</div></div>';
        }

        $out .= self::get_schemas_groups(
            $db,
            $config_service,
            $systems_service,
            $pp,
            $link_render
        );

        return $this->render('intersystems/intersystems.html.twig', [
            'content'   => $out,
        ]);
    }

    public static function get_schemas_groups(
        Db $db,
        ConfigService $config_service,
        SystemsService $systems_service,
        PageParamsService $pp,
        LinkRender $link_render
    ):string
    {

        $out = '<div class="panel panel-default">';
        $out .= '<div class="panel-heading">';
        $out .= '<h3>Een interSysteem Verbinding aanmaken met een ander Systeem op deze eLAND server.</h3>';
        $out .= '</div>';
        $out .= '<ul>';
        $out .= '<li> ';
        $out .= 'Contacteer altijd eerst vooraf de beheerders van het andere Systeem ';
        $out .= 'waarmee je een interSysteem verbinding wil opzetten. ';
        $out .= 'En verifiëer of zij ook een munteenheid gebaseerd ';
        $out .= 'op tijd hebben en of zij geïnteresseerd zijn.</li>';
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

        $loc_group_ary = [];
        $loc_account_ary = [];
        $rem_group_ary =  [];
        $rem_account_ary = [];
        $group_user_count_ary = [];
        $loc_letscode_ary = [];

        $this_origin = $systems_service->get_legacy_eland_origin($pp->schema());

        $res = $db->executeQuery('select localletscode, url, id
            from ' . $pp->schema() . '.letsgroups
            where url in (?)',
            [$url_ary],
            [ArrayParameterType::STRING]);

        while ($group = $res->fetchAssociative())
        {
            $loc_letscode_ary[] = $group['localletscode'];
            $h = strtolower(parse_url($group['url'], PHP_URL_HOST));
            $loc_group_ary['http://' . $h] = $group;
            $loc_group_ary['https://' . $h] = $group;
        }

        $res = $db->executeQuery('select id, code, status, role
            from ' . $pp->schema() . '.users
            where code in (?)',
            [$loc_letscode_ary],
            [ArrayParameterType::STRING]);

        while ($u = $res->fetchAssociative())
        {
            $loc_account_ary[$u['code']] = $u;
        }

        foreach ($systems_service->get_schemas() as $rem_schema)
        {
            $rem_group = $db->fetchAssociative('select localletscode, url, id
                from ' . $rem_schema . '.letsgroups
                where url = ?',
                [$this_origin],
                [\PDO::PARAM_STR]
            );

            $group_user_count_ary[$rem_schema] = $db->fetchOne('select count(*)
                from ' . $rem_schema . '.users
                where is_active
                    and remote_schema is null
                    and remote_email is null', [], []);

            if ($rem_group)
            {
                $rem_origin = $systems_service->get_legacy_eland_origin($rem_schema);

                $rem_group_ary[$rem_origin] = $rem_group;

                if ($rem_group['localletscode'])
                {
                    $rem_account = $db->fetchAssociative('select id, code, status, role
                        from ' . $rem_schema . '.users where code = ?',
                        [$rem_group['localletscode']],
                        [\PDO::PARAM_STR]
                    );

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

        foreach($systems_service->get_schemas() as $rem_schema)
        {
            $rem_origin = $systems_service->get_legacy_eland_origin($rem_schema);

            $out .= '<tr';

            if (!$config_service->get_intersystem_en($rem_schema))
            {
                $out .= ' class="danger"';

                $unavailable_explain = true;
            }

            $out .= '>';

            $out .= '<td>';
            $out .= $config_service->get_str('system.name', $rem_schema);

            if (!$config_service->get_bool('transactions.currency.timebased_en', $rem_schema))
            {
                $out .= ' <span class="label label-danger" ';
                $out .= 'title="Dit Systeem is niet ';
                $out .= 'geconfigureerd met munt met tijdbasis.">';
                $out .= '<i class="fa fa-exclamation-triangle">';
                $out .= '</i></span>';
            }

            if (!$config_service->get_bool('intersystem.enabled', $rem_schema))
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
                    if ($config_service->get_intersystem_en($rem_schema))
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

                    if (isset($loc_account_ary[$loc_group['localletscode']])
                        && is_array($loc_account_ary[$loc_group['localletscode']]))
                    {
                        $loc_acc = $loc_account_ary[$loc_group['localletscode']];

                        if ($loc_acc['role'] != 'guest')
                        {
                            $out .= $link_render->link('users_show', $pp->ary(),
                                ['id' => $loc_acc['id']], 'rol',
                                [
                                    'class'	=> 'btn btn-warning',
                                    'title'	=> 'De rol van het account moet van het type Gast zijn.',
                                ]);
                        }
                        else if (!in_array($loc_acc['status'], [1, 2, 7]))
                        {
                            $out .= $link_render->link('users_show', $pp->ary(),
                                ['id' => $loc_acc['id']], 'status',
                                [
                                    'class'	=> 'btn btn-warning',
                                    'title'	=> 'De status van het account moet actief, uitstapper of extern zijn.',
                                ]);
                        }
                        else
                        {
                            $out .= $link_render->link('users_show', $pp->ary(),
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
