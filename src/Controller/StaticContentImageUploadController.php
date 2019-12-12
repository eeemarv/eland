<?php declare(strict_types=1);

namespace App\Controller;

use App\Service\ImageUploadService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;

class StaticContentImageUploadController extends AbstractController
{
    public function __invoke(
        Request $request,
        Db $db,
        LoggerInterface $logger,
        ImageUploadService $image_upload_service,
        PageParamsService $pp,
        SessionUserService $su,
        string $env_s3_url
    ):Response
    {
        $uploaded_file = $request->files->get('image');

        if (!$uploaded_file)
        {
            throw new BadRequestHttpException('Afbeeldingsbestand ontbreekt.');
        }

        $file = $image_upload_service->upload($uploaded_file,
            'c', 0, 600, 600, $pp->schema());

        $db->insert($pp->schema() . '.static_content_images', [
            'file'          => $file,
            'created_by'    => $su->id(),
        ]);

        $logger->info('Static Content image ' . $filename .
            ' uploaded.',
            ['schema' => $pp->schema()]);

        return $this->json([
            'file'  => $file,
            'base_url'  => $env_s3_url,
        ]);
    }
}
