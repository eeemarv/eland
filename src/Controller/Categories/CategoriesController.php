<?php declare(strict_types=1);

namespace App\Controller\Categories;

use App\Command\Categories\CategoriesListCommand;
use App\Command\Messages\MessagesCleanupCommand;
use App\Form\Type\Categories\CategoriesListType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\CategoryRepository;
use App\Routing\AccessLevel;
use App\Routing\MessagesSubModule;
use App\Routing\SchemaRoute;
use App\Service\PageParamsService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class CategoriesController extends AbstractController
{
  #[SchemaRoute(
    access_level: AccessLevel::Admin,
    module: MessagesSubModule::Categories,
    path: '/categories',
    name: 'categories',
    methods: ['GET', 'POST'],
  )]

  public function __invoke(
    Request $request,
    CategoryRepository $category_repository,
    PageParamsService $pp,
  ):Response
  {

    $fetch = $category_repository->get_list_and_input_ary(
      schema: $pp->schema_o(),
    );
    $categories = $fetch['categories'];
    $input_ary = $fetch['input_ary'];

    $command = new CategoriesListCommand();

    $command->categories = json_encode($input_ary);

    $form_options = [
      'validation_groups' => ['edit'],
    ];

    $form = $this->createForm(CategoriesListType::class, $command, $form_options)
      ->handleRequest($request);

    if ($form->isSubmitted()
      && $form->isValid())
    {
      $categories_json = $command->categories;
      $posted_categories = json_decode($categories_json, true);

      $update_count = $category_repository->update_list(
        posted_ary: $posted_categories,
        schema: $pp->schema_o(),
      );

      if ($update_count)
      {
        $this->addFlash('success', 'Plaatsing categorieën aangepast.');
      }
      else
      {
        $this->addFlash('warning', 'Geen aangepaste plaatsing van categorieën');
      }

      return $this->redirectToRoute('categories', $pp->ary());
    }

    return $this->render('categories/categories_list.html.twig', [
      'form'          => $form->createView(),
      'categories'    => $categories,
    ]);
  }
}
