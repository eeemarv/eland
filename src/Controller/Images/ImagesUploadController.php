<?php declare(strict_types=1);

namespace App\Controller\Images;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\FormTokenService;
use App\Service\ImageUploadService;
use App\Service\PageParamsService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class ImagesUploadController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/images/upload/{form_token}',
        name: 'images_upload',
        methods: ['POST'],
        requirements: [
            'form_token'    => '%assert.token%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.user%',
        ],
        defaults: [
            'module'        => 'images',
        ],
    )]

    public function __invoke(
        Request $request,
        string $form_token,
        FormTokenService $form_token_service,
        LoggerInterface $logger,
        PageParamsService $pp,
        ImageUploadService $image_upload_service
    ):Response
    {
        if ($error = $form_token_service->get_ajax_error($form_token))
        {
            return $this->json([
                'error' => 'Form token fout: ' . $error,
                'code'  => 400,
            ], 400);
        }

        $uploaded_file = $request->files->get('file');

        error_log(var_export($uploaded_file, true));

        if (!$uploaded_file)
        {
            return $this->json([
                'error' => 'Image file missing.',
                'code'  => 400,
            ], 400);
        }

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

        return $this->json([
            'filename'  => $filename,
            'code'      => 200,
        ]);
    }
}
