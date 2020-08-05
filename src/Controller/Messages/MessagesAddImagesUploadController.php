<?php declare(strict_types=1);

namespace App\Controller\Messages;

use App\Repository\MessageRepository;
use App\Service\ImageTokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\ImageUploadService;
use App\Service\PageParamsService;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class MessagesAddImagesUploadController extends AbstractController
{
    public function __invoke(
        Request $request,
        MessageRepository $message_repository,
        TranslatorInterface $translator,
        string $image_token,
        LoggerInterface $logger,
        PageParamsService $pp,
        ImageUploadService $image_upload_service,
        ImageTokenService $image_token_service
    ):Response
    {
        $error_response = $image_token_service->get_error_response(0, $image_token);

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

        $id = $message_repository->get_max_id($pp->schema());
        $id++;

        $filename_ary = [];

        foreach ($uploaded_files as $uploaded_file)
        {
            $response_ary = $image_upload_service->upload($uploaded_file,
                'm', $id, 400, 400, false, $pp->schema());

            if (!isset($response_ary['filename']))
            {
                return $this->json($response_ary, $response_ary['code']);
            }

            $filename = $response_ary['filename'];

            $logger->info('Image file ' .
                $filename . ' uploaded and not (yet) inserted in db.',
                ['schema' => $pp->schema()]);

            $filename_ary[] = $filename;
        }

        return $this->json([
            'filenames'     => $filename_ary,
            'code'          => 200,
        ]);
    }
}
