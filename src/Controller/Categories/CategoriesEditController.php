<?php declare(strict_types=1);

namespace App\Controller\Categories;

use App\Command\Categories\CategoriesNameCommand;
use App\Form\Type\Categories\CategoriesNameType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\CategoryRepository;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class CategoriesEditController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/categories/{id}/edit',
    name: 'categories_edit',
    methods: ['GET', 'POST'],
    requirements: [
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.admin%',
      'id'            => '%assert.id%',
    ],
    defaults: [
      'module'        => 'messages',
      'sub_module'    => 'categories',
    ],
  )]

  public function __invoke(
    Request $request,
    int $id,
    CategoryRepository $category_repository,
    ConfigService $config_service,
    PageParamsService $pp,
  ):Response
  {
    if (!$config_service->get_bool(
      config_id: 'messages.enabled',
      schema: $pp->schema_o(),
    ))
    {
      throw new NotFoundHttpException('messages (offer/want) module not enabled.');
    }

    if (!$config_service->get_bool(
      config_id: 'messages.fields.category.enabled',
      schema: $pp->schema_o(),
    ))
    {
      throw new NotFoundHttpException('Categories module not enabled.');
    }

    $category = $category_repository->get(
      id: $id,
      schema: $pp->schema_o(),
    );

    $command = new CategoriesNameCommand();
    $command->id = $id;
    $command->name = $category['name'];

    $form = $this->createForm(CategoriesNameType::class, $command);
    $form->handleRequest($request);

    if ($form->isSubmitted()
      && $form->isValid())
    {
      $command = $form->getData();
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
