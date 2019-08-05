<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class categories_del
{
    public function match(Request $request, app $app, int $id):Response
    {
        if($request->isMethod('POST'))
        {
            if ($error_token = $app['form_token']->get_error())
            {
                $app['alert']->error($error_token);
                $app['link']->redirect('categories', $app['pp_ary'], []);
            }

            if ($app['db']->delete($app['tschema'] . '.categories', ['id' => $id]))
            {
                $app['alert']->success('Categorie verwijderd.');
                $app['link']->redirect('categories', $app['pp_ary'], []);
            }

            $app['alert']->error('Categorie niet verwijderd.');
        }

        $fullname = $app['db']->fetchColumn('select fullname
            from ' . $app['tschema'] . '.categories
            where id = ?', [$id]);

        $app['heading']->add('Categorie verwijderen : ');
        $app['heading']->add($fullname);
        $app['heading']->fa('clone');

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= "<p><font color='#F56DB5'><strong>Ben je zeker dat deze categorie";
        $out .= " moet verwijderd worden?</strong></font></p>";
        $out .= '<form method="post">';

        $out .= $app['link']->btn_cancel('categories', $app['pp_ary'], []);

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Verwijderen" ';
        $out .= 'name="zend" class="btn btn-danger">';
        $out .= $app['form_token']->get_hidden_input();
        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $app['tpl']->add($out);
        $app['tpl']->menu('categories');

        return $app['tpl']->get($request);
    }
}
