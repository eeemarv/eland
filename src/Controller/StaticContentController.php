<?php declare(strict_types=1);

namespace App\Controller;

use App\Service\ImageUploadService;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;

class StaticContentController extends AbstractController
{
    public function __invoke(
        Request $request,
        Db $db,
        LoggerInterface $logger,
        PageParamsService $pp
    ):Response
    {
        $content = $request->request->get('content', '');
        $page = $request->request->get('page', '');
        $form_token = $request->request->get('form_token', '');



        $db->update($pp->schema() . '.static_content', [
            'data' => ''
        ]);

        $logger->info('Content image ' . $filename .
            ' uploaded.',
            ['schema' => $pp->schema()]);

        return $this->json([
            'filename'  => $filename,
            'base_url'  => $env_s3_url,
        ]);
    }
}
