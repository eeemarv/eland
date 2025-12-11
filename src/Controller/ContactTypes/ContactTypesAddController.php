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
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class ContactTypesAddController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/contact-types/add',
    name: 'contact_types_add',
    methods: ['GET', 'POST'],
    requirements: [
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
    ContactRepository $contact_repository,
    PageParamsService $pp,
  ):Response
  {
    $command = new ContactTypesCommand();

    $form_options = [
      'validation_groups' => ['add'],
    ];

    $form = $this->createForm(
      type: ContactTypesType::class,
      data: $command,
      options: $form_options,
    );

    $form->handleRequest($request);

    if ($form->isSubmitted()
      && $form->isValid())
    {
      $command = $form->getData();
      $contact_repository->insert_contact_type(
        abbrev: $command->abbrev,
        name: $command->name,
        schema: $pp->schema_o(),
      );

      $this->addFlash(
        type: 'success',
        message: [
          'key' => 'contact_types_add.flash.success',
          'params'  => [
            'name'  => $command->name,
          ]
        ] ,
      );
      return $this->redirectToRoute('contact_types', $pp->ary());
    }

    return $this->render('contact_types/contact_types_add.html.twig', [
      'form'  => $form->createView(),
    ]);
  }
}
