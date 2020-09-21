<?php declare(strict_types=1);

namespace App\Controller;

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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CategoriesEditController extends AbstractController
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
        HeadingRender $heading_render,
        PageParamsService $pp
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

        $name = $db->fetchColumn('select name
            from ' . $pp->schema() . '.categories
            where id = ?', [$id]);

        if ($name === false)
        {
            throw new NotFoundException('Category with id ' . $id . ' not found.');
        }

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

        $heading_render->add('Naam van categorie aanpassen : ');
        $heading_render->add($name);
        $heading_render->fa('clone');

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

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
