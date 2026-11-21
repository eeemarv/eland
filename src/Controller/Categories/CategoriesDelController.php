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
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

#[AsController]
class CategoriesDelController extends AbstractController
{
  #[SchemaRoute(
    access_level: AccessLevel::Admin,
    module: MessagesSubModule::Categories,
    path: '/categories/{id}/del',
    name: 'categories_del',
    methods: ['GET', 'POST'],
  )]

  public function __invoke(
    Request $request,
    int $id,
    CategoryRepository $category_repository,
    PageParamsService $pp,
  ):Response
  {

    $category = $category_repository->get_with_messages_count(
      id: $id,
      schema: $pp->schema_o(),
    );

    if ($category === false)
    {
      throw $this->createNotFoundException(
        'Category ' . $id . ' not found.'
      );
    }

    if ($category['count'] !== 0)
    {
      throw new ConflictHttpException('A category containing messages cannot be deleted.');
    }

    if (($category['left_id'] + 1) !== $category['right_id'])
    {
      throw new ConflictHttpException('A category containing categories cannot be deleted.');
    }

    $command = new CategoriesNameCommand();
    $command->id = $id;
    $command->name = $category['name'];

    $form = $this->createForm(CategoriesNameType::class, $command, [
      'del'   => true,
    ]);
    $form->handleRequest($request);

    if ($form->isSubmitted()
      && $form->isValid())
    {
      $category_repository->del(
        id: $id,
        schema: $pp->schema_o(),
      );

      $this->addFlash('success', 'Categorie "' . $category['name'] . '" verwijderd.');

      return $this->redirectToRoute('categories', $pp->ary());
    }

    return $this->render('categories/categories_del.html.twig', [
      'form'      => $form->createView(),
    ]);
  }
}
