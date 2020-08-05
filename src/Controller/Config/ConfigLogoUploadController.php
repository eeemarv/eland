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
use Symfony\Contracts\Translation\TranslatorInterface;

class ConfigLogoUploadController extends AbstractController
{
    public function __invoke(
        Request $request,
        string $image_token,
        LoggerInterface $logger,
        ConfigService $config_service,
        TranslatorInterface $translator,
        PageParamsService $pp,
        ImageTokenService $image_token_service,
        ImageUploadService $image_upload_service
    ):Response
    {
        $error_response = $image_token_service->get_error_response(0, $image_token);

        if (isset($error_response))
        {
            return $error_response;
        }

        $uploaded_file = $request->files->get('logo');

        if (!$uploaded_file)
        {
            return $this->json([
                'error' => $translator->trans('image_upload.error.missing'),
                'code'  => 400,
            ], 400);
        }

        $response_ary = $image_upload_service->upload($uploaded_file,
            'l', 0, 400, 100, false, $pp->schema());

        if (!isset($response_ary['filename']))
        {
            return $this->json($response_ary, $response_ary['code']);
        }

        $filename = $response_ary['filename'];

        $config_service->set_str('system.logo', $filename, $pp->schema());

        $logger->info('Logo ' . $filename .
            ' uploaded.',
            ['schema' => $pp->schema()]);


        return $this->json([
            'filenames'     => [$filename],
            'code'          => 200,
        ]);
    }
}
