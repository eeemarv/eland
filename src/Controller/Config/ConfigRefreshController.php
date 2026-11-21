<?php declare(strict_types=1);

namespace App\Controller\Config;

use App\Routing\AccessLevel;
use App\Routing\AppModule;
use App\Routing\SchemaRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Service\PageParamsService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[AsController]
class ConfigRefreshController extends AbstractController
{
  #[SchemaRoute(
    access_level: AccessLevel::Admin,
    module: AppModule::Config,
    path: '/config/refresh',
    name: 'config_refresh',
    methods: ['GET'],
  )]

  public function __invoke(
    TagAwareCacheInterface $cache,
    PageParamsService $pp
  ):Response
  {
    $cache->invalidateTags(['config']);

    $this->addFlash('success', 'Config refreshed.');

    return $this->redirectToRoute('config_name', $pp->ary());
  }
}
