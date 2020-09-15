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
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CategoriesAddController extends AbstractController
{
    public function __invoke(
        Request $request,
        CategoryRepository $category_repository,
        ConfigService $config_service,
        AlertService $alert_service,
        MenuService $menu_service,
        LinkRender $link_render,
        PageParamsService $pp,
        SessionUserService $su
    ):Response
    {
        if (!$config_service->get_bool('messages.fields.category.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Categories module not enabled.');
        }

        $categories_name_command = new CategoriesNameCommand();
        $categories_name_command->id = 0;

        $form = $this->createForm(CategoriesNameType::class,
                $categories_name_command)
            ->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $categories_name_command = $form->getData();
            $name= $categories_name_command->name;

            $category_repository->insert($name, $su, $pp->schema());

            $alert_service->success('categories_add.success', [
                '%name%' => $name,
            ]);

            $link_render->redirect('categories', $pp->ary(), []);
        }

        $menu_service->set('categories');

        return $this->render('categories/categories_add.html.twig', [
            'form'      => $form->createView(),
            'schema'    => $pp->schema(),
        ]);
    }
}
