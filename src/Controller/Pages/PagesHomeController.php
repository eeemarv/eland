<?php declare(strict_types=1);

namespace App\Controller\Pages;

use App\Routing\AccessLevel;
use App\Routing\AppModule;
use App\Routing\SchemaRoute;
use App\Service\SessionUserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class PagesHomeController extends AbstractController
{
  #[SchemaRoute(
    access_level: AccessLevel::Anonymous,
    module: AppModule::Home,
    path: '/',
    name: 'home',
    methods: ['GET'],
    priority: 30,
  )]

  public function __invoke(
    Request $request,
    SessionUserService $su,
  ):Response
  {
    $response = $this->render('pages/home.html.twig');

    $logins = $su->logins();

    if (empty($logins)){
      $response->setEtag(hash('crc32b', $response->getContent()), true);
      $response->setPublic();
      $response->isNotModified($request);
    }

    return $response;
  }
}
