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
        ],
    )]

    public function __invoke(
        string $thumbprint,
        string $module,
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

        $tags = $tag_repository->get_all_for_render($tag_type, $pp->schema());
        $data = json_encode($tags);
        $typeahead_service->set_thumbprint($thumbprint, $data, $pp, []);
        return new Response($data, 200, ['Content-Type' => 'application/json']);
    }
}
