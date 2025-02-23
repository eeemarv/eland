<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Cache\ConfigCache;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Form\Type\Filter\QTextSearchFilterType;
use App\Render\LinkRender;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use App\Service\VarRouteService;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class UsersTilesController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/users/tiles/{status}',
        name: 'users_tiles',
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
        Request $request,
        string $status,
        Db $db,
        LinkRender $link_render,
        ItemAccessService $item_access_service,
        ConfigCache $config_cache,
        PageParamsService $pp,
        VarRouteService $vr,
        string $env_s3_url
    ):Response
    {
        if (!$pp->is_admin() && !in_array($status, ['active', 'new', 'leaving', 'intersystem']))
        {
            throw new AccessDeniedHttpException('No access for status: ' . $status);
        }

        $postcode_enabled = $config_cache->get_bool('users.fields.postcode.enabled', $pp->schema());
        $new_users_enabled = $config_cache->get_bool('users.new.enabled', $pp->schema());
        $leaving_users_enabled = $config_cache->get_bool('users.leaving.enabled', $pp->schema());

        $new_user_treshold = $config_cache->get_new_user_treshold($pp->schema());

        $show_new_list = $new_users_enabled;

        if ($show_new_list)
        {
            $new_users_access_list = $config_cache->get_str('users.new.access_list', $pp->schema());
            $show_new_list = $item_access_service->is_visible($new_users_access_list);
        }

        $show_leaving_list = $leaving_users_enabled;

        if ($show_leaving_list)
        {
            $leaving_users_access_list = $config_cache->get_str('users.leaving.access_list', $pp->schema());
            $show_leaving_list = $item_access_service->is_visible($leaving_users_access_list);
        }

        $filter_form = $this->createForm(QTextSearchFilterType::class);
        $filter_form->handleRequest($request);

        $params = ['status'	=> $status];

        $status_def_ary = UsersListController::get_status_def_ary($config_cache, $item_access_service, $pp);

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

        $out = UsersListController::get_tab_selector(
            $params,
            $link_render,
            $item_access_service,
            $config_cache,
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
            $is_remote = isset($u['remote_schema']) || isset($u['remote_email']);
            $is_active = $u['is_active'];
            $is_leaving = $u['is_leaving'];
            $post_active = isset($u['activated_at']);
            $is_new = false;

            if ($post_active)
            {
                if ($new_user_treshold->getTimestamp() < strtotime($u['activated_at'] . ' UTC'))
                {
                    $is_new = true;
                }
            }

            $tile_class = null;

            if ($is_active)
            {
                if ($is_remote)
                {
                    $tile_class = 'warning';
                }
                else if ($is_leaving && $show_leaving_list)
                {
                    $tile_class = 'danger';
                }
                else if ($is_new && $show_new_list)
                {
                    $tile_class = 'success';
                }
            }
            else if ($post_active)
            {
                $tile_class = 'inactive';
            }
            else
            {
                $tile_class = 'info';
            }

            $url = $link_render->context_path('users_show', $pp->ary(),
                ['id' => $u['id'], 'link' => $status]);

            $out .= '<div class="col-xs-4 col-md-3 col-lg-2 tile">';
            $out .= '<div';

            if (isset($tile_class))
            {
                $out .= ' class="bg-';
                $out .= $tile_class;
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

        return $this->render('users/users_tiles.html.twig', [
            'content'       => $out,
            'filter_form'   => $filter_form->createView(),
        ]);
    }
}
