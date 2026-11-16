<?php declare(strict_types=1);

namespace App\Controller\Dev;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * To check if there are routes with missing requirements
 */

#[AsController]
class DevCheckRoutesController extends AbstractController
{
  #[Route(
    '/_dev/check-routes',
    name: 'dev_check_routes',
    methods: ['GET'],
    env: 'dev',
  )]

  public function __invoke(
    RouterInterface $router,
  ):Response
  {
    $routes_with_missing_requirements = [];

    foreach ($router->getRouteCollection()->all() as $name => $route) 
    {
      if (str_starts_with($name, '_')) 
      {
        continue;
      }

      $path = $route->getPath();
      $requirements = $route->getRequirements();

      preg_match_all('/\{([^}]+)\}/', $path, $matches);
      $parameters = $matches[1] ?? [];

      foreach ($parameters as $parameter) 
      {
        $pure_param_name = explode('=', $parameter)[0];

        if (!array_key_exists($pure_param_name, $requirements)) 
        {
          $routes_with_missing_requirements[] = [
            'route_name' => $name,
            'path'       => $path,
            'parameter'  => $pure_param_name,
          ];
        }
      }
    }

    return $this->json([
      'total_missing' => count($routes_with_missing_requirements),
      'missing' => $routes_with_missing_requirements,
    ]);
  }
}
