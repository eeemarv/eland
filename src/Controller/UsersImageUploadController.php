<?php declare(strict_types=1);

namespace App\Controller;

use App\Service\ImageUploadService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\UserCacheService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;

class UsersImageUploadController extends AbstractController
{
    public function users_image_upload(
        Request $request,
        Db $db,
        LoggerInterface $logger,
        ImageUploadService $image_upload_service,
        UserCacheService $user_cache_service,
        PageParamsService $pp,
        SessionUserService $su
    ):Response
    {
        if ($su->id() < 1)
        {
            throw new AccessDeniedHttpException('Je hebt onvoldoende rechten voor deze actie.');
        }

        return $this->users_image_upload_admin(
            $request,
            $su->id(),
            $db,
            $logger,
            $image_upload_service,
            $user_cache_service,
            $pp
        );
    }

    public function users_image_upload_admin(
        Request $request,
        int $id,
        Db $db,
        LoggerInterface $logger,
        ImageUploadService $image_upload_service,
        UserCacheService $user_cache_service,
        PageParamsService $pp
    ):Response
    {
        $uploaded_file = $request->files->get('image');

        if (!$uploaded_file)
        {
            throw new BadRequestHttpException('Afbeeldingsbestand ontbreekt.');
        }

        $filename = $image_upload_service->upload($uploaded_file,
            'u', $id, 400, 400, $pp->schema());

        $db->update($pp->schema() . '.users', [
            '"PictureFile"'	=> $filename
        ],['id' => $id]);

        $logger->info('User image ' . $filename .
            ' uploaded. User: ' . $id,
            ['schema' => $pp->schema()]);

        $user_cache_service->clear($id, $pp->schema());

        return $this->json([$filename]);
    }
}
