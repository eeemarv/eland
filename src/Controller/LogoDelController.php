<?php declare(strict_types=1);

namespace App\Controller;

use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Annotation\Route;

class LogoDelController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/logo/del',
        name: 'logo_del',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'module'        => 'config',
        ],
    )]

    public function __invoke(
        Request $request,
        ConfigService $config_service,
        AlertService $alert_service,
        HeadingRender $heading_render,
        LinkRender $link_render,
        MenuService $menu_service,
        PageParamsService $pp,
        string $env_s3_url
    ):Response
    {
        $logo = $config_service->get('logo', $pp->schema());

        if ($logo == '' || !$logo)
        {
            throw new ConflictHttpException('Er is geen logo ingesteld.');
        }

        if ($request->isMethod('POST'))
        {
            $config_service->set_str('system.logo', '', $pp->schema());
            $alert_service->success('Logo verwijderd.');
            $link_render->redirect('config', $pp->ary(), ['tab' => 'logo']);
        }

        $heading_render->add('Logo verwijderen?');

        $out = '<div class="row">';
        $out .= '<div class="col-xs-6">';
        $out .= '<div class="thumbnail img-upload">';
        $out .= '<img src="';
        $out .= $env_s3_url . $logo;
        $out .= '" class="img-rounded">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '</div>';

        $out .= '<form method="post">';

        $out .= '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= $link_render->btn_cancel('config', $pp->ary(), ['tab' => 'logo']);

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger btn-lg">';

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('config');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
