<?php declare(strict_types=1);

namespace App\Controller\Categories;

use App\Command\Categories\CategoriesNameCommand;
use App\Form\Type\Categories\CategoriesNameType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Repository\CategoryRepository;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class CategoriesAddController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/categories/add',
        name: 'categories_add',
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
        PageParamsService $pp,
        SessionUserService $su
    ):Response
    {
        if (!$config_service->get_bool('messages.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('messages (offer/want) module not enabled.');
        }

        if (!$config_service->get_bool('messages.fields.category.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Categories module not enabled.');
        }

        $command = new CategoriesNameCommand();
        $command->id = 0;

        $form = $this->createForm(CategoriesNameType::class, $command);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();
            $name= $command->name;

            $category_repository->insert($name, $su, $pp->schema());

            $alert_service->success('Categorie "' . $name . '" toegevoegd.');
            return $this->redirectToRoute('categories', $pp->ary());
        }

        return $this->render('categories/categories_add.html.twig', [
            'form'  => $form->createView(),
        ]);
    }
}
