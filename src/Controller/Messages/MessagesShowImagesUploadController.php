<?php declare(strict_types=1);

namespace App\Controller\Messages;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use App\Repository\MessageRepository;
use App\Service\ImageTokenService;
use App\Service\ImageUploadService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MessagesShowImagesUploadController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $id,
        string $image_token,
        MessageRepository $message_repository,
        ImageTokenService $image_token_service,
        LoggerInterface $logger,
        PageParamsService $pp,
        SessionUserService $su,
        ImageUploadService $image_upload_service
    ):Response
    {
        $image_token_service->check_and_throw($id, $image_token);

        $uploaded_files = $request->files->get('images', []);

        if (!count($uploaded_files))
        {
            throw new BadRequestHttpException('Missing file.');
        }

        $message = $message_repository->get($id, $pp->schema());

        if (!$message)
        {
            throw new NotFoundHttpException('Message ' . $id . ' not found.');
        }

        if (!$su->is_owner($message['user_id']) && !$pp->is_admin())
        {
            throw new AccessDeniedHttpException('Access Denied for image upload.');
        }

        $filename_ary = [];

        foreach ($uploaded_files as $uploaded_file)
        {
            $filename = $image_upload_service->upload($uploaded_file,
                'm', $id, 400, 400, $pp->schema());

            $message_repository->add_image_file($filename, $id, $pp->schema());

            $logger->info('Image file ' .
                $filename . ' uploaded and inserted in db.',
                ['schema' => $pp->schema()]);

            $filename_ary[] = $filename;
        }

        return $this->json($filename_ary);
    }
}
