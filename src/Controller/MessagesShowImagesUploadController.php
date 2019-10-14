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

        $return_ary = [];

        if (!count($uploaded_files))
        {
            throw new BadRequestHttpException('Afbeeldingsbestand ontbreekt.');
        }

        if ($id)
        {
            $message = MessagesShowController::get_message($db, $id, $pp->schema());

            $s_owner = !$pp->is_guest()
                && $su->is_system_self()
                && $su->id() === $message['id_user']
                && $message['id_user'];

            if (!$s_owner && !$pp->is_admin())
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

        foreach ($uploaded_files as $uploaded_file)
        {
            $filename = $image_upload_service->upload($uploaded_file,
                'm', $id, 400, 400, $pp->schema());

            if ($insert_in_db)
            {
                $db->insert($pp->schema() . '.msgpictures', [
                    'msgid'			=> $id,
                    '"PictureFile"'	=> $filename]);

                $logger->info('Message-Picture ' .
                    $filename . ' uploaded and inserted in db.',
                    ['schema' => $pp->schema()]);
            }
            else
            {
                $logger->info('Message-Picture ' .
                    $filename . ' uploaded, not (yet) inserted in db.',
                    ['schema' => $pp->schema()]);
            }

            $return_ary[] = $filename;
        }

        return $this->json($return_ary);
    }
}
