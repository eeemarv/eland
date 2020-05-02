<?php declare(strict_types=1);

namespace App\Controller;

use App\Render\AccountRender;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\DateFormatService;
use App\Service\FormTokenService;
use App\Service\ItemAccessService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\VarRouteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;

class NewsDelController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $id,
        Db $db,
        FormTokenService $form_token_service,
        AccountRender $account_render,
        AlertService $alert_service,
        DateFormatService $date_format_service,
        HeadingRender $heading_render,
        ItemAccessService $item_access_service,
        LinkRender $link_render,
        MenuService $menu_service,
        PageParamsService $pp,
        VarRouteService $vr
    ):Response
    {
        if ($request->isMethod('POST'))
        {
            if ($error_token = $form_token_service->get_error())
            {
                $alert_service->error($error_token);
                $link_render->redirect($vr->get('news'), $pp->ary(), []);
            }

            if($db->delete($pp->schema() . '.news',

            ['id' => $id]))
            {
                $alert_service->success('Nieuwsbericht verwijderd.');
                $link_render->redirect($vr->get('news'), $pp->ary(), []);
            }

            $alert_service->error('Nieuwsbericht niet verwijderd.');
        }

        $news = $db->fetchAssoc('select n.*
            from ' . $pp->schema() . '.news n
            where n.id = ?', [$id]);

        $heading_render->add('Nieuwsbericht ' . $news['subject'] . ' verwijderen?');
        $heading_render->fa('calendar-o');

        $out = NewsExtendedController::render_news_item(
            $news,
            true,
            false,
            false,
            $pp,
            $link_render,
            $account_render,
            $date_format_service,
            $item_access_service
        );

        $out .= '<div class="card bg-info">';
        $out .= '<div class="card-body">';

        $out .= '<p class="text-danger"><strong>';
        $out .= 'Ben je zeker dat dit nieuwsbericht ';
        $out .= 'moet verwijderd worden?</strong></p>';

        $out .= '<form method="post">';
        $out .= $link_render->btn_cancel('news_show', $pp->ary(), ['id' => $id]);
        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Verwijderen" ';
        $out .= 'name="zend" class="btn btn-danger btn-lg">';
        $out .= $form_token_service->get_hidden_input();
        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('news');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
