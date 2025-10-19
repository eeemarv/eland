<?php declare(strict_types=1);

namespace App\Controller\SupportForm;

use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class SupportFormSentController extends AbstractController
{
  #[Route(
    '/{system}/{role_short}/support/sent',
    name: 'support_form_sent',
    methods: ['GET'],
    requirements: [
      'system'        => '%assert.system%',
      'role_short'    => '%assert.role_short.user%',
    ],
    defaults: [
      'module'        => 'support_form',
    ],
  )]

  public function __invoke(
    ConfigService $config_service,
    PageParamsService $pp,
  ):Response
  {
    if (!$config_service->get_bool('support_form.enabled', $pp->schema()))
    {
      throw $this->createNotFoundException('Support form not enabled.');
    }

    return $this->render('support_form/support_form_sent.html.twig', [
    ]);
  }
}
