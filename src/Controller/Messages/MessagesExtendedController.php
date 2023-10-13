<?php declare(strict_types=1);

namespace App\Controller\Messages;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Render\AccountRender;
use App\Render\LinkRender;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
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
        MessagesListController $messages_list_controller,
        AccountRender $account_render,
        ConfigService $config_service,
        LinkRender $link_render,
        PageParamsService $pp,
        SessionUserService $su,
        string $env_s3_url
    ):Response
    {
        if (!$config_service->get_bool('messages.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Messages (offers/wants) module not enabled.');
        }

        $expires_at_enabled = $config_service->get_bool('messages.fields.expires_at.enabled', $pp->schema());
        $category_enabled = $config_service->get_bool('messages.fields.category.enabled', $pp->schema());

        $fetch_and_filter = $messages_list_controller->fetch_and_filter(
            $request,
            $db,
            $is_self,
            $config_service,
            $pp,
            $su
        );

        $messages = $fetch_and_filter['messages'];
        $row_count = $fetch_and_filter['row_count'];
        $filter_form = $fetch_and_filter['filter_form'];
        $filter_command = $fetch_and_filter['filter_command'];
        $uid = $fetch_and_filter['uid'];
        $filtered = $fetch_and_filter['filtered'];
        $filter_collapse = $fetch_and_filter['filter_collapse'];
        $count_ary = $fetch_and_filter['count_ary'];
        $cat_count_ary = $fetch_and_filter['cat_count_ary'];

        $categories = [];

        if ($category_enabled)
        {
            $parent_name = '***';

            $res = $db->executeQuery('select *
                from ' . $pp->schema() . '.categories
                order by left_id asc');

            while ($row = $res->fetchAssociative())
            {
                $name = $row['name'];
                $cat_id = $row['id'];
                $parent_id = $row['parent_id'];

                if (isset($parent_id))
                {
                    $categories[$cat_id] = $parent_name . ' > ' . $name;
                }
                else
                {
                    $parent_name = $name;
                    $categories[$cat_id] = $parent_name;
                }
            }
        }

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

            $ow_label = '<span class="label label-li label-';
            $ow_label .= match($msg['offer_want']){
                'offer' => 'white">Aanbod',
                'want'  => 'default">Vraag',
                default => 'danger">***ERR**',
            };
            $ow_label .= '</span>';

            $out .= '<a href="';
            $out .= $link_render->path('messages_show', [
                ...$pp->ary(),
                'id' => $msg['id'],
            ]);
            $out .= '">';
            $out .= $ow_label;
            $out .= ' ';
            $out .= htmlspecialchars($msg['subject'], ENT_QUOTES);
            $out .= '</a>';

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

        return $this->render('messages/messages_extended.html.twig', [
            'data_list_raw'         => $out,
            'bulk_actions_raw'      => '',
            'categories'            => $categories,
            'row_count'             => $row_count,
            'is_self'               => $is_self,
            'uid'                   => $uid,
            'cat_id'                => $filter_command->cat ?: null,
            'filter_form'           => $filter_form,
            'filtered'              => $filtered,
            'msgs_filter_collapse'  => $filter_collapse,
            'count_ary'             => $count_ary,
            'cat_count_ary'         => $cat_count_ary,
        ]);
    }
}
