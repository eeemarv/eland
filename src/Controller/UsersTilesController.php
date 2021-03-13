<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Controller\UsersListController;
use App\Cnst\StatusCnst;
use App\Render\BtnNavRender;
use App\Render\BtnTopRender;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\AssetsService;
use App\Service\ConfigService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\VarRouteService;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;

class UsersTilesController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/users/tiles/{status}',
        name: 'users_tiles',
        methods: ['GET'],
        priority: 30,
        requirements: [
            'status'        => '%assert.account_status%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.guest%',
        ],
        defaults: [
            'status'        => 'active',
            'module'        => 'users',
        ],
    )]

    public function __invoke(
        Request $request,
        string $status,
        Db $db,
        HeadingRender $heading_render,
        BtnNavRender $btn_nav_render,
        BtnTopRender $btn_top_render,
        AssetsService $assets_service,
        LinkRender $link_render,
        ConfigService $config_service,
        PageParamsService $pp,
        VarRouteService $vr,
        MenuService $menu_service,
        string $env_s3_url
    ):Response
    {
        if (!$pp->is_admin() && !in_array($status, ['active', 'new', 'leaving']))
        {
            throw new AccessDeniedHttpException('No access for status: ' . $status);
        }

        $postcode_enabled = $config_service->get_bool('users.fields.postcode.enabled', $pp->schema());

        $new_user_treshold = $config_service->get_new_user_treshold($pp->schema());

        $q = $request->get('q', '');

        $params = ['status'	=> $status];

        $status_def_ary = UsersListController::get_status_def_ary($config_service, $pp);

        $sql = [
            'where'     => [],
            'params'    => [],
            'types'     => [],
        ];

        foreach ($status_def_ary[$status]['sql'] as $st_def_key => $def_sql_ary)
        {
            foreach ($def_sql_ary as $def_val)
            {
                $sql[$st_def_key][] = $def_val;
            }
        }

        $sql_where = ' and ' . implode(' and ', $sql['where']);

        $users = $db->fetchAllAssociative('select u.*
            from ' . $pp->schema() . '.users u
            where 1 = 1 ' . $sql_where . '
            order by u.code asc',
            $sql['params'], $sql['types']);

        $assets_service->add(['isotope', 'users_tiles.js']);

        if ($pp->is_admin())
        {
            $btn_top_render->add('users_add', $pp->ary(),
                [], 'Gebruiker toevoegen');
        }

        UsersListController::btn_nav($btn_nav_render, $pp->ary(), $params, 'users_tiles');
        UsersListController::heading($heading_render);

        $out = UsersListController::get_filter_and_tab_selector(
            $params,
            '',
            $q,
            $link_render,
            $config_service,
            $pp,
            $vr
        );

        $out .= '<p>';
        $out .= '<span class="btn-group sort-by" role="group">';
        $out .= '<button class="btn btn-default active" data-sort-by="code">';
        $out .= 'Account Code ';
        $out .= '<i class="fa fa-sort-asc"></i></button>';
        $out .= '<button class="btn btn-default" data-sort-by="name">';
        $out .= 'Naam ';
        $out .= '<i class="fa fa-sort"></i></button>';

        if ($postcode_enabled)
        {
            $out .= '<button class="btn btn-default" data-sort-by="postcode">';
            $out .= 'Postcode ';
            $out .= '<i class="fa fa-sort"></i></button>';
        }

        $out .= '</span>';
        $out .= '</p>';

        $out .= '<div class="row tiles">';

        foreach ($users as $u)
        {
            $row_stat = $u['status'];

            if (isset($u['adate'])
                && $u['status'] == 1
                && $new_user_treshold->getTimestamp() < strtotime($u['adate'] . ' UTC'))
            {
                $row_stat = 3;
            }

            $url = $link_render->context_path('users_show', $pp->ary(),
                ['id' => $u['id'], 'link' => $status]);

            $out .= '<div class="col-xs-4 col-md-3 col-lg-2 tile">';
            $out .= '<div';

            if (isset(StatusCnst::CLASS_ARY[$row_stat]))
            {
                $out .= ' class="bg-';
                $out .= StatusCnst::CLASS_ARY[$row_stat];
                $out .= '"';
            }

            $out .= '>';
            $out .= '<div class="thumbnail text-center">';
            $out .= '<a href="' . $url . '">';

            if (isset($u['image_file']) && $u['image_file'] != '')
            {
                $out .= '<img src="';
                $out .= $env_s3_url . $u['image_file'];
                $out .= '" class="img-rounded">';
            }
            else
            {
                $out .= '<div><i class="fa fa-user fa-5x text-muted"></i></div>';
            }
            $out .= '</a>';

            $out .= '<div class="caption">';

            $out .= '<a href="' . $url . '">';
            $out .= '<span class="code">' . $u['code'] . '</span> ';
            $out .= '<span class="name">' . $u['name'] . '</span>';
            $out .= '</a>';

            if ($postcode_enabled)
            {
                $out .= '<br><span class="postcode">' . $u['postcode'] . '</span>';
            }

            $out .= '</div>';
            $out .= '</div>';
            $out .= '</div>';
            $out .= '</div>';
        }

        $out .= '</div>';

        $menu_service->set('users');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
