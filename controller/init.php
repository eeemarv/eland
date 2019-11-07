<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class init
{
    const POSSIBLE_IMAGE_EXT = ['jpg', 'jpeg', 'JPG', 'JPEG'];

    const ROUTES_LABELS = [
        'init_clear_users_cache'    => 'Clear users cache',
        'init_queue_geocoding'      => 'Queue geocoding',
    ];

    public function init(Request $request, app $app):Response
    {
        $done = $request->query->get('ok', '');

        if ($done)
        {
            $app['alert']->success('Done: ' . self::ROUTES_LABELS[$done]);
        }

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';
        $out .= '<h1>Init</h1>';

        $out .= '</div>';
        $out .= '<div class="panel-body">';
        $out .= '<div class="list-group">';

        foreach (self::ROUTES_LABELS as $route => $lbl)
        {
            $class_done = $done === $route ? ' list-group-item-success' : '';
            $out .= $app['link']->link($route, $app['pp_ary'],
                [], $lbl, ['class' => 'list-group-item' . $class_done]);
        }
        $out .= '</div>';
        $out .= '</div>';
        $out .= '</div>';

        $app['menu']->set('init');

        return $app->render('base/sidebar.html.twig', [
            'content'   => $out,
            'schema'    => $app['pp_schema'],
        ]);
    }

    public function clear_users_cache(Request $request, app $app):Response
    {
        set_time_limit(300);

        error_log('*** clear users cache ***');

        $schemas = $app['systems']->get_schemas();

        foreach($schemas as $schema)
        {
            $users = $app['db']->fetchAll('select id
            from ' . $schema . '.users');

            foreach ($users as $u)
            {
                $app['user_cache']->clear($u['id'], $schema);
            }
        }

        $app['link']->redirect('init', $app['pp_ary'],
            ['ok' => $request->attributes->get('_route')]);

        return new Response('');
    }

    public function queue_geocoding(Request $request, app $app, int $start):Response
    {
        set_time_limit(300);

        error_log('*** Queue for Geocoding, start: ' . $start . ' ***');

        $rs = $app['db']->prepare('select c.id_user, c.value
            from ' . $app['pp_schema'] . '.contact c, ' .
                $app['pp_schema'] . '.type_contact tc
            where c.id_type_contact = tc.id
                and tc.abbrev = \'adr\'
            order by c.id_user asc
            limit 50 offset ' . $start);

        $rs->execute();

        $more_geocoding = false;

        while ($row = $rs->fetch())
        {
            $app['queue.geocode']->cond_queue([
                'adr'		=> $row['value'],
                'uid'		=> $row['id_user'],
                'schema'	=> $app['pp_schema'],
            ], 0);

            $more_geocoding = true;
        }

        if ($more_geocoding)
        {
            $start += 50;

            $app['link']->redirect('init_queue_geocoding', $app['pp_ary'],
                ['start' => $start]);
        }

        $app['link']->redirect('init', $app['pp_ary'],
            ['ok' => $request->attributes->get('_route')]);

        return new Response('');
    }
}
