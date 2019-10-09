<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Controller\UsersListController;
use Doctrine\DBAL\Connection as Db;

class UsersMapController extends AbstractController
{
    public function users_map_admin(
        Request $request,
        app $app,
        string $status,
        Db $db
    ):Response
    {
        return $this->users_map($request, $app, $status, $db);
    }

    public function users_map(
        Request $request,
        app $app,
        string $status,
        Db $db
    ):Response
    {
        $ref_geo = [];
        $params = ['status' => $status];

        $status_def_ary = UsersListController::get_status_def_ary($app['pp_admin'], $app['new_user_treshold']);

        $sql_bind = [];

        if (isset($status_def_ary[$status]['sql_bind']))
        {
            $sql_bind[] = $status_def_ary[$status]['sql_bind'];
        }

        $users = $db->fetchAll('select u.*
            from ' . $app['pp_schema'] . '.users u
            where ' . $status_def_ary[$status]['sql'] . '
            order by u.letscode asc', $sql_bind);

        $adr_ary = [];

        $rs = $db->prepare('select
                c.id, c.id_user as user_id, c.value, c.flag_public
            from ' . $app['pp_schema'] . '.contact c, ' .
                $app['pp_schema'] . '.type_contact tc
            where tc.id = c.id_type_contact
                and tc.abbrev = \'adr\'');

        $rs->execute();

        while ($row = $rs->fetch())
        {
            $adr_ary[$row['user_id']] = $row;
        }

        if (!$app['s_master'])
        {
            if ($app['pp_guest'] && $app['s_schema'] && !$app['s_elas_guest'])
            {
                $my_adr = $db->fetchColumn('select c.value
                    from ' . $app['s_schema'] . '.contact c, ' . $app['s_schema'] . '.type_contact tc
                    where c.id_user = ?
                        and c.id_type_contact = tc.id
                        and tc.abbrev = \'adr\'', [$app['s_id']]);
            }
            else if (!$app['pp_guest'])
            {
                $my_adr = trim($adr_ary[$app['s_id']]['value']);
            }

            if (isset($my_adr) && $my_adr)
            {
                $ref_geo = $this->cache_service->get('geo_' . $my_adr);
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

                if ($item_access_service->is_visible_flag_public($adr['flag_public']))
                {
                    $geo = $this->cache_service->get('geo_' . $adr['value']);

                    if ($geo)
                    {
                        $data_users[$user['id']] = [
                            'link'      => $link_render->context_url(
                                $app['r_users_show'],
                                $app['pp_ary'],
                                ['id' => $user['id']]),
                            'name'		=> $user['name'],
                            'code'	    => $user['letscode'],
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

        $app['assets']->add(['leaflet', 'users_map.js']);

        if ($app['pp_admin'])
        {
            $btn_top_render->add('users_add', $app['pp_ary'],
                [], 'Gebruiker toevoegen');
        }

        users_list::btn_nav($btn_nav_render, $app['pp_ary'], $params, 'users_map');
        users_list::heading($heading_render);

        $data_map = json_encode([
            'users' => $data_users,
            'lat'   => $ref_geo['lat'] ?? '',
            'lng'   => $ref_geo['lng'] ?? '',
            'token' => $app['mapbox_token'],
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

                if ($app['pp_admin'])
                {
                    $out .= '<p>';
                    $out .= 'Hieronder de adressen die nog niet ';
                    $out .= 'vertaald zijn in coördinaten: ';
                    $out .= '<ul>';

                    foreach($not_geocoded_ary as $not_geocoded)
                    {
                        $out .= '<li>';

                        $out .= $link_render->link_no_attr('users_contacts_edit_admin', $app['pp_ary'],
                            ['contact_id' => $not_geocoded['id'], 'user_id' => $not_geocoded['user_id']],
                            $not_geocoded['value']);

                        $out .= ' gebruiker: ';
                        $out .= $app['account']->link($not_geocoded['user_id'], $app['pp_ary']);
                        $out .= '</li>';
                    }

                    $out .= '</ul>';
                    $out .= '</p>';
                }
            }

            if ($app['pp_admin'] && $not_present_count)
            {
                $out .= '<h4>';
                $out .= 'Gebruikers zonder adres';
                $out .= '</h4>';

                $out .= '<p>';
                $out .= '<ul>';

                foreach ($not_present_ary as $not_present_addres_uid)
                {
                    $out .= '<li>';
                    $out .= $app['account']->link($not_present_addres_uid, $app['pp_ary']);
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
            'schema'    => $app['pp_schema'],
        ]);
    }
}
