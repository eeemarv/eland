<?php declare(strict_types=1);

namespace App\Controller\Icons;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Cache(public: true, maxage: 31536000, mustRevalidate: true)]
class IconsSummernoteController extends AbstractController
{
  #[Route(
    '/icons/summernote.js',
    name: 'icons_summernote',
    methods: ['GET'],
  )]

  public function __invoke(
  ):Response
  {
    $response = $this->render('icons/icons_summernote.js.twig');
    $response->headers->set('Content-Type', 'application/javascript');

    return $response;;
  }
}
