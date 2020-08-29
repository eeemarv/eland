<?php declare(strict_types=1);

namespace App\Controller\Categories;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use App\Service\AlertService;
use App\Service\MenuService;
use App\Service\FormTokenService;
use App\Render\LinkRender;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Http\Discovery\Exception\NotFoundException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CategoriesDelController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $id,
        Db $db,
        ConfigService $config_service,
        AlertService $alert_service,
        FormTokenService $form_token_service,
        MenuService $menu_service,
        LinkRender $link_render,
        PageParamsService $pp
    ):Response
    {
        $errors = [];

        if (!$config_service->get_bool('messages.fields.category.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Categories module not enabled.');
        }

        $message_count = $db->fetchColumn('select count(*)
            from ' . $pp->schema() . '.messages
            where category_id = ?', [$id]);

        if ($message_count !== 0)
        {
            throw new ConflictHttpException('A category containing messages cannot be deleted.');
        }

        $category = $db->fetchAssoc('select name, left_id, right_id
            from ' . $pp->schema() . '.categories
            where id = ?', [$id]);

        if ($category === false)
        {
            throw new NotFoundException('Category with id ' . $id . ' not found.');
        }

        $name = $category['name'];
        $left_id = $category['left_id'];
        $right_id = $category['right_id'];

        if (($left_id + 1) !== $right_id)
        {
            throw new ConflictHttpException('A category containing categories cannot be deleted.');
        }

        if($request->isMethod('POST'))
        {
            if ($error_token = $form_token_service->get_error())
            {
                $errors[] = $error_token;
            }

            if (!count($errors))
            {
                $db->beginTransaction();
                $db->executeUpdate('update ' . $pp->schema() . '.categories
                    set left_id = left_id - 2
                    where left_id > ?', [$left_id], [\PDO::PARAM_INT]);
                $db->executeUpdate('update ' . $pp->schema() . '.categories
                    set right_id = right_id - 2
                    where right_id > ?', [$right_id], [\PDO::PARAM_INT]);
                $db->delete($pp->schema() . '.categories', ['id' => $id]);
                $db->commit();
                $alert_service->success('Categorie "' . $name . '" verwijderd.');
                $link_render->redirect('categories', $pp->ary(), []);
            }

            $alert_service->error($errors);
        }

        $out = '<div class="card fcard fcard-info">';
        $out .= '<div class="card-body">';

        $out .= '<p class="text-danger"><strong>Ben je zeker dat deze categorie';
        $out .= ' moet verwijderd worden?</strong></p>';
        $out .= '<form method="post">';

        $out .= $link_render->btn_cancel('categories', $pp->ary(), []);

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Verwijderen" ';
        $out .= 'name="zend" class="btn btn-danger btn-lg">';
        $out .= $form_token_service->get_hidden_input();
        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('categories');

        return $this->render('categories/categories_del.html.twig', [
            'content'   => $out,
            'category'  => $category,
            'schema'    => $pp->schema(),
        ]);
    }
}
