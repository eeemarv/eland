<?php declare(strict_types=1);

namespace App\Controller\ContactForm;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class ContactFormLinkSentController extends AbstractController
{
  #[Route(
    '/{system}/contact/link-sent',
    name: 'contact_form_link_sent',
    methods: ['GET'],
    requirements: [
      'system'  => '%assert.system%',
    ],
    defaults: [
      'module'  => 'contact_form',
    ],
  )]

  public function __invoke(
    ConfigService $config_service,
    PageParamsService $pp,
  ):Response
  {
    if (!$config_service->get_bool(
      config_id: 'contact_form.enabled',
      schema: $pp->schema_o(),
    ))
    {
      throw $this->createNotFoundException('Contact form module not enabled.');
    }

    return $this->render('contact_form/contact_form_link_sent.html.twig', []);
  }
}
