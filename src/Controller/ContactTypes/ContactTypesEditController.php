<?php declare(strict_types=1);

namespace App\Controller\ContactTypes;

use App\Command\ContactTypes\ContactTypesCommand;
use App\Form\Type\ContactTypes\ContactTypesType;
use App\Repository\ContactRepository;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class ContactTypesEditController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/contact-types/{id}/edit',
    name: 'contact_types_edit',
    methods: ['GET', 'POST'],
    requirements: [
      'id'            => '%assert.id%',
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.admin%',
    ],
    defaults: [
      'module'        => 'users',
      'sub_module'    => 'contact_types',
    ],
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

    $command = new ContactTypesCommand();
    $command->id = $id;
    $command->name = $contact_type['name'];
    $command->abbrev = $contact_type['abbrev'];

    $form_options = [
      'validation_groups' => ['edit'],
    ];

    $form = $this->createForm(ContactTypesType::class, $command, $form_options);

    $form->handleRequest($request);

    if ($form->isSubmitted()
      && $form->isValid())
    {
      $command = $form->getData();
      $contact_repository->update_contact_type(
        id: $id,
        abbrev: $command->abbrev,
        name: $command->name,
        schema: $pp->schema_o(),
      );

      $this->addFlash(
        type: 'success',
        message: [
          'key' => 'contact_types_edit.flash.success',
          'params'  => [
            'name'  => $command->name,
          ]
        ] ,
      );

      return $this->redirectToRoute('contact_types', $pp->ary());
    }

    return $this->render('contact_types/contact_types_edit.html.twig', [
      'form'   => $form->createView(),
    ]);
  }
}
