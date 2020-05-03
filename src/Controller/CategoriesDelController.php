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
use App\Service\PageParamsService;

class CategoriesDelController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $id,
        Db $db,
        AlertService $alert_service,
        FormTokenService $form_token_service,
        MenuService $menu_service,
        LinkRender $link_render,
        PageParamsService $pp,
        HeadingRender $heading_render
    ):Response
    {
        if($request->isMethod('POST'))
        {
            if ($error_token = $form_token_service->get_error())
            {
                $alert_service->error($error_token);
                $link_render->redirect('categories', $pp->ary(), []);
            }

            if ($db->delete($pp->schema() . '.categories', ['id' => $id]))
            {
                $alert_service->success('Categorie verwijderd.');
                $link_render->redirect('categories', $pp->ary(), []);
            }

            $alert_service->error('Categorie niet verwijderd.');
        }

        $fullname = $db->fetchColumn('select fullname
            from ' . $pp->schema() . '.categories
            where id = ?', [$id]);

        $heading_render->add('Categorie verwijderen : ');
        $heading_render->add($fullname);
        $heading_render->fa('clone');

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

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
