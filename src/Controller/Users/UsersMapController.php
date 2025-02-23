<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Cache\ConfigCache;
use App\Render\AccountRender;
use App\Render\LinkRender;
use App\Service\CacheService;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\VarRouteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class UsersMapController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/users/map/{status}',
        name: 'users_map',
        methods: ['GET'],
        priority: 20,
        requirements: [
            'status'        => '%assert.user_status%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.guest%',
        ],
        defaults: [
            'status'        => 'active',
            'module'        => 'users',
        ],
    )]

    public function __invoke(
        string $status,
        Db $db,
        AccountRender $account_render,
        CacheService $cache_service,
        ItemAccessService $item_access_service,
        LinkRender $link_render,
        ConfigCache $config_cache,
        PageParamsService $pp,
        SessionUserService $su,
        VarRouteService $vr,
        string $env_map_access_token,
        string $env_map_tiles_url
    ):Response
    {
        if (!$pp->is_admin()
            && !in_array($status, ['active', 'new', 'leaving', 'intersystem']))
        {
            throw new AccessDeniedHttpException('No access for this user status');
        }

        $ref_geo = [];

        $status_def_ary = UsersListController::get_status_def_ary(
            $config_cache,
            $item_access_service,
            $pp
        );

        $params = [
            'status'    => $status,
        ];

        $sql_map = [
            'where'     => [],
            'params'    => [],
            'types'     => [],
        ];

        $sql = [];
        $sql['common'] = $sql_map;
        $sql['common']['where'][] = '1 = 1';

        $sql['status'] = $sql_map;

        foreach ($status_def_ary[$status]['sql'] as $st_def_key => $def_sql_ary)
        {
            foreach ($def_sql_ary as $def_val)
            {
                if (is_array($def_val) && $st_def_key = 'where')
                {
                    $wh_or = '(';
                    $wh_or .= implode(' or ', $def_val);
                    $wh_or .= ')';
                    $sql['status'][$st_def_key][] = $wh_or;
                    continue;
                }

                $sql['status'][$st_def_key][] = $def_val;
            }
        }

        $sql_where = implode(' and ', array_merge(...array_column($sql, 'where')));
        $sql_params = array_merge(...array_column($sql, 'params'));
        $sql_types = array_merge(...array_column($sql, 'types'));

        $users = $db->fetchAllAssociative('select u.*
            from ' . $pp->schema() . '.users u
            where ' . $sql_where . '
            order by u.code asc',
            $sql_params,
            $sql_types);

        $adr_ary = [];

        $stmt = $db->prepare('select
                c.id, c.user_id as user_id, c.value, c.access
            from ' . $pp->schema() . '.contact c, ' .
                $pp->schema() . '.type_contact tc
            where tc.id = c.id_type_contact
                and tc.abbrev = \'adr\'');

        $res = $stmt->executeQuery();

        while ($row = $res->fetchAssociative())
        {
            $adr_ary[$row['user_id']] = $row;
        }

        if (!$su->is_master())
        {
            if ($pp->is_guest() && !$su->is_system_self())
            {
                $my_adr = $db->fetchOne('select c.value
                    from ' . $su->schema() . '.contact c, ' .
                        $su->schema() . '.type_contact tc
                    where c.user_id = ?
                        and c.id_type_contact = tc.id
                        and tc.abbrev = \'adr\'',
                        [$su->id()], [\PDO::PARAM_INT]);
            }
            else if (!$pp->is_guest())
            {
                if  (isset($adr_ary[$su->id()]))
                {
                    $my_adr = trim($adr_ary[$su->id()]['value']);
                }
            }

            if (isset($my_adr) && $my_adr)
            {
                $ref_geo = $cache_service->get('geo_' . $my_adr);
            }
        }

        $lat_add = $lng_add = 0;
        $data_users = $not_geocoded_ary = $not_present_ary = [];
        $hidden_count = $not_geocoded_count = $not_present_count = 0;

        foreach ($users as $user)
        {
            if (isset($adr_ary[$user['id']]))
            {
                $adr = $adr_ary[$user['id']];

                if ($item_access_service->is_visible($adr['access']))
                {
                    $geo = $cache_service->get('geo_' . $adr['value']);

                    if ($geo)
                    {
                        $data_users[$user['id']] = [
                            'link'      => $link_render->context_url(
                                'users_show',
                                $pp->ary(),
                                ['id' => $user['id']]),
                            'name'		=> $user['name'],
                            'code'	    => $user['code'],
                            'lat'		=> $geo['lat'],
                            'lng'		=> $geo['lng'],
                        ];

                        $lat_add += $geo['lat'];
                        $lng_add += $geo['lng'];

                        continue;
                    }
                    else
                    {
                        $not_geocoded_count++;
                        $not_geocoded_ary[] = $adr;
                    }
                }
                else
                {
                    $hidden_count++;
                }
            }
            else
            {
                $not_present_count++;
                $not_present_ary[] = $user['id'];
            }
        }

        $shown_count = count($data_users);
        $not_shown_count = $hidden_count + $not_present_count + $not_geocoded_count;
        $total_count = $shown_count + $not_shown_count;

        if (!count($ref_geo) && $shown_count)
        {
            $ref_geo['lat'] = $lat_add / $shown_count;
            $ref_geo['lng'] = $lng_add / $shown_count;
        }

        $data_map = json_encode([
            'users'     => $data_users,
            'lat'       => $ref_geo['lat'] ?? '',
            'lng'       => $ref_geo['lng'] ?? '',
            'token'     => $env_map_access_token,
            'tiles_url' => $env_map_tiles_url,
        ]);

        $out = UsersListController::get_tab_selector(
            $params,
            $link_render,
            $item_access_service,
            $config_cache,
            $pp,
            $vr
        );

        $out .= '<div class="row">';
        $out .= '<div class="col-md-12">';
        $out .= '<div class="users_map" id="map" ';
        $out .= 'data-map="';
        $out .= htmlspecialchars($data_map);
        $out .= '"></div>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="panel panel-default">';
        $out .= '<div class="panel-heading">';
        $out .= '<p>';

        $out .= 'In dit kaartje wordt van elke gebruiker slechts het eerste ';
        $out .= 'adres in de contacten getoond. ';

        $out .= '</p>';

        if ($not_shown_count > 0)
        {
            $out .= '<p>';
            $out .= 'Van in totaal ' . $total_count;
            $out .= ' gebruikers worden ';
            $out .= $not_shown_count;
            $out .= ' adressen niet getoond wegens: ';
            $out .= '<ul>';

            if ($hidden_count)
            {
                $out .= '<li>';
                $out .= '<strong>';
                $out .= $hidden_count;
                $out .= '</strong> ';
                $out .= 'verborgen adres';
                $out .= '</li>';
            }

            if ($not_present_count)
            {
                $out .= '<li>';
                $out .= '<strong>';
                $out .= $not_present_count;
                $out .= '</strong> ';
                $out .= 'geen adres gekend';
                $out .= '</li>';
            }

            if ($not_geocoded_count)
            {
                $out .= '<li>';
                $out .= '<strong>';
                $out .= $not_geocoded_count;
                $out .= '</strong> ';
                $out .= 'coordinaten niet gekend.';
                $out .= '</li>';
            }

            $out .= '</ul>';
            $out .= '</p>';

            if ($not_geocoded_count)
            {
                $out .= '<h4>';
                $out .= 'Coördinaten niet gekend';
                $out .= '</h4>';
                $out .= '<p>';
                $out .= 'Wanneer een adres aangepast is of net toegevoegd, ';
                $out .= 'duurt het enige tijd eer de coordinaten zijn ';
                $out .= 'opgezocht door de software ';
                $out .= '(maximum één dag). ';
                $out .= 'Het kan ook dat bepaalde adressen niet vertaalbaar zijn door ';
                $out .= 'de "geocoding service".';
                $out .= '</p>';

                if ($pp->is_admin())
                {
                    $out .= '<p>';
                    $out .= 'Hieronder de adressen die nog niet ';
                    $out .= 'vertaald zijn in coördinaten: ';
                    $out .= '<ul>';

                    foreach($not_geocoded_ary as $not_geocoded)
                    {
                        $out .= '<li>';

                        $out .= $link_render->link_no_attr('users_contacts_edit', $pp->ary(),
                            ['contact_id' => $not_geocoded['id'], 'user_id' => $not_geocoded['user_id']],
                            $not_geocoded['value']);

                        $out .= ' gebruiker: ';
                        $out .= $account_render->link($not_geocoded['user_id'], $pp->ary());
                        $out .= '</li>';
                    }

                    $out .= '</ul>';
                    $out .= '</p>';
                }
            }

            if ($pp->is_admin() && $not_present_count)
            {
                $out .= '<h4>';
                $out .= 'Gebruikers zonder adres';
                $out .= '</h4>';

                $out .= '<p>';
                $out .= '<ul>';

                foreach ($not_present_ary as $not_present_addres_uid)
                {
                    $out .= '<li>';
                    $out .= $account_render->link($not_present_addres_uid, $pp->ary());
                    $out .= '</li>';
                }

                $out .= '</ul>';
                $out .= '</p>';
            }
        }

        $out .= '</div>';
        $out .= '</div>';

        return $this->render('users/users_map.html.twig', [
            'content'   => $out,
        ]);
    }
}
