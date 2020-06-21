<?php declare(strict_types=1);

namespace App\Controller\Config;

use App\Service\ConfigService;
use App\Service\ImageTokenService;
use App\Service\ImageUploadService;
use App\Service\PageParamsService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ConfigLogoUploadController extends AbstractController
{
    public function __invoke(
        Request $request,
        string $image_token,
        LoggerInterface $logger,
        ConfigService $config_service,
        PageParamsService $pp,
        ImageTokenService $image_token_service,
        ImageUploadService $image_upload_service
    ):Response
    {
        $image_token_service->check_and_throw(0, $image_token);

        $uploaded_file = $request->files->get('logo');

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
