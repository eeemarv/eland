<?php declare(strict_types=1);

namespace App\Controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class logo_del
{
    public function logo_del(Request $request, app $app):Response
    {
        $logo = $app['config']->get('logo', $app['pp_schema']);

        if ($logo == '' || !$logo)
        {
            throw new ConflictHttpException('Er is geen logo ingesteld.');
        }

        if ($request->isMethod('POST'))
        {
            $app['config']->set('logo', $app['pp_schema'], '');
            $app['config']->set('logo_width', $app['pp_schema'], '0');

            $app['alert']->success('Logo verwijderd.');
            $app['link']->redirect('config', $app['pp_ary'], ['tab' => 'logo']);
        }

        $app['heading']->add('Logo verwijderen?');

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

        $out .= $app['link']->btn_cancel('config', $app['pp_ary'], ['tab' => 'logo']);

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger btn-lg">';

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $app['menu']->set('config');

        return $app->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $app['pp_schema'],
        ]);
    }
}
