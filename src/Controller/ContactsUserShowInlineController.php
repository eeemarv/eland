<?php declare(strict_types=1);

namespace App\Controller;

use App\Render\AccountRender;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use App\Render\LinkRender;
use App\Service\AssetsService;
use App\Service\DistanceService;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;

class ContactsUserShowInlineController extends AbstractController
{
    public function contacts_user_show_inline(
        int $uid,
        Db $db,
        AssetsService $assets_service,
        ItemAccessService $item_access_service,
        LinkRender $link_render,
        PageParamsService $pp,
        SessionUserService $su,
        DistanceService $distance_service,
        AccountRender $account_render,
        string $env_mapbox_token
    ):Response
    {
        $s_owner = $su->id() === $uid
            && !$pp->is_guest()
            && !$su->is_elas_guest()
            && $su->is_system_self();

        $contacts = $db->fetchAll('select c.*, tc.abbrev
            from ' . $pp->schema() . '.contact c, ' .
                $pp->schema() . '.type_contact tc
            where c.id_type_contact = tc.id
                and c.id_user = ?', [$uid]);

        $assets_service->add([
            'leaflet',
            'contacts_user_map.js',
        ]);

		$out = '<div class="row">';
		$out .= '<div class="col-md-12">';

		$out .= '<h3>';
		$out .= '<i class="fa fa-map-marker"></i>';
		$out .= ' Contactinfo van ';
		$out .= $account_render->link($uid, $pp->ary());
        $out .= ' ';

        if ($pp->is_admin())
        {
            $out .= $link_render->link('users_contacts_add_admin', $pp->ary(),
                ['user_id' => $uid], 'Toevoegen', [
                'class'	=> 'btn btn-success',
            ], 'plus');
        }
        else if ($s_owner)
        {
            $out .= $link_render->link('users_contacts_add', $pp->ary(),
                [], 'Toevoegen', [
                    'class'	=> 'btn btn-success',
                ], 'plus');
        }

		$out .= '</h3>';

        if (count($contacts))
        {
            $out .= '<div class="panel panel-danger">';
            $out .= '<div class="table-responsive">';
            $out .= '<table class="table table-hover ';
            $out .= 'table-striped table-bordered footable" ';
            $out .= 'data-sort="false">';

            $out .= '<thead>';
            $out .= '<tr>';

            $out .= '<th>Type</th>';
            $out .= '<th>Waarde</th>';
            $out .= '<th data-hide="phone, tablet">Commentaar</th>';

            if ($pp->is_admin() || $s_owner)
            {
                $out .= '<th data-hide="phone, tablet">Zichtbaarheid</th>';
                $out .= '<th data-sort-ignore="true" ';
                $out .= 'data-hide="phone, tablet">Verwijderen</th>';
            }

            $out .= '</tr>';
            $out .= '</thead>';

            $out .= '<tbody>';

            foreach ($contacts as $c)
            {
                $tr = [];

                $tr[] = $c['abbrev'];

                if (!$item_access_service->is_visible_flag_public($c['flag_public']) && !$s_owner)
                {
                    $tr_c = '<span class="btn btn-default">verborgen</span>';
                    $tr[] = $tr_c;
                    $tr[] = $tr_c;
                }
                else if ($s_owner || $pp->is_admin())
                {
                    if ($pp->is_admin())
                    {
                        $tr_c = $link_render->link_no_attr('users_contacts_edit_admin', $pp->ary(),
                            ['contact_id' => $c['id'], 'user_id' => $uid], $c['value']);
                    }
                    else
                    {
                        $tr_c = $link_render->link_no_attr('users_contacts_edit', $pp->ary(),
                            ['contact_id' => $c['id']], $c['value']);
                    }

                    if ($c['abbrev'] == 'adr')
                    {
                        $distance_service->set_to_geo($c['value']);

                        if (!$su->is_elas_guest() && !$su->is_master())
                        {
                            $tr_c .= $distance_service->set_from_geo($su->id(), $su->schema())
                                ->calc()
                                ->format_parenthesis();
                        }
                    }

                    $tr[] = $tr_c;

                    if (isset($c['comments']))
                    {
                        if ($pp->is_admin())
                        {
                            $tr[] = $link_render->link_no_attr('users_contacts_edit_admin', $pp->ary(),
                                ['contact_id' => $c['id'], 'user_id' => $uid], $c['comments']);
                        }
                        else
                        {
                            $tr[] = $link_render->link_no_attr('users_contacts_edit', $pp->ary(),
                                ['contact_id' => $c['id']], $c['comments']);
                        }
                    }
                    else
                    {
                        $tr[] = '&nbsp;';
                    }
                }
                else if ($c['abbrev'] === 'mail')
                {
                    $tr[] = '<a href="mailto:' . $c['value'] . '">' .
                        $c['value'] . '</a>';

                    $tr[] = htmlspecialchars($c['comments'] ?? '', ENT_QUOTES);
                }
                else if ($c['abbrev'] === 'web')
                {
                    $tr[] = '<a href="' . $c['value'] . '">' .
                        $c['value'] .  '</a>';

                    $tr[] = htmlspecialchars($c['comments'] ?? '', ENT_QUOTES);
                }
                else
                {
                    $tr_c = htmlspecialchars($c['value'] ?? '', ENT_QUOTES);

                    if ($c['abbrev'] === 'adr')
                    {
                        $distance_service->set_to_geo($c['value']);

                        if (!$su->is_elas_guest() && !$su->is_master())
                        {
                            $tr_c .= $distance_service->set_from_geo($su->id(), $su->schema())
                                ->calc()
                                ->format_parenthesis();
                        }
                    }

                    $tr[] = $tr_c;

                    $tr[] = htmlspecialchars($c['comments'] ?? '', ENT_QUOTES);
                }

                if ($pp->is_admin() || $s_owner)
                {
                    $tr[] = $item_access_service->get_label_flag_public($c['flag_public']);

                    if ($pp->is_admin())
                    {
                        $tr[] = $link_render->link_fa('users_contacts_del_admin', $pp->ary(),
                            ['contact_id' => $c['id'], 'user_id' => $uid], 'Verwijderen',
                            ['class' => 'btn btn-danger'], 'times');
                    }
                    else
                    {
                        $tr[] = $link_render->link_fa('users_contacts_del', $pp->ary(),
                            ['contact_id' => $c['id']], 'Verwijderen',
                            ['class' => 'btn btn-danger'], 'times');
                    }
                }

                $out .= '<tr><td>';
                $out .= implode('</td><td>', $tr);
                $out .= '</td></tr>';
            }

            $out .= '</tbody>';
            $out .= '</table>';

            if ($distance_service->has_map_data())
            {
                $out .= '<div class="panel-footer">';
                $out .= '<div class="user_map" id="map" data-markers="';
                $out .= $distance_service->get_map_markers();
                $out .= '" ';
                $out .= 'data-token="';
                $out .= $env_mapbox_token;
                $out .= '"></div>';
                $out .= '</div>';
            }
        }
        else
        {
            $out .= '<div class="panel panel-danger">';
            $out .= '<div class="panel-body">';
            $out .= '<p>Er is geen contactinfo voor ';
            $out .= $account_render->str($uid, $pp->schema());
            $out .= '.</p>';
        }

        $out .= '</div></div>';
        $out .= '</div></div>';

        return new Response($out);
    }
}
