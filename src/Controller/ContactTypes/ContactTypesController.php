<?php declare(strict_types=1);

namespace App\Controller\ContactTypes;

use App\Repository\ContactRepository;
use App\Routing\AccessLevel;
use App\Routing\ContactsSubModule;
use App\Routing\SchemaRoute;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class ContactTypesController extends AbstractController
{
  const PROTECTED = ['mail', 'gsm', 'tel', 'adr', 'web'];

  #[SchemaRoute(
    access_level: AccessLevel::Admin,
    module: ContactsSubModule::ContactTypes,
    path: '/contact-types',
    name: 'contact_types',
    methods: ['GET'],
  )]

  public function __invoke(
    ContactRepository $contact_repository,
    PageParamsService $pp
  ):Response
  {
    $contact_types = $contact_repository->get_all_contact_types_with_count(
      schema: $pp->schema_o(),
    );

    foreach ($contact_types as &$ct)
    {
      if (in_array($ct['abbrev'], self::PROTECTED))
      {
        $ct['protected'] = true;
      }
    }

    return $this->render('contact_types/contact_types.html.twig', [
      'contact_types' => $contact_types,
    ]);
  }
}
