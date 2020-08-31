<?php declare(strict_types=1);

namespace App\Controller\Categories;

use App\Command\Categories\CategoriesNameCommand;
use App\Form\Post\Categories\CategoriesNameType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\MenuService;
use App\Render\LinkRender;
use App\Repository\CategoryRepository;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CategoriesEditController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $id,
        CategoryRepository $category_repository,
        ConfigService $config_service,
        AlertService $alert_service,
        MenuService $menu_service,
        LinkRender $link_render,
        PageParamsService $pp
    ):Response
    {
        if (!$config_service->get_bool('messages.fields.category.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Categories module not enabled.');
        }

        $category = $category_repository->get($id, $pp->schema());

        $categories_name_command = new CategoriesNameCommand();
        $categories_name_command->id = $id;
        $categories_name_command->name = $category['name'];

        $form = $this->createForm(CategoriesNameType::class,
                $categories_name_command)
            ->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $categories_name_command = $form->getData();
            $name= $categories_name_command->name;

            $category_repository->update_name($id, $name, $pp->schema());

            $alert_service->success('categories_edit.success', [
                '%old_name%'    => $category['name'],
                '%new_name%'    => $name,
            ]);

            $link_render->redirect('categories', $pp->ary(), []);
        }

        $menu_service->set('categories');

        return $this->render('categories/categories_edit.html.twig', [
            'form'      => $form->createView(),
            'category'  => $category,
            'schema'    => $pp->schema(),
        ]);
    }
}
