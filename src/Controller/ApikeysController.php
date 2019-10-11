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

class ApikeysController extends AbstractController
{
    public function apikeys(
        Db $db,
        MenuService $menu_service,
        HeadingRender $heading_render,
        BtnTopRender $btn_top_render,
        LinkRender $link_render,
        DateFormatService $date_format_service
    ):Response
    {
        $apikeys = $db->fetchAll('select *
            from ' . $pp->schema() . '.apikeys');

        $btn_top_render->add('apikeys_add', $pp->ary(), [], 'Apikey toevoegen');

        $heading_render->add('Apikeys');
        $heading_render->fa('key');

        $out = self::get_apikey_explain();

        $out .= '<div class="panel panel-default printview">';

        $out .= '<div class="table-responsive">';
        $out .= '<table class="table table-bordered table-hover table-striped footable">';
        $out .= '<thead>';
        $out .= '<tr>';
        $out .= '<th>Id</th>';
        $out .= '<th>Commentaar</th>';
        $out .= '<th data-hide="phone">Apikey</th>';
        $out .= '<th data-hide="phone, tablet" data-sort-initial="true">Gecreëerd</th>';
        $out .= '<th data-hide="phone, tablet" data-sort-ignore="true">Verwijderen</th>';
        $out .= '</tr>';
        $out .= '</thead>';

        $out .= '<tbody>';

        foreach($apikeys as $a)
        {
            $td = [];
            $td[] = $a['id'];
            $td[] = $a['comment'];
            $td[] = $a['apikey'];
            $td[] = $date_format_service->get_td($a['created'], 'min', $pp->schema());
            $td[] = $link_render->link_fa('apikeys_del', $pp->ary(),
                ['id' => $a['id']], 'Verwijderen',
                ['class' => 'btn btn-danger'], 'times');

            $out .= '<tr><td>';
            $out .= implode('</td><td>', $td);
            $out .= '</td></tr>';
        }

        $out .= '</tbody>';
        $out .= '</table>';
        $out .= '</div></div>';

        $menu_service->set('apikeys');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }

    public function apikeys_add(
        Request $request,
        Db $db,
        AlertService $alert_service,
        MenuService $menu_service,
        HeadingRender $heading_render,
        LinkRender $link_render,
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

        $out = self::get_apikey_explain();

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

    private static function get_apikey_explain():string
    {
        $out = '<p>';
        $out .= '<ul>';
        $out .= '<li>';
        $out .= 'Apikeys zijn enkel nodig voor het leggen van ';
        $out .= 'interSysteem verbindingen naar andere Systemen die ';
        $out .= 'eLAS draaien.</li>';
        $out .= '<li>Voor het leggen van interSysteem ';
        $out .= 'verbindingen naar andere Systemen op ';
        $out .= 'deze eLAND-server ';
        $out .= 'moet je geen Apikey aanmaken.';
        $out .= '</li></ul>';
        $out .= '</p>';
        return $out;
    }

    public function apikeys_del(
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
