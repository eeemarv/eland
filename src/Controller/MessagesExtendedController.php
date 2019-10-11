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
use App\Service\TypeaheadService;
use Doctrine\DBAL\Connection as Db;

class MessagesExtendedController extends AbstractController
{
    public function messages_extended(
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
        string $env_s3_url
    ):Response
    {
        $fetch_and_filter = self::fetch_and_filter(
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
            $typeahead_service
        );

        $messages = $fetch_and_filter['messages'];
        $params = $fetch_and_filter['params'];
        $out = $fetch_and_filter['out'];

        $ids = $imgs = [];

        foreach ($messages as $msg)
        {
            $ids[] = $msg['id'];
        }

        $_imgs = $db->executeQuery('select mp.msgid, mp."PictureFile"
            from ' . $pp->schema() . '.msgpictures mp
            where msgid in (?)',
            [$ids],
            [Db::PARAM_INT_ARRAY]);

        foreach ($_imgs as $_img)
        {
            if (isset($imgs[$_img['msgid']]))
            {
                continue;
            }

            $imgs[$_img['msgid']] = $_img['PictureFile'];
        }

        MessagesListController::set_view_btn_nav(
            $btn_nav_render,
            $pp->ary(),
            $params,
            'extended'
        );

        if (!count($messages))
        {
            return MessagesListController::no_messages(
                $pagination_render,
                $menu_service
            );
        }

        $time = time();
        $out .= $pagination_render->get();

        foreach ($messages as $msg)
        {
            $sf_owner = $pp->is_user()
                && $msg['id_user'] === $su->id();

            $exp = strtotime($msg['validity']) < $time;

            $out .= '<div class="panel panel-info printview">';
            $out .= '<div class="panel-body';
            $out .= $exp ? ' bg-danger' : '';
            $out .= '">';

            $out .= '<div class="media">';

            if (isset($imgs[$msg['id']]))
            {
                $out .= '<div class="media-left">';
                $out .= '<a href="';

                $out .= $link_render->context_path('messages_show', $pp->ary(),
                    ['id' => $msg['id']]);

                $out .= '">';
                $out .= '<img class="media-object" src="';
                $out .= $env_s3_url . $imgs[$msg['id']];
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
