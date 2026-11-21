<?php declare(strict_types=1);

namespace App\Controller\Cms;

use App\Routing\AccessLevel;
use App\Routing\AppModule;
use App\Routing\SchemaRoute;
use App\Service\ImageUploadService;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class CmsImageUploadController extends AbstractController
{
  #[SchemaRoute(
    access_level: AccessLevel::Admin,
    module: AppModule::Cms,
    path: '/cms/image-upload',
    name: 'cms_image_upload',
    methods: ['POST'],
  )]

  public function __invoke(
    Request $request,
    Db $db,
    LoggerInterface $logger,
    ImageUploadService $image_upload_service,
    PageParamsService $pp,
    string $env_s3_url,
  ):Response
  {
    $uploaded_file = $request->files->get('image');

    if (!$uploaded_file)
    {
      throw new BadRequestHttpException(
        'Image file missing'
      );
    }

    $file = $image_upload_service->upload($uploaded_file,
      'c', 0, 600, 600, false, $pp->schema());

    $db->insert($pp->schema() . '.static_content_images', [
      'file'          => $file,
    ]);

    $logger->info('Static Content image ' . $file .
      ' uploaded.',
      ['schema' => $pp->schema()]);

    return $this->json([
      'file'  => $file,
      'base_url'  => $env_s3_url,
    ]);
  }
}
