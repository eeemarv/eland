<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class users_image_del
{
    public function form_self(Request $request, app $app):Response
    {
        if ($app['s_id'] < 1)
        {
            $app['alert']->error('Je hebt geen toegang tot deze actie');
            $app['link']->redirect($app['r_users'], $app['pp_ary'], []);
        }

        return $this->form_admin($request, $app, $app['s_id']);
    }

    public function form_admin(Request $request, app $app, int $id):Response
    {
        $user = $app['user_cache']->get($id, $app['tschema']);

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
            $app['db']->update($app['tschema'] . '.users',
                ['"PictureFile"' => ''],
                ['id' => $id]);

            $app['user_cache']->clear($id, $app['tschema']);

            $app['alert']->success('Profielfoto verwijderd.');
            $app['link']->redirect($app['r_users_show'], $app['pp_ary'], ['id' => $id]);
        }

        $app['heading']->add('Profielfoto ');

        if ($app['s_admin'])
        {
            $app['heading']->add('van ');
            $app['heading']->add($app['account']->link($id, $app['pp_ary']));
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
        $out .= '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger">';

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $app['tpl']->add($out);
        $app['tpl']->menu('users');

        return $app['tpl']->get();
    }
}
