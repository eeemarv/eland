<?php declare(strict_types=1);

namespace App\Controller\Config;

use App\Service\ConfigService;
use App\Service\ImageUploadService;
use App\Service\PageParamsService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

class ConfigLogoUploadController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/logo/upload',
        name: 'config_logo_upload',
        methods: ['POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'module'        => 'config',
        ],
    )]

    public function __invoke(
        Request $request,
        LoggerInterface $logger,
        ConfigService $config_service,
        PageParamsService $pp,
        ImageUploadService $image_upload_service
    ):Response
    {
        $uploaded_file = $request->files->get('image');

        if (!$uploaded_file)
        {
            throw new BadRequestHttpException('Afbeeldingsbestand ontbreekt.');
        }

        $res = $image_upload_service->upload($uploaded_file,
            'l', 0, 400, 100, false, $pp->schema());

        if (isset($res['filename']))
        {
            $config_service->set_str('system.logo', $res['filename'], $pp->schema());

            $logger->info('Logo ' . $res['filename'] .
                ' uploaded.',
                ['schema' => $pp->schema()]);
        }

        return $this->json($res);
    }
}
