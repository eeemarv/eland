<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use App\Controller\MessagesShowController;
use App\Service\ImageUploadService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Doctrine\DBAL\Connection as Db;
use Doctrine\DBAL\Types\Types;
use Psr\Log\LoggerInterface;

class MessagesShowImagesUploadController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $id,
        Db $db,
        LoggerInterface $logger,
        PageParamsService $pp,
        SessionUserService $su,
        ImageUploadService $image_upload_service
    ):Response
    {
        $uploaded_files = $request->files->get('images', []);
        $insert_in_db = $request->attributes->get('form_token') ? false : true;

        $filename_ary = [];

        if (!count($uploaded_files))
        {
            throw new BadRequestHttpException('Afbeeldingsbestand ontbreekt.');
        }

        if ($id)
        {
            $message = MessagesShowController::get_message($db, $id, $pp->schema());

            if (!$su->is_owner($message['user_id']) && !$pp->is_admin())
            {
                throw new AccessDeniedHttpException('Je hebt onvoldoende rechten
                    om een afbeelding op te laden voor
                    dit vraag of aanbod bericht.');
            }
        }
        else
        {
            $id = $db->fetchColumn('select max(id)
                from ' . $pp->schema() . '.messages');
            $id++;
        }

        $update_db = false;

        foreach ($uploaded_files as $uploaded_file)
        {
            $filename = $image_upload_service->upload($uploaded_file,
                'm', $id, 400, 400, $pp->schema());

            if ($insert_in_db)
            {
                $update_db = true;

                $logger->info('Image file ' .
                    $filename . ' uploaded and inserted in db.',
                    ['schema' => $pp->schema()]);
            }
            else
            {
                $logger->info('Image file ' .
                    $filename . ' uploaded, not (yet) inserted in db.',
                    ['schema' => $pp->schema()]);
            }

            $filename_ary[] = $filename;
        }

        if ($update_db)
        {
            $db->executeUpdate('update ' . $pp->schema() . '.messages
                set image_files = image_files || ?
                where id = ?',
                [$filename_ary, $id],
                [Types::JSON, \PDO::PARAM_INT]
            );
        }

        return $this->json($filename_ary);
    }
}
