<?php declare(strict_types=1);

namespace App\Controller\News;

use App\Render\AccountRender;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\ConfigService;
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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class NewsDelController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/news/{id}/del',
        name: 'news_del',
        methods: ['GET', 'POST'],
        requirements: [
            'id'            => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'module'        => 'news',
        ],
    )]

    public function __invoke(
        Request $request,
        int $id,
        Db $db,
        ConfigService $config_service,
        FormTokenService $form_token_service,
        AccountRender $account_render,
        AlertService $alert_service,
        DateFormatService $date_format_service,
        ItemAccessService $item_access_service,
        LinkRender $link_render,
        MenuService $menu_service,
        PageParamsService $pp,
        VarRouteService $vr
    ):Response
    {
        if (!$config_service->get_bool('news.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('News module not enabled.');
        }

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

        $news_item = $db->fetchAssociative('select n.*
            from ' . $pp->schema() . '.news n
            where n.id = ?', [$id]);

        $out = NewsExtendedController::render_news_item(
            $news_item,
            true,
            false,
            false,
            $pp,
            $link_render,
            $account_render,
            $date_format_service,
            $item_access_service
        );

        $out .= '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

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

        return $this->render('news/news_del.html.twig', [
            'content'   => $out,
            'news_item' => $news_item,
            'schema'    => $pp->schema(),
        ]);
    }
}
