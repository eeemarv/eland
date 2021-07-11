<?php declare(strict_types=1);

namespace App\Controller\Forum;

use App\Render\AccountRender;
use App\Render\BtnNavRender;
use App\Render\BtnTopRender;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\ConfigService;
use App\Service\DateFormatService;
use App\Service\ItemAccessService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use Doctrine\DBAL\Connection as Db;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class ForumListController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/forum',
        name: 'forum',
        methods: ['GET'],
        priority: 20,
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.guest%',
        ],
        defaults: [
            'module'        => 'forum',
        ],
    )]

    public function __invoke(
        Request $request,
        Db $db,
        AccountRender $account_render,
        BtnNavRender $btn_nav_render,
        BtnTopRender $btn_top_render,
        ConfigService $config_service,
        DateFormatService $date_format_service,
        HeadingRender $heading_render,
        ItemAccessService $item_access_service,
        LinkRender $link_render,
        PageParamsService $pp,
        MenuService $menu_service
    ):Response
    {
        if (!$config_service->get_bool('forum.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Forum module not enabled.');
        }

        // to do: filter after page loaded
        $q = $request->query->get('q', '');

        // to do: order by last post edit desc
        $stmt = $db->executeQuery('select t.*, count(p.*) - 1 as reply_count
            from ' . $pp->schema() . '.forum_topics t
            inner join ' . $pp->schema() . '.forum_posts p on p.topic_id = t.id
            where t.access in (?)
            group by t.id
            order by t.last_edit_at desc',
            [$item_access_service->get_visible_ary_for_page()],
            [Db::PARAM_STR_ARRAY]);

        $forum_topics = $stmt->fetchAll();

        if ($pp->is_admin() || $pp->is_user())
        {
            $btn_top_render->add('forum_add_topic', $pp->ary(),
                [], 'Onderwerp toevoegen');
        }

        if ($pp->is_admin())
        {
            $btn_nav_render->csv();
        }

        $show_access = (!$pp->is_guest()
                && $config_service->get_intersystem_en($pp->schema()))
            || $pp->is_admin();

        $heading_render->add('Forum');
        $heading_render->fa('comments-o');

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="get">';
        $out .= '<div class="row">';
        $out .= '<div class="col-xs-12">';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-search"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" id="q" name="q" value="';
        $out .= $q . '" ';
        $out .= 'placeholder="Zoeken">';
        $out .= '</div>';
        $out .= '</div>';
        $out .= '</div>';
        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        if (!count($forum_topics))
        {
            $out .= '<div class="panel panel-default">';
            $out .= '<div class="panel-heading">';
            $out .= '<p>Er zijn nog geen forum onderwerpen.</p>';
            $out .= '</div></div>';

            $menu_service->set('forum');

            return $this->render('forum/forum_list.html.twig', [
                'content'   => $out,
                'schema'    => $pp->schema(),
            ]);
        }

        $out .= '<div class="panel panel-default printview">';

        $out .= '<div class="table-responsive">';
        $out .= '<table class="table table-bordered table-striped table-hover footable csv"';
        $out .= ' data-filter="#q" data-filter-minimum="1">';
        $out .= '<thead>';

        $out .= '<tr>';
        $out .= '<th>Onderwerp</th>';
        $out .= '<th>Reacties</th>';
        $out .= '<th data-hide="phone, tablet">Gebruiker</th>';
        $out .= '<th data-hide="phone, tablet" data-sort-initial="descending" ';
        $out .= 'data-type="numeric">Aanmaak</th>';
        $out .= $show_access ? '<th data-hide="phone, tablet">Toegang</th>' : '';
        $out .= '</tr>';

        $out .= '</thead>';
        $out .= '<tbody>';

        foreach($forum_topics as $topic)
        {
            $id = $topic['id'];
            $td = [];

            $td[] = $link_render->link_no_attr('forum_topic', $pp->ary(),
                ['id' => $id], $topic['subject']);
            $td[] = $topic['reply_count'];
            $td[] = $account_render->link((int) $topic['user_id'], $pp->ary());
            $td[] = $date_format_service->get($topic['created_at'], 'min', $pp->schema());

            if ($show_access)
            {
                $td[] = $item_access_service->get_label($topic['access']);
            }

            $out .= '<tr><td>';
            $out .= implode('</td><td>', $td);
            $out .= '</td></tr>';
        }

        $out .= '</tbody>';
        $out .= '</table>';
        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('forum');

        return $this->render('forum/forum_list.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
