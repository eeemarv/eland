<?php declare(strict_types=1);

namespace App\Controller\Categories;

use App\Command\Categories\CategoriesNameCommand;
use App\Command\Messages\MessagesCleanupCommand;
use App\Form\Type\Categories\CategoriesNameType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\CategoryRepository;
use App\Routing\AccessLevel;
use App\Routing\MessagesSubModule;
use App\Routing\SchemaRoute;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class CategoriesAddController extends AbstractController
{
  #[SchemaRoute(
    access_level: AccessLevel::Admin,
    module: MessagesSubModule::Categories,
    path: '/categories/add',
    name: 'categories_add',
    methods: ['GET', 'POST'],
  )]

  public function __invoke(
    Request $request,
    CategoryRepository $category_repository,
    PageParamsService $pp,
    SessionUserService $su,
  ):Response
  {
    $command = new CategoriesNameCommand();
    $command->id = 0;

    $form = $this->createForm(CategoriesNameType::class, $command);
    $form->handleRequest($request);

    if ($form->isSubmitted()
      && $form->isValid())
    {
      $name= $command->name;

		  $created_by = $su->is_master() ? null : $su->id();

      $category_repository->insert(
        name: $name,
        created_by: $created_by,
        schema: $pp->schema_o(),
      );

      $this->addFlash('success', 'Categorie "' . $name . '" toegevoegd.');
      return $this->redirectToRoute('categories', $pp->ary());
    }

    return $this->render('categories/categories_add.html.twig', [
      'form'  => $form->createView(),
    ]);
  }
}
