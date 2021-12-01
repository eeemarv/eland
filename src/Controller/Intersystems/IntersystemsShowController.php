<?php declare(strict_types=1);

namespace App\Controller\Intersystems;

use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SystemsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class IntersystemsShowController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/intersystems/{id}',
        name: 'intersystems_show',
        methods: ['GET'],
        requirements: [
            'id'            => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'module'        => 'users',
            'sub_module'    => 'intersystem',
        ],
    )]

    public function __invoke(
        int $id,
        Db $db,
        AlertService $alert_service,
        LinkRender $link_render,
        ConfigService $config_service,
        SystemsService $systems_service,
        PageParamsService $pp
    ):Response
    {
        if (!$config_service->get_bool('intersystem.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Intersystem submodule (users) not enabled.');
        }

        $group = $db->fetchAssociative('select *
            from ' . $pp->schema() . '.letsgroups
            where id = ?', [$id]);

        if (!$group)
        {
            $alert_service->error('Systeem niet gevonden.');

            return $this->redirectToRoute('intersystems', $pp->ary());
        }

        if ($group['localletscode'] === '')
        {
            $user = false;
        }
        else
        {
            $user = $db->fetchAssociative('select *
                from ' . $pp->schema() . '.users
                where code = ?', [$group['localletscode']]);
        }

        $out = '<div class="panel panel-default printview">';
        $out .= '<div class="panel-heading">';

        $out .= '<dl class="dl-horizontal">';
        $out .= '<dt>Status</dt>';

        $group_schema = $systems_service->get_schema_from_legacy_eland_origin($group['url']);

        if ($group_schema)
        {
            $out .= '<dd><span class="btn btn-info">eLAND server</span>';

            if (!$config_service->get_bool('transactions.currency.timebased_en', $group_schema))
            {
                $out .= ' <span class="btn btn-danger">';
                $out .= '<i class="fa fa-exclamation-triangle"></i> ';
                $out .= 'Niet geconfigureerd als Tijdbank</span>';
            }

            if (!$config_service->get_bool('intersystem.enabled', $group_schema))
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
            $out .= '<dd><i><span data-status="';
            $out .= 'null">';
            $out .= 'Geen verbinding</span></i>';
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

        $out .= '<dt>Account Code</dt>';
        $out .= '<dd>';

        if ($user)
        {
            $out .= $link_render->link('users_show', $pp->ary(),
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
            if ($user['role'] != 'guest')
            {
                $out .= ' ';
                $out .= $link_render->link_fa('users_edit_admin', $pp->ary(),
                    ['id' => $user['id']], 'Rol!',
                    [
                        'class'	=> 'btn btn-danger',
                        'title'	=> 'Het interSysteem-account heeft een ongeldige rol. De rol moet van het type Gast zijn.',
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

        $out .= '<dt>URL</dt>';
        $out .= '<dd>';
        $out .= $group['url'];
        $out .= '</dd>';

        $out .= '</div></div>';

        $out .= IntersystemsController::get_schemas_groups(
            $db,
            $config_service,
            $systems_service,
            $pp,
            $link_render
        );

        return $this->render('intersystems/intersystems_show.html.twig', [
            'content'   => $out,
            'id'        => $id,
            'name'      => $group['groupname'],
        ]);
    }
}
