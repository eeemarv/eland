<?php declare(strict_types=1);

namespace App\Controller\Typeahead;

use App\Repository\TagRepository;
use App\Service\PageParamsService;
use App\Service\TypeaheadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class TypeaheadTagsCheckController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/typeahead-tags-users-check/{thumbprint}',
        name: 'typeahead_tags_users_check',
        methods: ['GET'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
            'thumbprint'    => '%assert.thumbprint%',
        ],
        defaults: [
            'module'        => 'users',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/typeahead-tags-messages-check/{thumbprint}',
        name: 'typeahead_tags_messages_check',
        methods: ['GET'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
            'thumbprint'    => '%assert.thumbprint%',
        ],
        defaults: [
            'module'        => 'messages',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/typeahead-tags-calendar-check/{thumbprint}',
        name: 'typeahead_tags_calendar_check',
        methods: ['GET'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
            'thumbprint'    => '%assert.thumbprint%',
        ],
        defaults: [
            'module'        => 'calendar',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/typeahead-tags-news-check/{thumbprint}',
        name: 'typeahead_tags_news_check',
        methods: ['GET'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
            'thumbprint'    => '%assert.thumbprint%',
        ],
        defaults: [
            'module'        => 'news',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/typeahead-tags-transactions-check/{thumbprint}',
        name: 'typeahead_tags_transactions_check',
        methods: ['GET'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
            'thumbprint'    => '%assert.thumbprint%',
        ],
        defaults: [
            'module'        => 'transactions',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/typeahead-tags-docs-check/{thumbprint}',
        name: 'typeahead_tags_docs_check',
        methods: ['GET'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
            'thumbprint'    => '%assert.thumbprint%',
        ],
        defaults: [
            'module'        => 'docs',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/typeahead-tags-forum-check/{thumbprint}',
        name: 'typeahead_tags_forum_check',
        methods: ['GET'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
            'thumbprint'    => '%assert.thumbprint%',
        ],
        defaults: [
            'module'        => 'forum',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/typeahead-tags-blog-check/{thumbprint}',
        name: 'typeahead_tags_blog_check',
        methods: ['GET'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
            'thumbprint'    => '%assert.thumbprint%',
        ],
        defaults: [
            'module'        => 'blog',
        ],
    )]

    public function __invoke(
        string $thumbprint,
        string $module,
        string $tag_type,
        TagRepository $tag_repository,
        TypeaheadService $typeahead_service,
        PageParamsService $pp
    ):Response
    {
        $tag_type = $module;

        $cached = $typeahead_service->get_cached_data($thumbprint, $pp, []);

        if ($cached !== false)
        {
            return new Response($cached, 200, ['Content-Type' => 'application/json']);
        }

        $tags = $tag_repository->get_flat_ary($tag_type, $pp->schema());
        $data = json_encode($tags);
        $typeahead_service->set_thumbprint($thumbprint, $data, $pp, []);
        return new Response($data, 200, ['Content-Type' => 'application/json']);
    }
}
