<?php declare(strict_types=1);

namespace App\Controller\Categories;

use App\Cache\ConfigCache;
use App\Command\Categories\CategoriesNameCommand;
use App\Form\Type\Categories\CategoriesNameType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Repository\CategoryRepository;
use App\Service\PageParamsService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class CategoriesDelController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/categories/{id}/del',
        name: 'categories_del',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
            'id'            => '%assert.id%',
        ],
        defaults: [
            'module'        => 'messages',
            'sub_module'    => 'categories'
        ],
    )]

    public function __invoke(
        Request $request,
        int $id,
        CategoryRepository $category_repository,
        ConfigCache $config_cache,
        AlertService $alert_service,
        PageParamsService $pp
    ):Response
    {
        if (!$config_cache->get_bool('messages.fields.category.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Categories module not enabled.');
        }

        if (!$config_cache->get_bool('messages.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('messages (offer/want) module not enabled.');
        }

        $category = $category_repository->get_with_messages_count($id, $pp->schema());

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
            $category_repository->del($id, $pp->schema());

            $alert_service->success('Categorie "' . $category['name'] . '" verwijderd.');

            return $this->redirectToRoute('categories', $pp->ary());
        }

        return $this->render('categories/categories_del.html.twig', [
            'form'      => $form->createView(),
        ]);
    }
}
