<?php declare(strict_types=1);

namespace App\Controller\Categories;

use App\Command\Categories\CategoriesNameCommand;
use App\Form\Post\Categories\CategoriesNameType;
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
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CategoriesAddController extends AbstractController
{
    public function __invoke(
        Request $request,
        Db $db,
        CategoryRepository $category_repository,
        ConfigService $config_service,
        AlertService $alert_service,
        FormTokenService $form_token_service,
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

////

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

                $db->executeUpdate('insert into ' . $pp->schema() . '.categories (name, created_by, level, left_id, right_id)
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

        $out .= '<div class="card fcard fcard-info">';
        $out .= '<div class="card-body">';

        $out .= '<form  method="post">';

        $out .= '<div class="form-group">';
        $out .= '<label for="name" class="control-label">';
        $out .= 'Naam</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-prepend">';
        $out .= '<span class="input-group-text">';
        $out .= '<span class="fa fa-clone"></span>';
        $out .= '</span>';
        $out .= '</span>';
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
            'schema'    => $pp->schema(),
        ]);
    }
}
