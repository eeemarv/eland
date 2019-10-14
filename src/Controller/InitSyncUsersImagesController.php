<?php declare(strict_types=1);

namespace App\Controller;

use App\Render\LinkRender;
use App\Service\PageParamsService;
use App\Service\S3Service;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;

class InitSyncUsersImagesController extends AbstractController
{
    public function __invoke(
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

            foreach (InitController::POSSIBLE_IMAGE_EXT as $extension)
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
}
