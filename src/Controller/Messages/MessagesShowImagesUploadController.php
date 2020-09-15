<?php declare(strict_types=1);

namespace App\Controller\Messages;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\MessageRepository;
use App\Service\ImageTokenService;
use App\Service\ImageUploadService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class MessagesShowImagesUploadController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $id,
        string $image_token,
        MessageRepository $message_repository,
        ImageTokenService $image_token_service,
        TranslatorInterface $translator,
        LoggerInterface $logger,
        PageParamsService $pp,
        SessionUserService $su,
        ImageUploadService $image_upload_service
    ):Response
    {
        $error_response = $image_token_service->get_error_response($id, $image_token);

        if (isset($error_response))
        {
            return $error_response;
        }

        $uploaded_files = $request->files->get('images', []);

        if (!count($uploaded_files))
        {
            return $this->json([
                'error' => $translator->trans('image_upload.error.missing'),
                'code'  => 400,
            ], 400);
        }

        $message = $message_repository->get($id, $pp->schema());

        if (!$message)
        {
            return $this->json([
                'error' => $translator->trans('image_upload.error.message_not_found'),
                'code'  => 404,
            ], 404);
        }

        if (!$su->is_owner($message['user_id']) && !$pp->is_admin())
        {
            return $this->json([
                'error' => $translator->trans('image_upload.error.access_denied'),
                'code'  => 403,
            ], 403);
        }

        $filename_ary = [];

        foreach ($uploaded_files as $uploaded_file)
        {
            $response_ary = $image_upload_service->upload($uploaded_file,
                'm', $id, 400, 400, false, $pp->schema());

            if (!isset($response_ary['filename']))
            {
                return $this->json($response_ary);
            }

            $filename = $response_ary['filename'];

            $message_repository->add_image_file($filename, $id, $pp->schema());

            $logger->info('Image file ' .
                $filename . ' uploaded and inserted in db.',
                ['schema' => $pp->schema()]);

            $filename_ary[] = $filename;
        }

        return $this->json([
            'filenames'     => $filename_ary,
            'code'          => 200,
        ]);
    }
}