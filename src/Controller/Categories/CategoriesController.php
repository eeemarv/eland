<?php declare(strict_types=1);

namespace App\Controller\Categories;

use App\Command\Categories\CategoriesListCommand;
use App\Form\Post\Categories\CategoriesListType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Service\MenuService;
use App\Render\BtnTopRender;
use App\Render\LinkRender;
use App\Repository\CategoryRepository;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CategoriesController extends AbstractController
{
    public function __invoke(
        Request $request,
        CategoryRepository $category_repository,
        ConfigService $config_service,
        AlertService $alert_service,
        MenuService $menu_service,
        LinkRender $link_render,
        BtnTopRender $btn_top_render,
        PageParamsService $pp
    ):Response
    {
        if (!$config_service->get_bool('messages.fields.category.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Categories module not enabled.');
        }

        $fetch = $category_repository->get_list_and_input_ary($pp->schema());
        $categories = $fetch['categories'];
        $input_ary = $fetch['input_ary'];

        $categories_list_command = new CategoriesListCommand();

        $categories_list_command->categories = json_encode($input_ary);

        $form = $this->createForm(CategoriesListType::class, $categories_list_command)
            ->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $categories_list_command = $form->getData();
            $categories_json = $categories_list_command->categories;
            $posted_categories = json_decode($categories_json, true);

            $update_count = $category_repository->update_list($posted_categories, $pp->schema());

            if ($update_count)
            {
                $alert_service->success('categories.success', [
                    '%count%' => $update_count,
                ]);
            }
            else
            {
                $alert_service->warning('categories.warning.no_change');
            }

            $link_render->redirect('categories', $pp->ary(), []);
        }

        $btn_top_render->add('categories_add',
            $pp->ary(), [], 'Categorie toevoegen');

        $menu_service->set('categories');

        return $this->render('categories/categories_list.html.twig', [
            'categories'    => $categories,
            'form'          => $form->createView(),
            'schema'        => $pp->schema(),
        ]);
    }
}
