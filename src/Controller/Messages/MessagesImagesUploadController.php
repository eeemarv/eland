<?php declare(strict_types=1);

namespace App\Controller\Messages;

use App\Routing\AccessLevel;
use App\Routing\AppModule;
use App\Routing\SchemaRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\FormTokenService;
use App\Service\ImageUploadService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Doctrine\DBAL\Connection as Db;
use Doctrine\DBAL\Types\Types;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class MessagesImagesUploadController extends AbstractController
{
    #[SchemaRoute(
        access_level: AccessLevel::User,
        module: AppModule::Messages,
        mode: 'add',
        path: '/messages/images/upload/{form_token}',
        name: 'messages_add_images_upload',
        methods: ['POST'],
        requirements: [
            'form_token'    => '%assert.token%',
        ],
    )]

    #[SchemaRoute(
        access_level: AccessLevel::User,
        module: AppModule::Messages,
        mode: 'edit',
        path: '/messages/{id}/images/upload/{form_token}',
        name: 'messages_edit_images_upload',
        methods: ['POST'],
        requirements: [
            'form_token'    => '%assert.token%',
        ],
    )]

    #[SchemaRoute(
        access_level: AccessLevel::User,
        module: AppModule::Messages,
        mode: 'show',
        path: '/messages/{id}/images/upload',
        name: 'messages_images_upload',
        methods: ['POST'],
    )]

    public function __invoke(
        Request $request,
        string $_mode,
        Db $db,
        FormTokenService $form_token_service,
        LoggerInterface $logger,
        PageParamsService $pp,
        SessionUserService $su,
        ImageUploadService $image_upload_service,
        int|null $id = null,
        string|null $form_token = null,
    ):Response
    {
        if (in_array($_mode, ['add', 'edit']))
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
                'error' => 'Image file missing.',
                'code'  => 400,
            ], 400);
        }

        if (isset($id))
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

            if ($_mode === 'show')
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
                [Types::JSONB, Types::INTEGER]
            );
        }

        return $this->json([
            'filenames' => $filename_ary,
            'code'      => 200,
        ]);
    }
}
