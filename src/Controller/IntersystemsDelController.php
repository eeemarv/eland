<?php declare(strict_types=1);

namespace App\Controller;

use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\FormTokenService;
use App\Service\IntersystemsService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;

class IntersystemsDelController extends AbstractController
{
    public function intersystems_del(
        Request $request,
        int $id,
        Db $db,
        HeadingRender $heading_render,
        IntersystemsService $intersystems_service,
        LinkRender $link_render,
        AlertService $alert_service,
        FormTokenService $form_token_service,
        PageParamsService $pp,
        MenuService $menu_service
    ):Response
    {
        $group = $db->fetchAssoc('select *
            from ' . $pp->schema() . '.letsgroups
            where id = ?', [$id]);

        if (!$group)
        {
            $alert_service->error('Systeem niet gevonden.');
            $link_render->redirect('intersystems', $pp->ary(), []);
        }

        if ($request->isMethod('POST'))
        {
            if ($error_token = $form_token_service->get_error())
            {
                $alert_service->error($error_token);
                $link_render->redirect('intersystems', $pp->ary(), []);
            }

            if($db->delete($pp->schema() . '.letsgroups', ['id' => $id]))
            {
                $alert_service->success('InterSysteem verwijderd.');

                $intersystems_service->clear_cache($pp->schema());

                $link_render->redirect('intersystems', $pp->ary(), []);
            }

            $alert_service->error('InterSysteem niet verwijderd.');
        }

        $heading_render->add('InterSysteem verwijderen: ' . $group['groupname']);
        $heading_render->fa('share-alt');

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<p class="text-danger">Ben je zeker dat dit interSysteem ';
        $out .= 'moet verwijderd worden?</p>';
        $out .= '<div><p>';
        $out .= '<form method="post">';

        $out .= $link_render->btn_cancel('intersystems', $pp->ary(), []);

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger btn-lg">';
        $out .= $form_token_service->get_hidden_input();

        $out .= '</form></p>';
        $out .= '</div>';

        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('intersystems');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
