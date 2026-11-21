<?php declare(strict_types=1);

namespace App\Controller\Config;

use App\Routing\AccessLevel;
use App\Routing\AppModule;
use App\Routing\SchemaRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class ConfigLogoController extends AbstractController
{
  #[SchemaRoute(
    access_level: AccessLevel::Admin,
    module: AppModule::Config,
    path: '/config/logo',
    name: 'config_logo',
    methods: ['GET', 'POST'],
  )]

  public function __invoke(
  ):Response
  {
    return $this->render('config/config_logo.html.twig', [
    ]);
  }
}
