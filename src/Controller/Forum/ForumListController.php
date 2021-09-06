<?php declare(strict_types=1);

namespace App\Controller\Forum;

use App\Form\Type\Filter\QTextSearchFilterType;
use App\Repository\ForumRepository;
use App\Service\ConfigService;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
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
        ForumRepository $forum_repository,
        ConfigService $config_service,
        ItemAccessService $item_access_service,
        PageParamsService $pp,
    ):Response
    {
        if (!$config_service->get_bool('forum.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Forum module not enabled.');
        }

        $filter_form = $this->createForm(QTextSearchFilterType::class);
        $filter_form->handleRequest($request);

        $visible_ary = $item_access_service->get_visible_ary_for_page($pp->schema());
        $topics = $forum_repository->get_topics_with_reply_count($visible_ary, $pp->schema());

        $show_access = (!$pp->is_guest()
                && $config_service->get_intersystem_en($pp->schema()))
            || $pp->is_admin();

        return $this->render('forum/forum_list.html.twig', [
            'topics'        => $topics,
            'show_access'   => $show_access,
            'filter_form'   => $filter_form->createView(),
        ]);
    }
}
