<?php declare(strict_types=1);

namespace App\Controller\Categories;

use App\Command\Categories\CategoriesNameCommand;
use App\Form\Type\Categories\CategoriesNameType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\CategoryRepository;
use App\Routing\AccessLevel;
use App\Routing\MessagesSubModule;
use App\Routing\SchemaRoute;
use App\Service\PageParamsService;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class CategoriesEditController extends AbstractController
{
  #[SchemaRoute(
    access_level: AccessLevel::Admin,
    module: MessagesSubModule::Categories,
    path: '/categories/{id}/edit',
    name: 'categories_edit',
    methods: ['GET', 'POST'],
  )]

  public function __invoke(
    Request $request,
    int $id,
    CategoryRepository $category_repository,
    PageParamsService $pp,
  ):Response
  {

    $category = $category_repository->get(
      id: $id,
      schema: $pp->schema_o(),
    );

    if ($category === false)
    {
      throw $this->createNotFoundException(
        'Category ' . $id . ' not found.'
      );
    }

    $command = new CategoriesNameCommand();
    $command->id = $id;
    $command->name = $category['name'];

    $form = $this->createForm(CategoriesNameType::class, $command);
    $form->handleRequest($request);

    if ($form->isSubmitted()
      && $form->isValid())
    {
      $name= $command->name;

      $category_repository->update_name(
        id: $id,
        name: $name,
        schema: $pp->schema_o(),
      );

      $this->addFlash('success', 'Naam van Categorie aangepast van "' . $category['name'] . '" naar "' . $name . '".');

      return $this->redirectToRoute('categories', $pp->ary());
    }

    return $this->render('categories/categories_edit.html.twig', [
      'form'      => $form->createView(),
      'category'  => $category,
    ]);
  }
}
