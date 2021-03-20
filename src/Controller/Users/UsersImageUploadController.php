<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Service\ImageUploadService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\UserCacheService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Annotation\Route;

class UsersImageUploadController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/users/{id}/image/upload',
        name: 'users_image_upload_admin',
        methods: ['POST'],
        requirements: [
            'id'            => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'is_admin'      => true,
            'module'        => 'users',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/users/image/upload',
        name: 'users_image_upload',
        methods: ['POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.user%',
        ],
        defaults: [
            'id'            => 0,
            'is_admin'      => false,
            'module'        => 'users',
        ],
    )]

    public function __invoke(
        Request $request,
        int $id,
        bool $is_admin,
        Db $db,
        LoggerInterface $logger,
        ImageUploadService $image_upload_service,
        UserCacheService $user_cache_service,
        PageParamsService $pp,
        SessionUserService $su
    ):Response
    {
        if (!$is_admin)
        {
            $id = $su->id();
        }

        $uploaded_file = $request->files->get('image');

        if (!$uploaded_file)
        {
            return $this->json([
                'error' => 'Afbeeldingsbestand ontbreekt.',
                'code'  => 400,
            ], 400);
        }

        $res = $image_upload_service->upload($uploaded_file,
            'u', $id, 400, 400, true, $pp->schema());

        if (!isset($res['filename']))
        {
            return $this->json($res);
        }

        $db->update($pp->schema() . '.users', [
            'image_file'	=> $res['filename'],
        ],['id' => $id]);

        $logger->info('User image ' . $res['filename'] .
            ' uploaded. User: ' . $id,
            ['schema' => $pp->schema()]);

        $user_cache_service->clear($id, $pp->schema());

        return $this->json($res);
    }
}
