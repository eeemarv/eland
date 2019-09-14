<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class intersystems_del
{
    public function intersystems_del(Request $request, app $app, int $id):Response
    {
        $group = $app['db']->fetchAssoc('select *
            from ' . $app['pp_schema'] . '.letsgroups
            where id = ?', [$id]);

        if (!$group)
        {
            $app['alert']->error('Systeem niet gevonden.');
            $app['link']->redirect('intersystems', $app['pp_ary'], []);
        }

        if ($app['request']->isMethod('POST'))
        {
            if ($error_token = $app['form_token']->get_error())
            {
                $app['alert']->error($error_token);
                $app['link']->redirect('intersystems', $app['pp_ary'], []);
            }

            if($app['db']->delete($app['pp_schema'] . '.letsgroups', ['id' => $id]))
            {
                $app['alert']->success('InterSysteem verwijderd.');

                $app['intersystems']->clear_cache($app['pp_schema']);

                $app['link']->redirect('intersystems', $app['pp_ary'], []);
            }

            $app['alert']->error('InterSysteem niet verwijderd.');
        }

        $app['heading']->add('InterSysteem verwijderen: ' . $group['groupname']);
        $app['heading']->fa('share-alt');

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<p class="text-danger">Ben je zeker dat dit interSysteem ';
        $out .= 'moet verwijderd worden?</p>';
        $out .= '<div><p>';
        $out .= '<form method="post">';

        $out .= $app['link']->btn_cancel('intersystems', $app['pp_ary'], []);

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger">';
        $out .= $app['form_token']->get_hidden_input();

        $out .= '</form></p>';
        $out .= '</div>';

        $out .= '</div>';
        $out .= '</div>';

        $app['menu']->set('intersystems');

        return $app->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $app['pp_schema'],
        ]);
    }
}
