<?php declare(strict_types=1);

namespace App\Controller\Categories;

use App\Form\Post\DelType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use App\Service\AlertService;
use App\Service\MenuService;
use App\Service\FormTokenService;
use App\Render\LinkRender;
use App\Repository\CategoryRepository;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CategoriesDelController extends AbstractController
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

        $category = $category_repository->get_with_messages_count($id, $pp->schema());

        if ($category['count'] !== 0)
        {
            throw new ConflictHttpException('A category containing messages cannot be deleted.');
        }

        if (($category['left_id'] + 1) !== $category['right_id'])
        {
            throw new ConflictHttpException('A category containing categories cannot be deleted.');
        }

        $form = $this->createForm(DelType::class)
            ->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $category_repository->del($id, $pp->schema());
            $alert_service->success('categories_del.success', [
                '%name%'    => $category['name'],
            ]);
            $link_render->redirect('categories', $pp->ary(), []);
        }

        $menu_service->set('categories');

        return $this->render('categories/categories_del.html.twig', [
            'form'      => $form->createView(),
            'category'  => $category,
            'schema'    => $pp->schema(),
        ]);
    }
}
