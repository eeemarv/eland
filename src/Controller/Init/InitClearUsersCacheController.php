<?php declare(strict_types=1);

namespace App\Controller\Init;

use App\Service\PageParamsService;
use App\Service\SystemsService;
use App\Service\UserCacheService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

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
        SystemsService $systems_service,
        UserCacheService $user_cache_service,
        string $env_app_init_enabled
    ):Response
    {
        if (!$env_app_init_enabled)
        {
            throw new NotFoundHttpException('De init routes zijn niet ingeschakeld.');
        }

        set_time_limit(300);

        error_log('*** clear users cache ***');

        $schemas = $systems_service->get_schemas();

        foreach($schemas as $schema)
        {
            $user_cache_service->clear_all($schema);
        }

        return $this->redirectToRoute('init', [
            ...$pp->ary(),
            'ok' => $request->attributes->get('_route'),
        ]);
    }
}
