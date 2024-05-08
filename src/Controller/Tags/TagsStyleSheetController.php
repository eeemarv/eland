<?php declare(strict_types=1);

namespace App\Controller\Tags;

use App\Cache\ConfigCache;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\TagRepository;
use App\Service\PageParamsService;
use App\Service\ResponseCacheService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Cache(public: true, maxage: 31536000)]
class TagsStyleSheetController extends AbstractController
{
    #[Route(
        '/{system}/tags/{tag_type}/{thumbprint}_css',
        name: 'tags_css',
        methods: ['GET'],
        priority: 20,
        requirements: [
            'system'        => '%assert.system%',
            'thumbprint'    => '%assert.thumbprint%',
            'tag_type'      => '%assert.tag_type%',
        ],
    )]

    public function __invoke(
        string $tag_type,
        string $thumbprint,
        ResponseCacheService $response_cache_service,
        TagRepository $tag_repository,
        ConfigCache $config_cache,
        PageParamsService $pp
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

        $thumbprint_key = 'tags_css.' . $tag_type;
        $cached = $response_cache_service->get_response_body($thumbprint, $thumbprint_key, $pp->schema());

        if ($cached !== false)
        {
            return new Response($cached, Response::HTTP_OK, [
                'Content-Type'  => 'text/css',
            ]);
        }

        $tags = $tag_repository->get_all($tag_type, $pp->schema(), active_only:false);

        $response = $this->render('style/tags.css.twig', [
            'tags'   => $tags,
        ]);

        $response_body = $response->getContent();
        $response_cache_service->store_response_body($thumbprint_key, $pp->schema(), $response_body);

        $response->setStatusCode(Response::HTTP_OK);
        $response->headers->set('Content-Type', 'text/css');
        return $response;
    }
}
