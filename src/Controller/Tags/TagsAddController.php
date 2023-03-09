<?php declare(strict_types=1);

namespace App\Controller\Tags;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Command\Tags\TagsDefCommand;
use App\Form\Type\Tags\TagsDefType;
use App\Service\AlertService;
use App\Repository\TagRepository;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\TypeaheadService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class TagsAddController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/tags/users/add',
        name: 'tags_users_add',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'tag_type'      => 'users',
            'module'        => 'users',
            'sub_module'    => 'tags',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/tags/messages/add',
        name: 'tags_messages_add',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'tag_type'      => 'messages',
            'module'        => 'messages',
            'sub_module'    => 'tags',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/tags/calendar/add',
        name: 'tags_calendar_add',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'tag_type'      => 'calendar',
            'module'        => 'calendar',
            'sub_module'    => 'tags',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/tags/news/add',
        name: 'tags_news_add',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'tag_type'      => 'news',
            'module'        => 'news',
            'sub_module'    => 'tags',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/tags/transactions/add',
        name: 'tags_transactions_add',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'tag_type'      => 'transactions',
            'module'        => 'transactions',
            'sub_module'    => 'tags',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/tags/docs/add',
        name: 'tags_docs_add',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'tag_type'      => 'docs',
            'module'        => 'docs',
            'sub_module'    => 'tags',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/tags/forum-topics/add',
        name: 'tags_forum_topics_add',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'tag_type'      => 'forum_topics',
            'module'        => 'forum',
            'sub_module'    => 'tags',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/tags/blog/add',
        name: 'tags_blog_add',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'tag_type'      => 'blog',
            'module'        => 'blog',
            'sub_module'    => 'tags',
        ],
    )]

    public function __invoke(
        string $module,
        string $tag_type,
        Request $request,
        TagRepository $tag_repository,
        TypeaheadService $typeahead_service,
        AlertService $alert_service,
        PageParamsService $pp,
        SessionUserService $su
    ):Response
    {
        $command = new TagsDefCommand();
        $command->id = 0;
        $command->tag_type = $tag_type;
        $command->bg_color = '#eeeeee';
        $command->txt_color = '#555555';

        $form = $this->createForm(TagsDefType::class, $command, [
            'tag_type'  => $tag_type,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();
            $created_by = $su->is_master() ? null : $su->id();
            $command->tag_type = $tag_type;
            $tag_repository->insert($command, $created_by, $pp->schema());
            $typeahead_service->clear_cache($pp->schema());
            $alert_service->success('Tag "' . $command->txt . '" opgeslagen.');

            return $this->redirectToRoute('tags_' . $tag_type, $pp->ary());
        }

        return $this->render('tags/tags_add.html.twig', [
            'form'      => $form->createView(),
            'module'    => $module,
            'tag_type'  => $tag_type,
        ]);
    }
}
