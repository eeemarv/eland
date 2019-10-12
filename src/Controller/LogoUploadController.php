<?php declare(strict_types=1);

namespace App\Controller;

use App\Service\ConfigService;
use App\Service\ImageUploadService;
use App\Service\PageParamsService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class LogoUploadController extends AbstractController
{
    public function logo_upload(
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

        $filename = $image_upload_service->upload($uploaded_file,
            'l', 0, 400, 100, $pp->schema());

        $config_service->set('logo', $pp->schema(), $filename);

        $logger->info('Logo ' . $filename .
            ' uploaded.',
            ['schema' => $pp->schema()]);

        return $this->json([$filename]);
    }
}
