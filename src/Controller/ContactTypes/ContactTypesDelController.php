<?php declare(strict_types=1);

namespace App\Controller\ContactTypes;

use App\Command\ContactTypes\ContactTypesCommand;
use App\Form\Type\ContactTypes\ContactTypesDelType;
use App\Repository\ContactRepository;
use App\Routing\AccessLevel;
use App\Routing\ContactsSubModule;
use App\Routing\SchemaRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\PageParamsService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[AsController]
class ContactTypesDelController extends AbstractController
{
  #[SchemaRoute(
    access_level: AccessLevel::Admin,
    module: ContactsSubModule::ContactTypes,
    path: '/contact-types/{id}/del',
    name: 'contact_types_del',
    methods: ['GET', 'POST'],
  )]

  public function __invoke(
    Request $request,
    int $id,
    ContactRepository $contact_repository,
    PageParamsService $pp,
  ):Response
  {
    $contact_type = $contact_repository->get_contact_type(
      id: $id,
      schema: $pp->schema_o(),
    );

    if (in_array($contact_type['abbrev'], ContactTypesController::PROTECTED))
    {
      throw new BadRequestHttpException('Protected contact type.');
    }

    $count_contacts = $contact_repository->get_count_for_contact_type(
      contact_type_id: $id,
      schema: $pp->schema_o(),
    );

    if ($count_contacts > 0)
    {
      throw new BadRequestHttpException('A contact type with contacts can not be deleted.');
    }

    $command = new ContactTypesCommand();
    $command->id = $id;
    $command->name = $contact_type['name'];
    $command->abbrev = $contact_type['abbrev'];

    $form_options = [
      'validation_groups' => ['del'],
    ];

    $form = $this->createForm(ContactTypesDelType::class, $command, $form_options);

    $form->handleRequest($request);

    if ($form->isSubmitted()
        && $form->isValid())
    {
      $contact_repository->del_contact_type(
        id: $id,
        schema: $pp->schema_o(),
      );

      $this->addFlash(
        type: 'success',
        message: [
          'key' => 'contact_types_del.flash.success',
          'params'  => [
            'name'  => $command->name,
          ]
        ] ,
      );

      return $this->redirectToRoute('contact_types', $pp->ary());
    }

    return $this->render('contact_types/contact_types_del.html.twig', [
      'form'          => $form->createView(),
      'contact_type'  => $contact_type,
    ]);
  }
}
