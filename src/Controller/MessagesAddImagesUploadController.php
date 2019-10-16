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

class MessagesAddImagesUploadController extends AbstractController
{
    public function __invoke(
        Request $request,
        string $form_token,
        Db $db,
        FormTokenService $form_token_service,
        LoggerInterface $logger,
        PageParamsService $pp,
        SessionUserService $su,
        ImageUploadService $image_upload_service,
        MessagesEditImagesUploadController $edit_upload_controller
    ):Response
    {
        return $edit_upload_controller(
            $request,
            0,
            $form_token,
            $db,
            $form_token_service,
            $logger,
            $pp,
            $su,
            $image_upload_service
        );
    }
}
