<?php declare(strict_types=1);

namespace App\Controller\Categories;

use App\Command\Categories\CategoriesListCommand;
use App\Form\Type\Categories\CategoriesListType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\CategoryRepository;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class CategoriesController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/categories',
        name: 'categories',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'module'        => 'messages',
            'sub_module'    => 'categories',
        ],
    )]

    public function __invoke(
        Request $request,
        CategoryRepository $category_repository,
        ConfigService $config_service,
        AlertService $alert_service,
        PageParamsService $pp
    ):Response
    {
        if (!$config_service->get_bool('messages.fields.category.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Categories module not enabled.');
        }

        if (!$config_service->get_bool('messages.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('messages (offer/want) module not enabled.');
        }

        $fetch = $category_repository->get_list_and_input_ary($pp->schema());
        $categories = $fetch['categories'];
        $input_ary = $fetch['input_ary'];

        $command = new CategoriesListCommand();

        $command->categories = json_encode($input_ary);
        $form_options = ['validation_groups' => ['edit']];

        $form = $this->createForm(CategoriesListType::class, $command, $form_options)
            ->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();
            $categories_json = $command->categories;
            $posted_categories = json_decode($categories_json, true);

            $update_count = $category_repository->update_list($posted_categories, $pp->schema());

            if ($update_count)
            {
                $alert_service->success('Plaatsing categorieën aangepast.');
            }
            else
            {
                $alert_service->warning('Geen aangepaste plaatsing van categorieën');
            }

            return $this->redirectToRoute('categories', $pp->ary());
        }

        return $this->render('categories/categories_list.html.twig', [
            'form'          => $form->createView(),
            'categories'    => $categories,
        ]);
    }
}
