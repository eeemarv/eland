<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Controller\MessagesListController;
use App\Render\AccountRender;
use App\Render\BtnNavRender;
use App\Render\BtnTopRender;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Render\PaginationRender;
use App\Render\SelectRender;
use App\Service\AssetsService;
use App\Service\ConfigService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\TypeaheadService;
use App\Service\VarRouteService;
use Doctrine\DBAL\Connection as Db;

class MessagesExtendedController extends AbstractController
{
    public function __invoke(
        Request $request,
        Db $db,
        AccountRender $account_render,
        AssetsService $assets_service,
        BtnTopRender $btn_top_render,
        BtnNavRender $btn_nav_render,
        ConfigService $config_service,
        HeadingRender $heading_render,
        SelectRender $select_render,
        TypeaheadService $typeahead_service,
        LinkRender $link_render,
        MenuService $menu_service,
        PaginationRender $pagination_render,
        PageParamsService $pp,
        SessionUserService $su,
        VarRouteService $vr,
        string $env_s3_url
    ):Response
    {
        $fetch_and_filter = MessagesListController::fetch_and_filter(
            $request,
            $db,
            $account_render,
            $assets_service,
            $btn_top_render,
            $config_service,
            $heading_render,
            $link_render,
            $pagination_render,
            $select_render,
            $pp,
            $su,
            $vr,
            $typeahead_service
        );

        $messages = $fetch_and_filter['messages'];
        $params = $fetch_and_filter['params'];
        $out = $fetch_and_filter['out'];

        MessagesListController::set_view_btn_nav(
            $btn_nav_render,
            $pp,
            $params,
            'extended'
        );

        if (!count($messages))
        {
            $content = MessagesListController::no_messages(
                $pagination_render,
                $menu_service,
            );

            return $this->render('base/navbar.html.twig', [
                'content'   => $content,
                'schema'    => $pp->schema(),
            ]);
        }

        $time = time();
        $out .= $pagination_render->get();

        foreach ($messages as $msg)
        {
            $image_files_ary = array_values(json_decode($msg['image_files'] ?? '[]', true));
            $image_file = $image_files_ary ? $image_files_ary[0] : '';

            $sf_owner = $pp->is_user()
                && $msg['id_user'] === $su->id();

            $exp = strtotime($msg['validity']) < $time;

            $out .= '<div class="panel panel-info printview">';
            $out .= '<div class="panel-body';
            $out .= $exp ? ' bg-danger' : '';
            $out .= '">';

            $out .= '<div class="media">';

            if ($image_file)
            {
                $out .= '<div class="media-left">';
                $out .= '<a href="';

                $out .= $link_render->context_path('messages_show', $pp->ary(),
                    ['id' => $msg['id']]);

                $out .= '">';
                $out .= '<img class="media-object" src="';
                $out .= $env_s3_url . $image_file;
                $out .= '" width="150">';
                $out .= '</a>';
                $out .= '</div>';
            }

            $out .= '<div class="media-body">';
            $out .= '<h3 class="media-heading">';

            $out .= $link_render->link_no_attr('messages_show', $pp->ary(),
                ['id' => $msg['id']],
                ucfirst($msg['label']['type']) . ': ' . $msg['content']);

            if ($exp)
            {
                $out .= ' <small><span class="text-danger">';
                $out .= 'Vervallen</span></small>';
            }

            $out .= '</h3>';

            $out .= htmlspecialchars($msg['Description'] ?? '', ENT_QUOTES);

            $out .= '</div>';
            $out .= '</div>';

            $out .= '</div>';

            $out .= '<div class="panel-footer">';
            $out .= '<p><i class="fa fa-user"></i> ';
            $out .= $account_render->link($msg['id_user'], $pp->ary());
            $out .= $msg['postcode'] ? ', postcode: ' . $msg['postcode'] : '';

            if ($pp->is_admin() || $sf_owner)
            {
                $out .= '<span class="inline-buttons pull-right hidden-xs">';

                $out .= $link_render->link_fa('messages_edit', $pp->ary(),
                    ['id' => $msg['id']], 'Aanpassen',
                    ['class'	=> 'btn btn-primary'],
                    'pencil');

                $out .= $link_render->link_fa('messages_del', $pp->ary(),
                    ['id' => $msg['id']], 'Verwijderen',
                    ['class' => 'btn btn-danger'],
                    'times');

                $out .= '</span>';
            }
            $out .= '</p>';
            $out .= '</div>';

            $out .= '</div>';
        }

        $out .= $pagination_render->get();

        $menu_service->set('messages');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
