<?php declare(strict_types=1);

namespace App\Controller\ContactForm;

use App\Routing\AccessLevel;
use App\Routing\AppModule;
use App\Routing\SchemaRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class ContactFormLinkSentController extends AbstractController
{
  #[SchemaRoute(
    access_level: AccessLevel::Anonymous,
    module: AppModule::ContactForm,
    path: '/contact/link-sent',
    name: 'contact_form_link_sent',
    methods: ['GET'],
  )]

  public function __invoke(
  ):Response
  {
    return $this->render('contact_form/contact_form_link_sent.html.twig');
  }
}
