<?php declare(strict_types=1);

namespace App\Controller\Init;

use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[AsController]
class InitClearUsersCacheController extends AbstractController
{
    #[Route(
        '/{system}/init/clear-users-cache',
        name: 'init_clear_users_cache',
        methods: ['GET'],
        requirements: [
            'system'        => '%assert.system%',
        ],
    )]

    public function __invoke(
        Request $request,
        PageParamsService $pp,
        TagAwareCacheInterface $cache,
        #[Autowire('%env(APP_INIT_ENABLED)%')]
        string $env_app_init_enabled
    ):Response
    {
        if (!$env_app_init_enabled)
        {
            throw new NotFoundHttpException('De init routes zijn niet ingeschakeld.');
        }

        set_time_limit(300);

        $cache->invalidateTags(['users']);

        return $this->redirectToRoute('init', [
            ...$pp->ary(),
            'ok' => $request->attributes->get('_route'),
        ]);
    }
}
