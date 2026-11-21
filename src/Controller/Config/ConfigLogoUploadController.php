<?php declare(strict_types=1);

namespace App\Controller\Config;

use App\Routing\AccessLevel;
use App\Routing\AppModule;
use App\Routing\SchemaRoute;
use App\Service\ConfigService;
use App\Service\ImageUploadService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[AsController]
class ConfigLogoUploadController extends AbstractController
{
  #[SchemaRoute(
    access_level: AccessLevel::Admin,
    module: AppModule::Config,
    path: '/logo/upload',
    name: 'config_logo_upload',
    methods: ['POST'],
  )]

  public function __invoke(
    Request $request,
    LoggerInterface $logger,
    ConfigService $config_service,
    PageParamsService $pp,
    SessionUserService $su,
    ImageUploadService $image_upload_service,
  ):Response
  {
    $uploaded_file = $request->files->get('image');

    if (!$uploaded_file)
    {
      throw new BadRequestHttpException('Image file missing.');
    }

    $res = $image_upload_service->upload($uploaded_file,
      'l', 0, 400, 100, false, $pp->schema());

    if (isset($res['filename']))
    {
      $config_service->set_str(
        config_id: 'system.logo',
        value: $res['filename'],
        route: $pp->route(),
        user_id: $su->id() ?: null,
        schema: $pp->schema_o(),
      );

      $logger->info('Logo ' . $res['filename'] .
        ' uploaded.', [
          'schema' => $pp->schema(),
      ]);
    }

    return $this->json($res);
  }
}
