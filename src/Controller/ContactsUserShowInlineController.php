<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;

class ContactsUserShowInlineController extends AbstractController
{
    public function contacts_user_show_inline(
        app $app,
        int $uid,
        Db $db
    ):Response
    {
        $s_owner = $app['s_id'] === $uid
            && !$app['pp_guest']
            && !$app['s_elas_guest']
            && $app['s_system_self'];

        $contacts = $db->fetchAll('select c.*, tc.abbrev
            from ' . $app['pp_schema'] . '.contact c, ' .
                $app['pp_schema'] . '.type_contact tc
            where c.id_type_contact = tc.id
                and c.id_user = ?', [$uid]);

        $app['assets']->add([
            'leaflet',
            'contacts_user_map.js',
        ]);

		$out = '<div class="row">';
		$out .= '<div class="col-md-12">';

		$out .= '<h3>';
		$out .= '<i class="fa fa-map-marker"></i>';
		$out .= ' Contactinfo van ';
		$out .= $app['account']->link($uid, $app['pp_ary']);
        $out .= ' ';

        if ($app['pp_admin'])
        {
            $out .= $link_render->link('users_contacts_add_admin', $app['pp_ary'],
                ['user_id' => $uid], 'Toevoegen', [
                'class'	=> 'btn btn-success',
            ], 'plus');
        }
        else if ($s_owner)
        {
            $out .= $link_render->link('users_contacts_add', $app['pp_ary'],
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

            if ($app['pp_admin'] || $s_owner)
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

                if (!$app['item_access']->is_visible_flag_public($c['flag_public']) && !$s_owner)
                {
                    $tr_c = '<span class="btn btn-default">verborgen</span>';
                    $tr[] = $tr_c;
                    $tr[] = $tr_c;
                }
                else if ($s_owner || $app['pp_admin'])
                {
                    if ($app['pp_admin'])
                    {
                        $tr_c = $link_render->link_no_attr('users_contacts_edit_admin', $app['pp_ary'],
                            ['contact_id' => $c['id'], 'user_id' => $uid], $c['value']);
                    }
                    else
                    {
                        $tr_c = $link_render->link_no_attr('users_contacts_edit', $app['pp_ary'],
                            ['contact_id' => $c['id']], $c['value']);
                    }

                    if ($c['abbrev'] == 'adr')
                    {
                        $app['distance']->set_to_geo($c['value']);

                        if (!$app['s_elas_guest'] && !$app['s_master'])
                        {
                            $tr_c .= $app['distance']->set_from_geo($app['s_id'], $app['s_schema'])
                                ->calc()
                                ->format_parenthesis();
                        }
                    }

                    $tr[] = $tr_c;

                    if (isset($c['comments']))
                    {
                        if ($app['pp_admin'])
                        {
                            $tr[] = $link_render->link_no_attr('users_contacts_edit_admin', $app['pp_ary'],
                                ['contact_id' => $c['id'], 'user_id' => $uid], $c['comments']);
                        }
                        else
                        {
                            $tr[] = $link_render->link_no_attr('users_contacts_edit', $app['pp_ary'],
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
                        $app['distance']->set_to_geo($c['value']);

                        if (!$app['s_elas_guest'] && !$app['s_master'])
                        {
                            $tr_c .= $app['distance']->set_from_geo($app['s_id'], $app['s_schema'])
                                ->calc()
                                ->format_parenthesis();
                        }
                    }

                    $tr[] = $tr_c;

                    $tr[] = htmlspecialchars($c['comments'] ?? '', ENT_QUOTES);
                }

                if ($app['pp_admin'] || $s_owner)
                {
                    $tr[] = $app['item_access']->get_label_flag_public($c['flag_public']);

                    if ($app['pp_admin'])
                    {
                        $tr[] = $link_render->link_fa('users_contacts_del_admin', $app['pp_ary'],
                            ['contact_id' => $c['id'], 'user_id' => $uid], 'Verwijderen',
                            ['class' => 'btn btn-danger'], 'times');
                    }
                    else
                    {
                        $tr[] = $link_render->link_fa('users_contacts_del', $app['pp_ary'],
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

            if ($app['distance']->has_map_data())
            {
                $out .= '<div class="panel-footer">';
                $out .= '<div class="user_map" id="map" data-markers="';
                $out .= $app['distance']->get_map_markers();
                $out .= '" ';
                $out .= 'data-token="';
                $out .= $app['mapbox_token'];
                $out .= '"></div>';
                $out .= '</div>';
            }
        }
        else
        {
            $out .= '<div class="panel panel-danger">';
            $out .= '<div class="panel-body">';
            $out .= '<p>Er is geen contactinfo voor ';
            $out .= $app['account']->str($uid, $app['pp_schema']);
            $out .= '.</p>';
        }

        $out .= '</div></div>';
        $out .= '</div></div>';

        return new Response($out);
    }
}
