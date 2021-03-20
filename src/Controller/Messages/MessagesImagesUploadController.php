<?php declare(strict_types=1);

namespace App\Controller\Messages;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\ConfigService;
use App\Service\FormTokenService;
use App\Service\ImageUploadService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Doctrine\DBAL\Connection as Db;
use Doctrine\DBAL\Types\Types;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class MessagesImagesUploadController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/messages/images/upload/{form_token}',
        name: 'messages_add_images_upload',
        methods: ['POST'],
        requirements: [
            'form_token'    => '%assert.token%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.user%',
        ],
        defaults: [
            'id'            => 0,
            'mode'          => 'add',
            'module'        => 'messages',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/messages/{id}/images/upload/{form_token}',
        name: 'messages_edit_images_upload',
        methods: ['POST'],
        requirements: [
            'id'            => '%assert.id%',
            'form_token'    => '%assert.token%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.user%',
        ],
        defaults: [
            'mode'          => 'edit',
            'module'        => 'messages',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/messages/{id}/images/upload',
        name: 'messages_images_upload',
        methods: ['POST'],
        requirements: [
            'id'            => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.user%',
        ],
        defaults: [
            'mode'          => 'show',
            'form_token'    => '',
            'module'        => 'messages',
        ],
    )]

    public function __invoke(
        Request $request,
        int $id,
        string $mode,
        string $form_token,
        Db $db,
        FormTokenService $form_token_service,
        ConfigService $config_service,
        LoggerInterface $logger,
        PageParamsService $pp,
        SessionUserService $su,
        ImageUploadService $image_upload_service
    ):Response
    {
        if (!$config_service->get_bool('messages.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Messages (offers/wants) module not enabled.');
        }

        if (in_array($mode, ['add', 'edit']))
        {
            if ($error = $form_token_service->get_ajax_error($form_token))
            {
                return $this->json([
                    'error' => 'Form token fout: ' . $error,
                    'code'  => 400,
                ], 400);
            }
        }

        $uploaded_files = $request->files->get('images', []);

        $filename_ary = [];

        if (!count($uploaded_files))
        {
            return $this->json([
                'error' => 'Afbeeldingsbestand ontbreekt.',
                'code'  => 400,
            ], 400);
        }

        if ($id)
        {
            $message = MessagesShowController::get_message($db, $id, $pp->schema());

            if (!$su->is_owner($message['user_id']) && !$pp->is_admin())
            {
                return $this->json([
                    'error' => 'Je hebt onvoldoende rechten
                        om een afbeelding op te laden voor
                        dit vraag of aanbod bericht.',
                    'code'  => 403,
                ], 403);
            }
        }
        else
        {
            $id = $db->fetchOne('select max(id)
                from ' . $pp->schema() . '.messages', [], []);
            $id++;
        }

        $update_db = false;

        foreach ($uploaded_files as $uploaded_file)
        {
            $res = $image_upload_service->upload($uploaded_file,
                'm', $id, 400, 400, false, $pp->schema());

            if (isset($res['error']))
            {
                return $this->json($res);
            }

            $filename = $res['filename'];

            if ($mode === 'show')
            {
                $update_db = true;

                $logger->info('Image file ' .
                    $res['filename'] . ' uploaded and inserted in db.',
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
            $db->executeStatement('update ' . $pp->schema() . '.messages
                set image_files = image_files || ?
                where id = ?',
                [$filename_ary, $id],
                [Types::JSON, \PDO::PARAM_INT]
            );
        }

        return $this->json([
            'filenames' => $filename_ary,
            'code'      => 200,
        ]);
    }
}
