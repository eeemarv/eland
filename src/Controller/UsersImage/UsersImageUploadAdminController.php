<?php declare(strict_types=1);

namespace App\Controller\UsersImage;

use App\Service\ImageTokenService;
use App\Service\ImageUploadService;
use App\Service\PageParamsService;
use App\Service\UserCacheService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class UsersImageUploadAdminController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $id,
        string $image_token,
        Db $db,
        LoggerInterface $logger,
        TranslatorInterface $translator,
        ImageTokenService $image_token_service,
        ImageUploadService $image_upload_service,
        UserCacheService $user_cache_service,
        PageParamsService $pp
    ):Response
    {
        $error_response = $image_token_service->get_error_response(0, $image_token);

        if (isset($error_response))
        {
            return $error_response;
        }

        $uploaded_file = $request->files->get('image');

        if (!$uploaded_file)
        {
            return $this->json([
                'error' => $translator->trans('image_upload.error.missing'),
                'code'  => 400,
            ], 400);
        }

        $response_ary = $image_upload_service->upload($uploaded_file,
            'u', $id, 400, 400, true, $pp->schema());

        if (!isset($response_ary['filename']))
        {
            return $this->json($response_ary, $response_ary['code']);
        }

        $filename = $response_ary['filename'];

        $db->update($pp->schema() . '.users', [
            'image_file'	=> $filename,
        ],['id' => $id]);

        $logger->info('User image ' . $filename .
            ' uploaded. User: ' . $id,
            ['schema' => $pp->schema()]);

        $user_cache_service->clear($id, $pp->schema());

        return $this->json([
            'filenames' => [$filename],
            'code'      => 200,
        ]);
    }
}
