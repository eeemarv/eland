<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class InitController extends AbstractController
{
    const POSSIBLE_IMAGE_EXT = ['jpg', 'jpeg', 'JPG', 'JPEG'];

    const ROUTES_LABELS = [
        'init_elas_db_upgrade'      => 'eLAS database upgrade',
        'init_sync_users_images'    => 'Sync users images',
        'init_sync_messages_images' => 'Sync messages images',
        'init_clear_users_cache'    => 'Clear users cache',
        'init_empty_elas_tokens'    => 'Empty eLAS tokens',
        'init_empty_city_distance'  => 'Empty city distance table',
        'init_queue_geocoding'      => 'Queue geocoding',
        'init_copy_config'          => 'Copy config',
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

    public function elas_db_upgrade(Request $request, app $app):Response
    {
        set_time_limit(300);

        $schemaversion = 31000;

        $currentversion = $dbversion = $app['db']->fetchColumn('select value
            from ' . $app['pp_schema'] . '.parameters
            where parameter = \'schemaversion\'');

        if ($currentversion >= $schemaversion)
        {
            error_log('-- Database already up to date -- ');
        }
        else
        {
            error_log(' -- eLAS/eLAND database needs to
                upgrade from ' . $currentversion .
                ' to ' . $schemaversion . ' -- ');

            while($currentversion < $schemaversion)
            {
                $currentversion++;

                $app['elas_db_upgrade']->run($currentversion, $app['pp_schema']);
            }

            $m = 'Upgraded database from schema version ' .
                $dbversion . ' to ' . $currentversion;

            error_log(' -- ' . $m . ' -- ');
            $app['monolog']->info('DB: ' . $m, ['schema' => $app['pp_schema']]);
        }

        $app['link']->redirect('init', $app['pp_ary'],
            ['ok' => $request->attributes->get('_route')]);

        return new Response('');
    }

    public function sync_users_images(Request $request, app $app, int $start):Response
    {
        set_time_limit(300);

        $found = false;

        $rs = $app['db']->prepare('select id, "PictureFile"
            from ' . $app['pp_schema'] . '.users
            where "PictureFile" is not null
            order by id asc
            limit 50 offset ' . $start);

        $rs->execute();

        while($row = $rs->fetch())
        {
            $found = true;

            $filename = $row['PictureFile'];
            $user_id = $row['id'];

            [$f_schema] = explode('_', $filename);

            $filename_no_ext = pathinfo($filename, PATHINFO_FILENAME);

            $found = false;

            foreach (self::POSSIBLE_IMAGE_EXT as $extension)
            {
                $filename_bucket = $filename_no_ext . '.' . $extension;

                if ($app['s3']->exists($filename_bucket))
                {
                    $found = true;
                    break;
                }
            }

            if (!$found)
            {
                $app['db']->update($app['pp_schema'] . '.users',
                    ['"PictureFile"' => null], ['id' => $user_id]);

                error_log(' -- Profile image not present,
                    deleted in database: ' . $filename . ' -- ');

                $app['monolog']->info('cron: Profile image file of user ' .
                    $user_id . ' was not found in bucket: deleted
                    from database. Deleted filename : ' .
                    $filename, ['schema' => $app['pp_schema']]);
            }
            else if ($f_schema !== $app['pp_schema'])
            {
                $new_filename = $app['pp_schema'] . '_u_' . $user_id .
                    '_' . sha1(time() . $filename) . '.jpg';

                $err = $app['s3']->copy($filename_bucket, $new_filename);

                if ($err)
                {
                    error_log(' -- error: ' . $err . ' -- ');

                    $app['monolog']->info('init: copy img error: ' .
                        $err, ['schema' => $app['pp_schema']]);

                    continue;
                }

                $app['db']->update($app['pp_schema'] . '.users',
                    ['"PictureFile"' => $new_filename],
                    ['id' => $user_id]);

                error_log(' -- Profile image renamed, old: ' .
                    $filename . ' new: ' . $new_filename . ' -- ');

                $app['monolog']->info('init: Profile image file renamed, Old: ' .
                    $filename . ' New: ' . $new_filename,
                    ['schema' => $app['pp_schema']]);
            }
        }

        if ($found)
        {
            error_log(' found img ');
            $start += 50;

            $app['link']->redirect('init_sync_users_images', $app['pp_ary'],
                ['start' => $start]);
        }

        $app['link']->redirect('init', $app['pp_ary'],
            ['ok' => $request->attributes->get('_route')]);

        return new Response('');
    }

    public function sync_messages_images(Request $request, app $app, int $start):Response
    {
        set_time_limit(300);

        $message_images = $app['db']->fetchAll('select id, msgid, "PictureFile"
            from ' . $app['pp_schema'] . '.msgpictures
            order by id asc
            limit 50 offset ' . $start);

        if (!count($message_images))
        {
            $app['link']->redirect('init', $app['pp_ary'],
                ['ok' => $request->attributes->get('_route')]);
        }

        foreach ($message_images as $image)
        {
            $filename = $image['PictureFile'];
            $msg_id = $image['msgid'];
            $id = $image['id'];

            [$f_schema] = explode('_', $filename);

            $filename_no_ext = pathinfo($filename, PATHINFO_FILENAME);

            $found = false;

            foreach (self::POSSIBLE_IMAGE_EXT as $extension)
            {
                $filename_bucket = $filename_no_ext . '.' . $extension;

                if ($app['s3']->exists($filename_bucket))
                {
                    $found = true;
                    break;
                }
            }

            if (!$found)
            {
                $app['db']->delete($app['pp_schema'] . '.msgpictures',
                    ['id' => $id]);

                error_log(' -- Message image not present,
                    deleted in database: ' . $filename . ' -- ');

                $app['monolog']->info('init: Image file of message ' . $msg_id .
                    ' not found in bucket: deleted from database. Deleted : ' .
                    $filename . ' id: ' . $id, ['schema' => $app['pp_schema']]);
            }
            else if ($f_schema !== $app['pp_schema'])
            {
                $new_filename = $app['pp_schema'] . '_m_' .
                    $msg_id . '_' . sha1(time() .
                    $filename) . '.jpg';

                $err = $app['s3']->copy($filename_bucket, $new_filename);

                if ($err)
                {
                    error_log(' -- error: ' . $err . ' -- ');

                    $app['monolog']->info('init: copy img error: ' . $err,
                        ['schema' => $app['pp_schema']]);
                    continue;
                }

                $app['db']->update($app['pp_schema'] . '.msgpictures',
                    ['"PictureFile"' => $new_filename], ['id' => $id]);

                error_log('Profile image renamed, old: ' .
                    $filename . ' new: ' . $new_filename);

                $app['monolog']->info('init: Message image file renamed, Old : ' .
                    $filename . ' New: ' . $new_filename, ['schema' => $app['pp_schema']]);
            }
        }

        error_log('Sync image files next.');

        $start += 50;

        $app['link']->redirect('init_sync_messages_images', $app['pp_ary'],
            ['start' => $start]);

        return new Response('');
    }

    public function clear_users_cache(Request $request, app $app):Response
    {
        set_time_limit(300);

        error_log('*** clear users cache ***');

        $users = $app['db']->fetchAll('select id
            from ' . $app['pp_schema'] . '.users');

        foreach ($users as $u)
        {
            $app['predis']->del($app['pp_schema'] . '_user_' . $u['id']);
        }

        $app['link']->redirect('init', $app['pp_ary'],
            ['ok' => $request->attributes->get('_route')]);

        return new Response('');
    }

    public function empty_elas_tokens(Request $request, app $app):Response
    {
        set_time_limit(300);

        $app['db']->executeQuery('delete from ' .
        $app['pp_schema'] . '.tokens');

        error_log('*** empty tokens table from elas (is not used anymore) *** ');

        $app['link']->redirect('init', $app['pp_ary'],
            ['ok' => $request->attributes->get('_route')]);

        return new Response('');
    }

    public function empty_city_distance(Request $request, app $app):Response
    {
        set_time_limit(300);

        $app['db']->executeQuery('delete from ' .
        $app['pp_schema'] . '.city_distance');

        error_log('*** empty city_distance table (is not used anymore) *** ');

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

    public function copy_config(Request $request, app $app):Response
    {
        set_time_limit(300);

        error_log('** Copy config **');

        $config_ary = $app['db']->fetchAll('select value, setting
            from ' . $app['pp_schema'] . '.config');

        foreach($config_ary as $rec)
        {
            if (!$app['config']->exists($rec['setting'], $app['pp_schema']))
            {
                $app['config']->set($rec['setting'], $app['pp_schema'], $rec['value']);
                error_log('Config value copied: ' . $rec['setting'] . ' ' . $rec['value']);
            }
        }

        $app['link']->redirect('init', $app['pp_ary'],
            ['ok' => $request->attributes->get('_route')]);

        return new Response('');
    }
}
