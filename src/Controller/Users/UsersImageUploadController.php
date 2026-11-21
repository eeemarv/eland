<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Routing\AccessLevel;
use App\Routing\AppModule;
use App\Routing\SchemaRoute;
use App\Service\ImageUploadService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\UserCacheService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class UsersImageUploadController extends AbstractController
{
  #[SchemaRoute(
    access_level: AccessLevel::Admin,
    module: AppModule::Users,
    path: '/users/{id}/image/upload',
    name: 'users_image_upload',
    methods: ['POST'],
  )]

  #[SchemaRoute(
    access_level: AccessLevel::User,
    module: AppModule::Users,
    path: '/users/self/image/upload',
    name: 'users_image_upload_self',
    methods: ['POST'],
  )]

  public function __invoke(
    Request $request,
    string $_access_level,
    Db $db,
    LoggerInterface $logger,
    ImageUploadService $image_upload_service,
    UserCacheService $user_cache_service,
    PageParamsService $pp,
    SessionUserService $su,
    int|null $id = null,
  ):Response
  {
    if ($_access_level !== 'admin')
    {
      $id = $su->id();
    }

    $uploaded_file = $request->files->get('image');

    if (!$uploaded_file)
    {
      return $this->json([
        'error' => 'Image file missing.',
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
