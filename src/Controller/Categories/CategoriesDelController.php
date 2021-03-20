<?php declare(strict_types=1);

namespace App\Controller\Categories;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use App\Service\AlertService;
use App\Service\MenuService;
use App\Service\FormTokenService;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Http\Discovery\Exception\NotFoundException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

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
        Db $db,
        ConfigService $config_service,
        AlertService $alert_service,
        FormTokenService $form_token_service,
        MenuService $menu_service,
        LinkRender $link_render,
        PageParamsService $pp,
        HeadingRender $heading_render
    ):Response
    {
        $errors = [];

        if (!$config_service->get_bool('messages.fields.category.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Categories module not enabled.');
        }

        if (!$config_service->get_bool('messages.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('messages (offer/want) module not enabled.');
        }

        $message_count = $db->fetchOne('select count(*)
            from ' . $pp->schema() . '.messages
            where category_id = ?', [$id], [\PDO::PARAM_INT]);

        if ($message_count !== 0)
        {
            throw new ConflictHttpException('A category containing messages cannot be deleted.');
        }

        $category = $db->fetchAssociative('select name, left_id, right_id
            from ' . $pp->schema() . '.categories
            where id = ?',
            [$id], [\PDO::PARAM_INT]);

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
                $db->executeStatement('update ' . $pp->schema() . '.categories
                    set left_id = left_id - 2
                    where left_id > ?', [$left_id], [\PDO::PARAM_INT]);
                $db->executeStatement('update ' . $pp->schema() . '.categories
                    set right_id = right_id - 2
                    where right_id > ?', [$right_id], [\PDO::PARAM_INT]);
                $db->delete($pp->schema() . '.categories', ['id' => $id]);
                $db->commit();
                $alert_service->success('Categorie "' . $name . '" verwijderd.');
                $link_render->redirect('categories', $pp->ary(), []);
            }

            $alert_service->error($errors);
        }

        $heading_render->add('Categorie verwijderen : ');
        $heading_render->add($name);
        $heading_render->fa('clone');

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= "<p><font color='#F56DB5'><strong>Ben je zeker dat deze categorie";
        $out .= " moet verwijderd worden?</strong></font></p>";
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

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
