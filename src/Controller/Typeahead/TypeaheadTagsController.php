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
class TypeaheadTagsController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/typeahead-tags-users/{thumbprint}',
        name: 'typeahead_tags_users',
        methods: ['GET'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.guest%',
            'thumbprint'    => '%assert.thumbprint%',
        ],
        defaults: [
            'module'        => 'users',
            'tag_type'      => 'users',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/typeahead-tags-messages/{thumbprint}',
        name: 'typeahead_tags_messages',
        methods: ['GET'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.guest%',
            'thumbprint'    => '%assert.thumbprint%',
        ],
        defaults: [
            'module'        => 'messages',
            'tag_type'      => 'messages',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/typeahead-tags-calendar/{thumbprint}',
        name: 'typeahead_tags_calendar',
        methods: ['GET'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.guest%',
            'thumbprint'    => '%assert.thumbprint%',
        ],
        defaults: [
            'module'        => 'calendar',
            'tag_type'      => 'calendar',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/typeahead-tags-news/{thumbprint}',
        name: 'typeahead_tags_news',
        methods: ['GET'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.guest%',
            'thumbprint'    => '%assert.thumbprint%',
        ],
        defaults: [
            'module'        => 'news',
            'tag_type'      => 'news',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/typeahead-tags-transactions/{thumbprint}',
        name: 'typeahead_tags_transactions',
        methods: ['GET'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.guest%',
            'thumbprint'    => '%assert.thumbprint%',
        ],
        defaults: [
            'module'        => 'transactions',
            'tag_type'      => 'transactions',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/typeahead-tags-docs/{thumbprint}',
        name: 'typeahead_tags_docs',
        methods: ['GET'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.guest%',
            'thumbprint'    => '%assert.thumbprint%',
        ],
        defaults: [
            'module'        => 'docs',
            'tag_type'      => 'docs',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/typeahead-tags-forum-topics/{thumbprint}',
        name: 'typeahead_tags_forum_topics',
        methods: ['GET'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.guest%',
            'thumbprint'    => '%assert.thumbprint%',
        ],
        defaults: [
            'module'        => 'forum',
            'tag_type'      => 'forum_topics',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/typeahead-tags-blog/{thumbprint}',
        name: 'typeahead_tags_blog',
        methods: ['GET'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.guest%',
            'thumbprint'    => '%assert.thumbprint%',
        ],
        defaults: [
            'module'        => 'blog',
            'tag_type'      => 'blog',
        ],
    )]

    public function __invoke(
        string $thumbprint,
        string $tag_type,
        TagRepository $tag_repository,
        TypeaheadService $typeahead_service,
        PageParamsService $pp
    ):Response
    {
        $cached = $typeahead_service->get_cached_data($thumbprint, $pp, []);

        if ($cached !== false)
        {
            return new Response($cached, 200, ['Content-Type' => 'application/json']);
        }

        $tags = $tag_repository->get_all_for_render($tag_type, $pp->schema());
        $data = json_encode($tags);
        $typeahead_service->set_thumbprint($thumbprint, $data, $pp, []);
        return new Response($data, 200, ['Content-Type' => 'application/json']);
    }
}
