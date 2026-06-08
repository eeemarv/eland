<?php declare(strict_types=1);

namespace App\Controller\Images;

use App\Repository\MessageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\ConfigService;
use App\Service\ImageUploadService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class ImagesUploadMessagesController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/images/upload/messages/{message_id}',
    name: 'images_upload_messages',
    methods: ['POST'],
    requirements: [
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.user%',
      'message_id'    => '%assert.id%',
    ],
    defaults: [
      'module'        => 'images',
      'temp'          => false,
    ],
  )]

  #[Route(
    '/{schema}/{role_short}/images/upload/messages/temp',
    name: 'images_upload_messages_temp',
    methods: ['POST'],
    requirements: [
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.user%',
    ],
    defaults: [
      'module'        => 'images',
      'temp'          => true,
      'message_id'    => 0,
    ],
  )]

  public function __invoke(
    bool $temp,
    int $message_id,
    Request $request,
    ConfigService $config_service,
    MessageRepository $message_repository,
    LoggerInterface $logger,
    PageParamsService $pp,
    SessionUserService $su,
    ImageUploadService $image_upload_service,
  ):Response
  {
    if (!$this->isCsrfTokenValid('image_upload',
      $request->request->get('_image_upload_token'))
    )
    {
      throw $this->createAccessDeniedException();
    }

    if (!$config_service->get_bool(
      config_id: 'messages.enabled',
      schema: $pp->schema_o(),
    ))
    {
      throw $this->createNotFoundException('Messages (offers/wants) module not enabled.');
    }

    if (!$temp)
    {
      $message = $message_repository->get(
        id: $message_id,
        schema: $pp->schema_o(),
      );

      if (!$message)
      {
        throw $this->createNotFoundException('Message not found.');
      }

      if ($pp->is_user()
        && $message['user_id'] !== $su->id()
      )
      {
        throw $this->createAccessDeniedException();
      }
    }


    $uploaded_files = $request->files->get('images', []);

    $filename_ary = [];

    if (!count($uploaded_files))
    {
      return $this->json([
        'error' => 'Image file missing.',
        'code'  => 400,
      ], 400);
    }

    foreach ($uploaded_files as $uploaded_file)
    {
      $res = $image_upload_service->upload($uploaded_file,
        'm', 0, 400, 400, false, $pp->schema());

      if (isset($res['error']))
      {
        return $this->json($res);
      }

      $filename = $res['filename'];

      $logger->info('Image file ' .
        $filename . ' uploaded, not (yet) inserted in db.',
        ['schema' => $pp->schema()]);

      $filename_ary[] = $filename;
    }

    return $this->json([
      'filenames' => $filename_ary,
      'code'      => 200,
    ]);
  }
}
