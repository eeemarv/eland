<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class LogoDelController extends AbstractController
{
    public function logo_del(Request $request, app $app):Response
    {
        $logo = $config_service->get('logo', $app['pp_schema']);

        if ($logo == '' || !$logo)
        {
            throw new ConflictHttpException('Er is geen logo ingesteld.');
        }

        if ($request->isMethod('POST'))
        {
            $config_service->set('logo', $app['pp_schema'], '');
            $config_service->set('logo_width', $app['pp_schema'], '0');

            $alert_service->success('Logo verwijderd.');
            $link_render->redirect('config', $app['pp_ary'], ['tab' => 'logo']);
        }

        $heading_render->add('Logo verwijderen?');

        $out = '<div class="row">';
        $out .= '<div class="col-xs-6">';
        $out .= '<div class="thumbnail">';
        $out .= '<img src="';
        $out .= $app['s3_url'] . $logo;
        $out .= '" class="img-rounded">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '</div>';

        $out .= '<form method="post">';

        $out .= '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= $link_render->btn_cancel('config', $app['pp_ary'], ['tab' => 'logo']);

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger btn-lg">';

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('config');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $app['pp_schema'],
        ]);
    }
}
