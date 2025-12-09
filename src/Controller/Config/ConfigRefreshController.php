<?php declare(strict_types=1);

namespace App\Controller\Config;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Service\PageParamsService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[AsController]
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
    TagAwareCacheInterface $cache,
    PageParamsService $pp
  ):Response
  {
    $cache->invalidateTags(['config']);

    $this->addFlash('success', 'Config refreshed.');

    return $this->redirectToRoute('config_name', $pp->ary());
  }
}
