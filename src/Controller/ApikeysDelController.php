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
use App\Render\BtnTopRender;
use App\Render\LinkRender;
use App\Service\DateFormatService;
use App\Service\PageParamsService;

class ApikeysDelController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $id,
        Db $db,
        AlertService $alert_service,
        MenuService $menu_service,
        HeadingRender $heading_render,
        LinkRender $link_render,
        FormTokenService $form_token_service,
        PageParamsService $pp
    ):Response
    {
        if($request->isMethod('POST'))
        {
            if ($error_token = $form_token_service->get_error())
            {
                $alert_service->error($error_token);
                $link_render->redirect('apikeys', $pp->ary(), []);
            }

            if ($db->delete($pp->schema() . '.apikeys',
                ['id' => $id]))
            {
                $alert_service->success('Apikey verwijderd.');
                $link_render->redirect('apikeys', $pp->ary(), []);
            }

            $alert_service->error('Apikey niet verwijderd.');
        }

        $apikey = $db->fetchAssoc('select *
            from ' . $pp->schema() . '.apikeys
            where id = ?', [$id]);

        $heading_render->add('Apikey verwijderen?');
        $heading_render->fa('key');

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="post">';
        $out .= '<dl>';
        $out .= '<dt>Apikey</dt>';
        $out .= '<dd>';
        $out .= $apikey['apikey'] ?: '<i class="fa fa-times"></i>';
        $out .= '</dd>';
        $out .= '<dt>Commentaar</dt>';
        $out .= '<dd>';
        $out .= $apikey['comment'] ?: '<i class="fa fa-times"></i>';
        $out .= '</dd>';
        $out .= '</dl>';
        $out .= $link_render->btn_cancel('apikeys', $pp->ary(), []);
        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Verwijderen" ';
        $out .= 'name="zend" class="btn btn-danger btn-lg">';
        $out .= $form_token_service->get_hidden_input();
        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('apikeys');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
