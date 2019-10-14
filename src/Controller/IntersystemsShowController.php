<?php declare(strict_types=1);

namespace App\Controller;

use App\Render\BtnNavRender;
use App\Render\BtnTopRender;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\AssetsService;
use App\Service\ConfigService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SystemsService;
use App\Service\VarRouteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;

class IntersystemsShowController extends AbstractController
{
    public function __invoke(
        int $id,
        Db $db,
        AlertService $alert_service,
        LinkRender $link_render,
        ConfigService $config_service,
        AssetsService $assets_service,
        BtnNavRender $btn_nav_render,
        BtnTopRender $btn_top_render,
        HeadingRender $heading_render,
        SystemsService $systems_service,
        PageParamsService $pp,
        VarRouteService $vr,
        MenuService $menu_service
    ):Response
    {
        $group = $db->fetchAssoc('select *
            from ' . $pp->schema() . '.letsgroups
            where id = ?', [$id]);

        if (!$group)
        {
            $alert_service->error('Systeem niet gevonden.');
            $link_render->redirect('intersystems', $pp->ary(), []);
        }

        if ($group['localletscode'] === '')
        {
            $user = false;
        }
        else
        {
            $user = $db->fetchAssoc('select *
                from ' . $pp->schema() . '.users
                where letscode = ?', [$group['localletscode']]);
        }

        $btn_top_render->edit('intersystems_edit', $pp->ary(),
            ['id' => $id], 'Intersysteem aanpassen');

        $btn_top_render->del('intersystems_del', $pp->ary(),
            ['id' => $id], 'Intersysteem verwijderen');

        $btn_nav_render->nav_list('intersystems', $pp->ary(),
            [], 'Lijst', 'share-alt');

        $assets_service->add(['elas_soap_status.js']);

        $heading_render->add('InterSysteem: ');
        $heading_render->add($group['groupname']);
        $heading_render->fa('share-alt');

        $out = '<div class="panel panel-default printview">';
        $out .= '<div class="panel-heading">';

        $out .= '<dl class="dl-horizontal">';
        $out .= '<dt>Status</dt>';

        $group_schema = $systems_service->get_schema_from_legacy_eland_origin($group['url']);

        if ($group_schema)
        {
            $out .= '<dd><span class="btn btn-info">eLAND server</span>';

            if (!$config_service->get('template_lets', $group_schema))
            {
                $out .= ' <span class="btn btn-danger">';
                $out .= '<i class="fa fa-exclamation-triangle"></i> ';
                $out .= 'Niet geconfigureerd als Tijdsbank</span>';
            }

            if (!$config_service->get('interlets_en', $group_schema))
            {
                $out .= ' <span class="btn btn-danger">';
                $out .= '<i class="fa fa-exclamation-triangle"></i> ';
                $out .= 'De InterSysteem-mogelijkheid is niet ingeschakeld ';
                $out .= 'in configuratie</span>';
            }

            $out .= '</dd>';
        }
        else
        {
            $out .= '<dd><i><span data-elas-soap-status="';

            $out .= htmlspecialchars($link_render->context_path('elas_soap_status',
                $pp->ary(), ['group_id' => $group['id']]));

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
            $out .= $link_render->link('users_show_admin', $pp->ary(),
                ['id' => $user['id']], $group['localletscode'],
                [
                    'class' => 'btn btn-default',
                    'title'	=> 'Ga naar het interSysteem account',
                ]);

            if (!in_array($user['status'], [1, 2, 7]))
            {
                $out .= ' ';

                $out .= $link_render->link_fa('users_edit_admin', $pp->ary(),
                    ['id' => $user['id']], 'Status!',
                    [
                        'class'	=> 'btn btn-danger',
                        'title'	=> 'Het interSysteem-account heeft een ongeldige status. De status moet van het type extern, actief of uitstapper zijn.',
                    ],
                    'exclamation-triangle');
            }
            if ($user['accountrole'] != 'interlets')
            {
                $out .= ' ';
                $out .= $link_render->link_fa('users_edit_admin', $pp->ary(),
                    ['id' => $user['id']], 'Rol!',
                    [
                        'class'	=> 'btn btn-danger',
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

        $out .= IntersystemsController::get_schemas_groups(
            $db,
            $config_service,
            $systems_service,
            $pp,
            $vr,
            $link_render
        );

        $menu_service->set('intersystems');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
