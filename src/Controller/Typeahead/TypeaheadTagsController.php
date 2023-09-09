<?php declare(strict_types=1);

namespace App\Controller\Typeahead;

use App\Repository\TagRepository;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\TypeaheadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class TypeaheadTagsController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/typeahead-tags/{tag_type}/{thumbprint}',
        name: 'typeahead_tags',
        methods: ['GET'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.guest%',
            'thumbprint'    => '%assert.thumbprint%',
            'tag_type'      => '%assert.tag_type%',
        ]
    )]

    public function __invoke(
        string $thumbprint,
        string $tag_type,
        TagRepository $tag_repository,
        TypeaheadService $typeahead_service,
        ConfigService $config_service,
        PageParamsService $pp
    ):Response
    {
        switch ($tag_type)
        {
            case 'users':
                if (!$config_service->get_bool('users.tags.enabled', $pp->schema()))
                {
                    throw new NotFoundHttpException('Tags for users not enabled.');
                }
                if (!$pp->is_admin())
                {
                    throw new AccessDeniedHttpException('Access denied.');
                }
                break;
            default:
                throw new NotFoundHttpException('Tag type not supported.');
                break;
        }

        $params = [
            'tag_type'  => $tag_type,
        ];

        $cached = $typeahead_service->get_cached_response_body($thumbprint, $pp, $params);

        if ($cached !== false)
        {
            return new Response($cached, 200, ['Content-Type' => 'application/json']);
        }

        $tags = $tag_repository->get_all_active_and_ordered($tag_type, $pp->schema());
        $response_body = json_encode($tags);
        $typeahead_service->store_response_body($response_body, $pp, $params);
        return new Response($response_body, 200, ['Content-Type' => 'application/json']);
    }
}
