<?php declare(strict_types=1);

namespace App\Controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class users_image_del
{
    public function users_image_del(Request $request, app $app):Response
    {
        if ($app['s_id'] < 1)
        {
            $app['alert']->error('Je hebt geen toegang tot deze actie');
            $app['link']->redirect($app['r_users'], $app['pp_ary'], []);
        }

        return $this->users_image_del_admin($request, $app, $app['s_id']);
    }

    public function users_image_del_admin(Request $request, app $app, int $id):Response
    {
        $user = $app['user_cache']->get($id, $app['pp_schema']);

        if (!$user)
        {
            $app['alert']->error('De gebruiker bestaat niet.');
            $app['link']->redirect($app['r_users'], $app['pp_ary'], []);
        }

        $file = $user['PictureFile'];

        if ($file == '' || !$file)
        {
            $app['alert']->error('De gebruiker heeft geen foto.');
            $app['link']->redirect($app['r_users_show'], $app['pp_ary'], ['id' => $id]);
        }

        if ($request->isMethod('POST'))
        {
            $app['db']->update($app['pp_schema'] . '.users',
                ['"PictureFile"' => ''],
                ['id' => $id]);

            $app['user_cache']->clear($id, $app['pp_schema']);

            $app['alert']->success('Profielfoto verwijderd.');
            $app['link']->redirect($app['r_users_show'], $app['pp_ary'], ['id' => $id]);
        }

        $app['heading']->add('Profielfoto ');

        if ($app['pp_admin'])
        {
            $app['heading']->add('van ');
            $app['heading']->add_raw($app['account']->link($id, $app['pp_ary']));
            $app['heading']->add(' ');
        }

        $app['heading']->add('verwijderen?');

        $out = '<div class="row">';
        $out .= '<div class="col-xs-6">';
        $out .= '<div class="thumbnail">';
        $out .= '<img src="';
        $out .= $app['s3_url'] . $file;
        $out .= '" class="img-rounded">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '</div>';

        $out .= '<form method="post">';

        $out .= '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= $app['link']->btn_cancel($app['r_users_show'], $app['pp_ary'], ['id' => $id]);

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger btn-lg">';

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $app['menu']->set('users');

        return $app->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $app['pp_schema'],
        ]);
    }
}
