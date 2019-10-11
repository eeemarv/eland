<?php declare(strict_types=1);

namespace App\Controller;

use App\Queue\GeocodeQueue;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\ElasDbUpgradeService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\S3Service;
use App\Service\UserCacheService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;

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

    public function init(
        Request $request,
        AlertService $alert_service,
        MenuService $menu_service,
        PageParamsService $pp,
        LinkRender $link_render
    ):Response
    {
        $done = $request->query->get('ok', '');

        if ($done)
        {
            $alert_service->success('Done: ' . self::ROUTES_LABELS[$done]);
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
            $out .= $link_render->link($route, $pp->ary(),
                [], $lbl, ['class' => 'list-group-item' . $class_done]);
        }
        $out .= '</div>';
        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('init');

        return $this->render('base/sidebar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }

    public function elas_db_upgrade(
        Request $request,
        Db $db,
        PageParamsService $pp,
        ElasDbUpgradeService $elas_db_upgrade_service,
        LoggerInterface $logger,
        LinkRender $link_render
    ):Response
    {
        set_time_limit(300);

        $schemaversion = 31000;

        $currentversion = $dbversion = $db->fetchColumn('select value
            from ' . $pp->schema() . '.parameters
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

                $elas_db_upgrade_service->run($currentversion, $pp->schema());
            }

            $m = 'Upgraded database from schema version ' .
                $dbversion . ' to ' . $currentversion;

            error_log(' -- ' . $m . ' -- ');
            $logger->info('DB: ' . $m, ['schema' => $pp->schema()]);
        }

        $link_render->redirect('init', $pp->ary(),
            ['ok' => $request->attributes->get('_route')]);

        return new Response('');
    }

    public function sync_users_images(
        Request $request,
        int $start,
        Db $db,
        LoggerInterface $logger,
        S3Service $s3_service,
        PageParamsService $pp,
        LinkRender $link_render
    ):Response
    {
        set_time_limit(300);

        $found = false;

        $rs = $db->prepare('select id, "PictureFile"
            from ' . $pp->schema() . '.users
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

                if ($s3_service->exists($filename_bucket))
                {
                    $found = true;
                    break;
                }
            }

            if (!$found)
            {
                $db->update($pp->schema() . '.users',
                    ['"PictureFile"' => null], ['id' => $user_id]);

                error_log(' -- Profile image not present,
                    deleted in database: ' . $filename . ' -- ');

                $logger->info('cron: Profile image file of user ' .
                    $user_id . ' was not found in bucket: deleted
                    from database. Deleted filename : ' .
                    $filename, ['schema' => $pp->schema()]);
            }
            else if ($f_schema !== $pp->schema())
            {
                $new_filename = $pp->schema() . '_u_' . $user_id .
                    '_' . sha1(time() . $filename) . '.jpg';

                $err = $s3_service->copy($filename_bucket, $new_filename);

                if ($err)
                {
                    error_log(' -- error: ' . $err . ' -- ');

                    $logger->info('init: copy img error: ' .
                        $err, ['schema' => $pp->schema()]);

                    continue;
                }

                $db->update($pp->schema() . '.users',
                    ['"PictureFile"' => $new_filename],
                    ['id' => $user_id]);

                error_log(' -- Profile image renamed, old: ' .
                    $filename . ' new: ' . $new_filename . ' -- ');

                $logger->info('init: Profile image file renamed, Old: ' .
                    $filename . ' New: ' . $new_filename,
                    ['schema' => $pp->schema()]);
            }
        }

        if ($found)
        {
            error_log(' found img ');
            $start += 50;

            $link_render->redirect('init_sync_users_images', $pp->ary(),
                ['start' => $start]);
        }

        $link_render->redirect('init', $pp->ary(),
            ['ok' => $request->attributes->get('_route')]);

        return new Response('');
    }

    public function sync_messages_images(
        Request $request,
        int $start,
        Db $db,
        LoggerInterface $logger,
        S3Service $s3_service,
        PageParamsService $pp,
        LinkRender $link_render
    ):Response
    {
        set_time_limit(300);

        $message_images = $db->fetchAll('select id, msgid, "PictureFile"
            from ' . $pp->schema() . '.msgpictures
            order by id asc
            limit 50 offset ' . $start);

        if (!count($message_images))
        {
            $link_render->redirect('init', $pp->ary(),
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

                if ($s3_service->exists($filename_bucket))
                {
                    $found = true;
                    break;
                }
            }

            if (!$found)
            {
                $db->delete($pp->schema() . '.msgpictures',
                    ['id' => $id]);

                error_log(' -- Message image not present,
                    deleted in database: ' . $filename . ' -- ');

                $logger->info('init: Image file of message ' . $msg_id .
                    ' not found in bucket: deleted from database. Deleted : ' .
                    $filename . ' id: ' . $id, ['schema' => $pp->schema()]);
            }
            else if ($f_schema !== $pp->schema())
            {
                $new_filename = $pp->schema() . '_m_' .
                    $msg_id . '_' . sha1(time() .
                    $filename) . '.jpg';

                $err = $s3_service->copy($filename_bucket, $new_filename);

                if ($err)
                {
                    error_log(' -- error: ' . $err . ' -- ');

                    $logger->info('init: copy img error: ' . $err,
                        ['schema' => $pp->schema()]);
                    continue;
                }

                $db->update($pp->schema() . '.msgpictures',
                    ['"PictureFile"' => $new_filename], ['id' => $id]);

                error_log('Profile image renamed, old: ' .
                    $filename . ' new: ' . $new_filename);

                $logger->info('init: Message image file renamed, Old : ' .
                    $filename . ' New: ' . $new_filename, ['schema' => $pp->schema()]);
            }
        }

        error_log('Sync image files next.');

        $start += 50;

        $link_render->redirect('init_sync_messages_images', $pp->ary(),
            ['start' => $start]);

        return new Response('');
    }

    public function clear_users_cache(
        Request $request,
        Db $db,
        PageParamsService $pp,
        LinkRender $link_render,
        UserCacheService $user_cache_service
    ):Response
    {
        set_time_limit(300);

        error_log('*** clear users cache ***');

        $users = $db->fetchAll('select id
            from ' . $pp->schema() . '.users');

        foreach ($users as $u)
        {
            $user_cache_service->clear($u['id'], $pp->schema());
        }

        $link_render->redirect('init', $pp->ary(),
            ['ok' => $request->attributes->get('_route')]);

        return new Response('');
    }

    public function empty_elas_tokens(
        Request $request,
        Db $db,
        PageParamsService $pp,
        LinkRender $link_render
    ):Response
    {
        set_time_limit(300);

        $db->executeQuery('delete from ' .
            $pp->schema() . '.tokens');

        error_log('*** empty tokens table from elas (is not used anymore) *** ');

        $link_render->redirect('init', $pp->ary(),
            ['ok' => $request->attributes->get('_route')]);

        return new Response('');
    }

    public function empty_city_distance(
        Request $request,
        PageParamsService $pp,
        Db $db,
        LinkRender $link_render
    ):Response
    {
        set_time_limit(300);

        $db->executeQuery('delete from ' .
            $pp->schema() . '.city_distance');

        error_log('*** empty city_distance table (is not used anymore) *** ');

        $link_render->redirect('init', $pp->ary(),
            ['ok' => $request->attributes->get('_route')]);

        return new Response('');
    }

    public function queue_geocoding(
        Request $request,
        int $start,
        Db $db,
        GeocodeQueue $geocode_queue,
        PageParamsService $pp,
        LinkRender $link_render
    ):Response
    {
        set_time_limit(300);

        error_log('*** Queue for Geocoding, start: ' . $start . ' ***');

        $rs = $db->prepare('select c.id_user, c.value
            from ' . $pp->schema() . '.contact c, ' .
                $pp->schema() . '.type_contact tc
            where c.id_type_contact = tc.id
                and tc.abbrev = \'adr\'
            order by c.id_user asc
            limit 50 offset ' . $start);

        $rs->execute();

        $more_geocoding = false;

        while ($row = $rs->fetch())
        {
            $geocode_queue->cond_queue([
                'adr'		=> $row['value'],
                'uid'		=> $row['id_user'],
                'schema'	=> $pp->schema(),
            ], 0);

            $more_geocoding = true;
        }

        if ($more_geocoding)
        {
            $start += 50;

            $link_render->redirect('init_queue_geocoding', $pp->ary(),
                ['start' => $start]);
        }

        $link_render->redirect('init', $pp->ary(),
            ['ok' => $request->attributes->get('_route')]);

        return new Response('');
    }

    public function copy_config(
        Request $request,
        Db $db,
        PageParamsService $pp,
        ConfigService $config_service,
        LinkRender $link_render
    ):Response
    {
        set_time_limit(300);

        error_log('** Copy config **');

        $config_ary = $db->fetchAll('select value, setting
            from ' . $pp->schema() . '.config');

        foreach($config_ary as $rec)
        {
            if (!$config_service->exists($rec['setting'], $pp->schema()))
            {
                $config_service->set($rec['setting'], $pp->schema(), $rec['value']);
                error_log('Config value copied: ' . $rec['setting'] . ' ' . $rec['value']);
            }
        }

        $link_render->redirect('init', $pp->ary(),
            ['ok' => $request->attributes->get('_route')]);

        return new Response('');
    }
}
