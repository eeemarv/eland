<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\FormTokenService;
use App\Service\ImageUploadService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;

class MessagesEditImagesUploadController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $id,
        string $form_token,
        Db $db,
        FormTokenService $form_token_service,
        LoggerInterface $logger,
        PageParamsService $pp,
        SessionUserService $su,
        ImageUploadService $image_upload_service,
        MessagesShowImagesUploadController $upload_controller
    ):Response
    {
        if ($error = $form_token_service->get_ajax_error($form_token))
        {
            return $this->json([
                'error' => 'Form token fout: ' . $error,
                'code'  => 400,
            ], 400);
        }

        return $upload_controller(
            $request,
            $id,
            $db,
            $logger,
            $pp,
            $su,
            $image_upload_service
        );
    }
}
