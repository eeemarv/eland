<?php declare(strict_types=1);

namespace App\Controller\RegisterForm;

use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class RegisterFormLinkSentController extends AbstractController
{
  #[Route(
    '/{system}/register/link_sent',
    name: 'register_form_link_sent',
    methods: ['GET'],
    requirements: [
      'system'        => '%assert.system%',
    ],
    defaults: [
      'module'        => 'register_form',
    ],
  )]

  public function __invoke(
    ConfigService $config_service,
    PageParamsService $pp
  ):Response
  {
    if (!$config_service->get_bool(
      config_id: 'register_form.enabled',
      schema: $pp->schema_o(),
    ))
    {
      throw $this->createNotFoundException('Register form not enabled.');
    }

    return $this->render('register_form/register_form_link_sent.html.twig', []);
  }
}
