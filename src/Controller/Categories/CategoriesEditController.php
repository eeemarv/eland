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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class CategoriesEditController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/categories/{id}/edit',
        name: 'categories_edit',
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
        PageParamsService $pp
    ):Response
    {
        $errors = [];

        if (!$config_service->get_bool('messages.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('messages (offer/want) module not enabled.');
        }

        if (!$config_service->get_bool('messages.fields.category.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Categories module not enabled.');
        }

        $category = $db->fetchAssociative('select *
            from ' . $pp->schema() . '.categories
            where id = ?', [$id], [\PDO::PARAM_INT]);

        if ($category === false)
        {
            throw new NotFoundException('Category with id ' . $id . ' not found.');
        }

        $name = $category['name'];

        if ($request->isMethod('POST'))
        {
            $old_name = $name;
            $name = $request->request->get('name', '');

            if ($token_error = $form_token_service->get_error())
            {
                $errors[] = $token_error;
            }

            if (!$name === '')
            {
                $errors[] = 'Vul een naam in!';
            }

            if (strlen($name) > 40)
            {
                $errors[] = 'De naam mag maximaal 40 tekens lang zijn.';
            }

            if (!count($errors))
            {
                $db->update($pp->schema() . '.categories', [
                    'name'  => $name,
                ], ['id' => $id]);

                $alert_service->success('Naam van Categorie aangepast van "' . $old_name . '" naar "' . $name . '".');
                $link_render->redirect('categories', $pp->ary(), []);
            }

            $alert_service->error($errors);
        }

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="post">';

        $out .= '<div class="form-group">';
        $out .= '<label for="name" class="control-label">';
        $out .= 'Naam</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<span class="fa fa-clone"></span></span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="name" name="name" ';
        $out .= 'value="';
        $out .= $name ?? '';
        $out .= '" required>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= $link_render->btn_cancel('categories', $pp->ary(), []);

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Opslaan" ';
        $out .= 'name="zend" class="btn btn-primary btn-lg">';
        $out .= $form_token_service->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('categories');

        return $this->render('categories/categories_edit.html.twig', [
            'content'   => $out,
            'category'  => $category,
        ]);
    }
}
