<?php declare(strict_types=1);

namespace App\Controller\Config;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\SystemsService;
use App\Service\PageParamsService;
use Symfony\Component\Routing\Annotation\Route;

class ConfigRefreshController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/config/refresh',
        name: 'config_refresh',
        methods: ['GET'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'module'        => 'config',
        ],
    )]

    public function __invoke(
        AlertService $alert_service,
        ConfigService $config_service,
        SystemsService $systems_service,
        PageParamsService $pp
    ):Response
    {
        $schemas = $systems_service->get_schemas();

        foreach ($schemas as $schema)
        {
            $config_service->clear_cache($schema);
        }

        $alert_service->success('Config refreshed.');

        return $this->redirectToRoute('config_name', $pp->ary());
    }
}
