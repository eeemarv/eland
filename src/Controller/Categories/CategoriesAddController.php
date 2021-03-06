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
        Db $db,
        ConfigService $config_service,
        AlertService $alert_service,
        FormTokenService $form_token_service,
        MenuService $menu_service,
        LinkRender $link_render,
        PageParamsService $pp,
        SessionUserService $su
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

        if ($request->isMethod('POST'))
        {
            $name = $request->request->get('name', '');

            if ($token_error = $form_token_service->get_error())
            {
                $errors[] = $token_error;
            }

            if (trim($name) === '')
            {
                $errors[] = 'Vul naam in!';
            }

            if (strlen($name) > 40)
            {
                $errors[] = 'De naam mag maximaal 40 tekens lang zijn.';
            }

            if (!count($errors))
            {
                $created_by = $su->is_master() ? null : $su->id();

                $db->executeStatement('insert into ' . $pp->schema() . '.categories (name, created_by, level, left_id, right_id)
                    select ?, ?, 1, coalesce(max(right_id), 0) + 1, coalesce(max(right_id), 0) + 2
                    from ' . $pp->schema() . '.categories',
                    [$name, $created_by],
                    [\PDO::PARAM_STR, \PDO::PARAM_INT]);

                $alert_service->success('Categorie "' . $name . '" toegevoegd.');
                $link_render->redirect('categories', $pp->ary(), []);
            }

            $alert_service->error($errors);
        }

        $out = '<p>De nieuwe categorie wordt aan het einde van de ';
        $out .= 'lijst toegevoegd en kan nadien verplaatst worden.</p>';

        $out .= '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form  method="post">';

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
        $out .= '" required maxlength="40">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= $link_render->btn_cancel('categories', $pp->ary(), []);
        $out .= '&nbsp;';
        $out .= '<input type="submit" name="zend" value="Toevoegen" ';
        $out .= 'class="btn btn-success btn-lg">';
        $out .= $form_token_service->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('categories');

        return $this->render('categories/categories_add.html.twig', [
            'content'   => $out,
        ]);
    }
}
