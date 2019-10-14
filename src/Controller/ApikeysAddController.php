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

class ApikeysAddController extends AbstractController
{
    public function __invoke(
        Request $request,
        Db $db,
        AlertService $alert_service,
        MenuService $menu_service,
        HeadingRender $heading_render,
        LinkRender $link_render,
        PageParamsService $pp,
        FormTokenService $form_token_service
    ):Response
    {
        if ($request->isMethod('POST'))
        {
            if ($error_token = $form_token_service->get_error())
            {
                $alert_service->error($error_token);
                $link_render->redirect('apikeys', $pp->ary(), []);
            }

            $apikey = [
                'apikey' 	=> $request->request->get('apikey', ''),
                'comment'	=> $request->request->get('comment', ''),
                'type'		=> 'interlets',
            ];

            if($db->insert($pp->schema() . '.apikeys', $apikey))
            {
                $alert_service->success('Apikey opgeslagen.');
                $link_render->redirect('apikeys', $pp->ary(), []);
            }

            $alert_service->error('Apikey niet opgeslagen.');
        }

        $key = sha1(random_bytes(16));

        $heading_render->add('Apikey toevoegen');
        $heading_render->fa('key');

        $out = ApikeysController::get_apikey_explain();

        $out .= '<div class="panel panel-info" id="add">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="post">';

        $out .= '<div class="form-group">';
        $out .= '<label for="apikey" class="control-label">';
        $out .= 'Apikey</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon" id="name_addon">';
        $out .= '<span class="fa fa-key"></span></span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="apikey" name="apikey" ';
        $out .= 'value="';
        $out .= $key;
        $out .= '" required readonly>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="comment" class="control-label">';
        $out .= 'Commentaar</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon" id="name_addon">';
        $out .= '<span class="fa fa-comment-o"></span></span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="comment" name="comment" ';
        $out .= 'value="';
        $out .= $apikey['comment'] ?? '';
        $out .= '">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= $link_render->btn_cancel('apikeys', $pp->ary(), []);
        $out .= '&nbsp;';
        $out .= '<input type="submit" name="zend" ';
        $out .= 'value="Opslaan" class="btn btn-success btn-lg">';
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
