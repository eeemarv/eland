<?php declare(strict_types=1);

namespace App\Controller\Config;

use App\Form\Post\DelType;
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

class ConfigLogoDelController extends AbstractController
{
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
            throw new ConflictHttpException('No logo defined.');
        }

        $form = $this->createForm(DelType::class)
            ->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $config_service->set_str('system.logo', '', $pp->schema());
            $alert_service->success('config_logo_del.success');
            $link_render->redirect('config_logo', $pp->ary(), []);
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

        $out .= '<div class="card fcard fcard-info">';
        $out .= '<div class="card-body">';

        $out .= '<form method="post">';

        $out .= $link_render->btn_cancel('config', $pp->ary(), ['tab' => 'logo']);

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger btn-lg">';

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('config');

        return $this->render('config/config_logo_del.html.twig', [
            'content'   => $out,
            'form'      => $form->createView(),
            'schema'    => $pp->schema(),
        ]);
    }
}