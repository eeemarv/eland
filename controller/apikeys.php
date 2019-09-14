<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class apikeys
{
    public function apikeys(app $app):Response
    {
        $apikeys = $app['db']->fetchAll('select *
            from ' . $app['pp_schema'] . '.apikeys');

        $app['btn_top']->add('apikeys_add', $app['pp_ary'], [], 'Apikey toevoegen');

        $app['heading']->add('Apikeys');
        $app['heading']->fa('key');

        $out = self::get_apikey_explain();

        $out .= '<div class="panel panel-default printview">';

        $out .= '<div class="table-responsive">';
        $out .= '<table class="table table-bordered table-hover table-striped footable">';
        $out .= '<thead>';
        $out .= '<tr>';
        $out .= '<th>Id</th>';
        $out .= '<th>Commentaar</th>';
        $out .= '<th data-hide="phone">Apikey</th>';
        $out .= '<th data-hide="phone, tablet" data-sort-initial="true">Gecreëerd</th>';
        $out .= '<th data-hide="phone, tablet" data-sort-ignore="true">Verwijderen</th>';
        $out .= '</tr>';
        $out .= '</thead>';

        $out .= '<tbody>';

        foreach($apikeys as $a)
        {
            $td = [];
            $td[] = $a['id'];
            $td[] = $a['comment'];
            $td[] = $a['apikey'];
            $td[] = $app['date_format']->get_td($a['created'], 'min', $app['pp_schema']);
            $td[] = $app['link']->link_fa('apikeys_del', $app['pp_ary'],
                ['id' => $a['id']], 'Verwijderen',
                ['class' => 'btn btn-danger'], 'times');

            $out .= '<tr><td>';
            $out .= implode('</td><td>', $td);
            $out .= '</td></tr>';
        }

        $out .= '</tbody>';
        $out .= '</table>';
        $out .= '</div></div>';

        $app['menu']->set('apikeys');

        return $app->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $app['pp_schema'],
        ]);
    }

    public function apikeys_add(Request $request, app $app):Response
    {
        if ($request->isMethod('POST'))
        {
            if ($error_token = $app['form_token']->get_error())
            {
                $app['alert']->error($error_token);
                $app['link']->redirect('apikeys', $app['pp_ary'], []);
            }

            $apikey = [
                'apikey' 	=> $request->request->get('apikey', ''),
                'comment'	=> $request->request->get('comment', ''),
                'type'		=> 'interlets',
            ];

            if($app['db']->insert($app['pp_schema'] . '.apikeys', $apikey))
            {
                $app['alert']->success('Apikey opgeslagen.');
                $app['link']->redirect('apikeys', $app['pp_ary'], []);
            }

            $app['alert']->error('Apikey niet opgeslagen.');
        }

        $key = sha1(random_bytes(16));

        $app['heading']->add('Apikey toevoegen');
        $app['heading']->fa('key');

        $out = self::get_apikey_explain();

        $out .= '<div class="panel panel-info" id="add">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="post">';

        $out .= '<div class="form-group">';
        $out .= '<label for="apikey" class="control-label">';
        $out .= 'Apikey</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon" id="name_addon">';
        $out .= '<span class="fa fa-key"></span></span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="apikey" name="apikey" ';
        $out .= 'value="';
        $out .= $key;
        $out .= '" required readonly>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="comment" class="control-label">';
        $out .= 'Commentaar</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon" id="name_addon">';
        $out .= '<span class="fa fa-comment-o"></span></span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="comment" name="comment" ';
        $out .= 'value="';
        $out .= $apikey['comment'] ?? '';
        $out .= '">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= $app['link']->btn_cancel('apikeys', $app['pp_ary'], []);
        $out .= '&nbsp;';
        $out .= '<input type="submit" name="zend" ';
        $out .= 'value="Opslaan" class="btn btn-success">';
        $out .= $app['form_token']->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $app['menu']->set('apikeys');

        return $app->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $app['pp_schema'],
        ]);
    }

    private static function get_apikey_explain():string
    {
        $out = '<p>';
        $out .= '<ul>';
        $out .= '<li>';
        $out .= 'Apikeys zijn enkel nodig voor het leggen van ';
        $out .= 'interSysteem verbindingen naar andere Systemen die ';
        $out .= 'eLAS draaien.</li>';
        $out .= '<li>Voor het leggen van interSysteem ';
        $out .= 'verbindingen naar andere Systemen op ';
        $out .= 'deze eLAND-server ';
        $out .= 'moet je geen Apikey aanmaken.';
        $out .= '</li></ul>';
        $out .= '</p>';
        return $out;
    }

    public function apikeys_del(Request $request, app $app, int $id):Response
    {
        if($request->isMethod('POST'))
        {
            if ($error_token = $app['form_token']->get_error())
            {
                $app['alert']->error($error_token);
                $app['link']->redirect('apikeys', $app['pp_ary'], []);
            }

            if ($app['db']->delete($app['pp_schema'] . '.apikeys',
                ['id' => $id]))
            {
                $app['alert']->success('Apikey verwijderd.');
                $app['link']->redirect('apikeys', $app['pp_ary'], []);
            }

            $app['alert']->error('Apikey niet verwijderd.');
        }
        $apikey = $app['db']->fetchAssoc('select *
            from ' . $app['pp_schema'] . '.apikeys
            where id = ?', [$id]);

        $app['heading']->add('Apikey verwijderen?');
        $app['heading']->fa('key');

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="post">';
        $out .= '<dl>';
        $out .= '<dt>Apikey</dt>';
        $out .= '<dd>';
        $out .= $apikey['apikey'] ?: '<i class="fa fa-times"></i>';
        $out .= '</dd>';
        $out .= '<dt>Commentaar</dt>';
        $out .= '<dd>';
        $out .= $apikey['comment'] ?: '<i class="fa fa-times"></i>';
        $out .= '</dd>';
        $out .= '</dl>';
        $out .= $app['link']->btn_cancel('apikeys', $app['pp_ary'], []);
        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Verwijderen" ';
        $out .= 'name="zend" class="btn btn-danger">';
        $out .= $app['form_token']->get_hidden_input();
        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $app['menu']->set('apikeys');

        return $app->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $app['pp_schema'],
        ]);
    }
}
