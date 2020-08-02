<?php declare(strict_types=1);

namespace App\Controller\UsersImage;

use App\Service\ImageUploadService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\UserCacheService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;

class UsersImageUploadController extends AbstractController
{
    public function __invoke(
        Request $request,
        Db $db,
        LoggerInterface $logger,
        ImageUploadService $image_upload_service,
        UserCacheService $user_cache_service,
        PageParamsService $pp,
        SessionUserService $su,
        UsersImageUploadAdminController $users_image_upload_admin_controller
    ):Response
    {
        if ($su->id() < 1)
        {
            return $this->json([
                'error' => 'Je hebt onvoldoende rechten voor deze actie.',
                'code'  => 403,
            ], 403);
        }

        return $users_image_upload_admin_controller(
            $request,
            $su->id(),
            $db,
            $logger,
            $image_upload_service,
            $user_cache_service,
            $pp
        );
    }
}
