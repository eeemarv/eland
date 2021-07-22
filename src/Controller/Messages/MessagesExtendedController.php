<?php declare(strict_types=1);

namespace App\Controller\Messages;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Render\AccountRender;
use App\Render\LinkRender;
use App\Render\SelectRender;
use App\Service\ConfigService;
use App\Service\ItemAccessService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\TypeaheadService;
use App\Service\VarRouteService;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class MessagesExtendedController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/messages/extended',
        name: 'messages_extended',
        methods: ['GET'],
        priority: 20,
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.guest%',
        ],
        defaults: [
            'is_self'       => false,
            'module'        => 'messages',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/messages/extended/self',
        name: 'messages_extended_self',
        methods: ['GET'],
        priority: 20,
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.user%',
        ],
        defaults: [
            'is_self'       => true,
            'module'        => 'messages',
        ],
    )]

    public function __invoke(
        Request $request,
        Db $db,
        bool $is_self,
        AccountRender $account_render,
        ConfigService $config_service,
        ItemAccessService $item_access_service,
        SelectRender $select_render,
        TypeaheadService $typeahead_service,
        LinkRender $link_render,
        MenuService $menu_service,
        PageParamsService $pp,
        SessionUserService $su,
        VarRouteService $vr,
        string $env_s3_url
    ):Response
    {
        if (!$config_service->get_bool('messages.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Messages (offers/wants) module not enabled.');
        }

        $expires_at_enabled = $config_service->get_bool('messages.fields.expires_at.enabled', $pp->schema());

        $fetch_and_filter = MessagesListController::fetch_and_filter(
            $request,
            $db,
            $is_self,
            $account_render,
            $config_service,
            $item_access_service,
            $link_render,
            $select_render,
            $pp,
            $su,
            $vr,
            $typeahead_service
        );

        $messages = $fetch_and_filter['messages'];
        $row_count = $fetch_and_filter['row_count'];
        $categories = $fetch_and_filter['categories'];
        $filter_uid = $fetch_and_filter['filter_uid'];
        $uid = $fetch_and_filter['uid'];
        $filter_cid = $fetch_and_filter['filter_cid'];
        $cid = $fetch_and_filter['cid'];
        $filtered = $fetch_and_filter['filtered'];
        $params = $fetch_and_filter['params'];
        $flt = $fetch_and_filter['out'];

        $time = time();

        $out = '';

        foreach ($messages as $msg)
        {
            $image_files_ary = array_values(json_decode($msg['image_files'] ?? '[]', true));
            $image_file = $image_files_ary ? $image_files_ary[0] : '';

            $sf_owner = $pp->is_user()
                && $msg['user_id'] === $su->id();

            $expired = $expires_at_enabled
                && isset($msg['expires_at'])
                && strtotime($msg['expires_at'] . ' UTC') < $time;

            $out .= '<div class="panel panel-info printview">';
            $out .= '<div class="panel-body';
            $out .= $expired ? ' bg-danger' : '';
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
                ucfirst($msg['label']['offer_want']) . ': ' . $msg['subject']);

            if ($expired)
            {
                $out .= ' <small><span class="text-danger">';
                $out .= 'Vervallen</span></small>';
            }

            $out .= '</h3>';

            $out .= nl2br($msg['content'] ?? '');

            $out .= '</div>';
            $out .= '</div>';

            $out .= '</div>';

            $out .= '<div class="panel-footer">';
            $out .= '<p><i class="fa fa-user"></i> ';
            $out .= $account_render->link($msg['user_id'], $pp->ary());
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

        $menu_service->set('messages');

        return $this->render('messages/messages_extended.html.twig', [
            'data_list_raw'     => $out,
            'filter_form_raw'   => $flt,
            'bulk_actions_raw'  => '',
            'categories'    => $categories,
            'row_count'     => $row_count,
            'is_self'       => $is_self,
            'filter_uid'    => $filter_uid,
            'uid'           => $uid,
            'filter_cid'    => $filter_cid,
            'cid'           => $cid,
            'filtered'      => $filtered,
        ]);
    }
}
