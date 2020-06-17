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
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class MessagesAddImagesUploadController extends AbstractController
{
    public function __invoke(
        Request $request,
        MessageRepository $message_repository,
        string $image_token,
        LoggerInterface $logger,
        PageParamsService $pp,
        ImageUploadService $image_upload_service,
        ImageTokenService $image_token_service
    ):Response
    {
        $image_token_service->check_and_throw(0, $image_token);

        $uploaded_files = $request->files->get('images', []);

        if (!count($uploaded_files))
        {
            throw new BadRequestHttpException('Missing file.');
        }

        $id = $message_repository->get_max_id($pp->schema());
        $id++;

        $filename_ary = [];

        foreach ($uploaded_files as $uploaded_file)
        {
            $filename = $image_upload_service->upload($uploaded_file,
                'm', $id, 400, 400, $pp->schema());

            $logger->info('Image file ' .
                $filename . ' uploaded and not (yet) inserted in db.',
                ['schema' => $pp->schema()]);

            $filename_ary[] = $filename;
        }

        return $this->json($filename_ary);
    }
}
