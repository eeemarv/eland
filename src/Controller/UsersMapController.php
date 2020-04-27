<?php declare(strict_types=1);

namespace App\Controller;

use App\Render\AccountRender;
use App\Render\BtnNavRender;
use App\Render\BtnTopRender;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\AssetsService;
use App\Service\CacheService;
use App\Service\ConfigService;
use App\Service\ItemAccessService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\VarRouteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;

class UsersMapController extends AbstractController
{
    public function __invoke(
        string $status,
        Db $db,
        AccountRender $account_render,
        AssetsService $assets_service,
        BtnNavRender $btn_nav_render,
        BtnTopRender $btn_top_render,
        HeadingRender $heading_render,
        CacheService $cache_service,
        ItemAccessService $item_access_service,
        LinkRender $link_render,
        ConfigService $config_service,
        PageParamsService $pp,
        SessionUserService $su,
        VarRouteService $vr,
        MenuService $menu_service,
        string $env_map_access_token,
        string $env_map_tiles_url
    ):Response
    {
        $ref_geo = [];
        $params = ['status' => $status];

        $status_def_ary = UsersListController::get_status_def_ary(
            $config_service, $pp
        );

        $sql_bind = [];

        if (isset($status_def_ary[$status]['sql_bind']))
        {
            $sql_bind[] = $status_def_ary[$status]['sql_bind'];
        }

        $users = $db->fetchAll('select u.*
            from ' . $pp->schema() . '.users u
            where ' . $status_def_ary[$status]['sql'] . '
            order by u.code asc', $sql_bind);

        $adr_ary = [];

        $rs = $db->prepare('select
                c.id, c.id_user as user_id, c.value, c.access
            from ' . $pp->schema() . '.contact c, ' .
                $pp->schema() . '.type_contact tc
            where tc.id = c.id_type_contact
                and tc.abbrev = \'adr\'');

        $rs->execute();

        while ($row = $rs->fetch())
        {
            $adr_ary[$row['user_id']] = $row;
        }

        if (!$su->is_master())
        {
            if ($pp->is_guest() && !$su->is_system_self())
            {
                $my_adr = $db->fetchColumn('select c.value
                    from ' . $su->schema() . '.contact c, ' .
                        $su->schema() . '.type_contact tc
                    where c.id_user = ?
                        and c.id_type_contact = tc.id
                        and tc.abbrev = \'adr\'', [$su->id()]);
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
                                $vr->get('users_show'),
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

        $assets_service->add(['leaflet', 'users_map.js']);

        if ($pp->is_admin())
        {
            $btn_top_render->add('users_add', $pp->ary(),
                [], 'Gebruiker toevoegen');
        }

        UsersListController::btn_nav($btn_nav_render, $pp->ary(), $params, 'users_map');
        UsersListController::heading($heading_render);

        $data_map = json_encode([
            'users'     => $data_users,
            'lat'       => $ref_geo['lat'] ?? '',
            'lng'       => $ref_geo['lng'] ?? '',
            'token'     => $env_map_access_token,
            'tiles_url' => $env_map_tiles_url,
        ]);

        $out = '<div class="row">';
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

                        $out .= $link_render->link_no_attr('users_contacts_edit_admin', $pp->ary(),
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

        $menu_service->set('users');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
