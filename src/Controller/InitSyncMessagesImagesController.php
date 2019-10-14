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

class InitSyncMessagesImagesController extends AbstractController
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
}
