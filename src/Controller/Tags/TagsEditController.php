<?php declare(strict_types=1);

namespace App\Controller\Tags;

use App\Cache\ConfigCache;
use App\Command\Tags\TagsDefCommand;
use App\Form\Type\Tags\TagsDefType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Repository\TagRepository;
use App\Service\PageParamsService;
use App\Service\ResponseCacheService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class TagsEditController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/tags/users/{id}/edit',
        name: 'tags_users_edit',
        methods: ['GET', 'POST'],
        requirements: [
            'id'            => '%assert.id%',
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
        '/{system}/{role_short}/tags/messages/{id}/edit',
        name: 'tags_messages_edit',
        methods: ['GET', 'POST'],
        requirements: [
            'id'            => '%assert.id%',
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
        '/{system}/{role_short}/tags/calendar/{id}/edit',
        name: 'tags_calendar_edit',
        methods: ['GET', 'POST'],
        requirements: [
            'id'            => '%assert.id%',
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
        '/{system}/{role_short}/tags/news/{id}/edit',
        name: 'tags_news_edit',
        methods: ['GET', 'POST'],
        requirements: [
            'id'            => '%assert.id%',
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
        '/{system}/{role_short}/tags/transactions/{id}/edit',
        name: 'tags_transactions_edit',
        methods: ['GET', 'POST'],
        requirements: [
            'id'            => '%assert.id%',
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
        '/{system}/{role_short}/tags/docs/{id}/edit',
        name: 'tags_docs_edit',
        methods: ['GET', 'POST'],
        requirements: [
            'id'            => '%assert.id%',
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
        '/{system}/{role_short}/tags/forum-topics/{id}/edit',
        name: 'tags_forum_topics_edit',
        methods: ['GET', 'POST'],
        requirements: [
            'id'            => '%assert.id%',
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
        '/{system}/{role_short}/tags/blog/{id}/edit',
        name: 'tags_blog_edit',
        methods: ['GET', 'POST'],
        requirements: [
            'id'            => '%assert.id%',
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
        Request $request,
        int $id,
        string $module,
        string $tag_type,
        TagRepository $tag_repository,
        AlertService $alert_service,
        ConfigCache $config_cache,
        ResponseCacheService $response_cache_service,
        PageParamsService $pp,
    ):Response
    {
        switch ($tag_type)
        {
            case 'users':
                if (!$config_cache->get_bool('users.tags.enabled', $pp->schema()))
                {
                    throw new NotFoundHttpException('Tags for users not enabled.');
                }
                break;
            default:
                throw new NotFoundHttpException('Tag type not supported.');
                break;
        }

        $tag = $tag_repository->get($id, $tag_type, $pp->schema());

        $command = new TagsDefCommand();
        $command->id = $id;
        $command->tag_type = $tag_type;
        $command->txt = $tag['txt'];
        $command->txt_color = $tag['txt_color'];
        $command->bg_color = $tag['bg_color'];
        $command->description = $tag['description'];

        $form = $this->createForm(TagsDefType::class, $command, [
            'tag_type'  => $tag_type,
            'txt_omit'  => $tag['txt'],
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();
            $tag_repository->update($command, $pp->schema());
            $response_cache_service->clear_cache($pp->schema());

            $alert_service->success('Tag "' . $command->txt . '" aangepast.');

            return $this->redirectToRoute('tags_' . $tag_type, $pp->ary());
        }

        return $this->render('tags/tags_edit.html.twig', [
            'form'      => $form->createView(),
            'module'    => $module,
            'tag_type'  => $tag_type,
        ]);
    }
}
