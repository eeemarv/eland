<?php declare(strict_types=1);

namespace App\Controller\Categories;

use App\Command\Categories\CategoriesNameCommand;
use App\Form\Post\Categories\CategoriesNameType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Render\LinkRender;
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
        LinkRender $link_render,
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

            $alert_service->success('Categorie "' . $name . '" toegevoegd.');
            $link_render->redirect('categories', $pp->ary(), []);
        }

        /*
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
        */

        return $this->render('categories/categories_add.html.twig', [
            'form'  => $form->createView(),
//            'content'   => $out,
        ]);
    }
}
