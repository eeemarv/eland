<?php declare(strict_types=1);

namespace App\Controller\Messages;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Repository\MessageRepository;
use App\Service\ImageTokenService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;

class MessagesImagesInstantDelController extends AbstractController
{
    public function __invoke(
        int $id,
        string $img,
        string $ext,
        string $image_token,
        MessageRepository $message_repository,
        ImageTokenService $image_token_service,
        PageParamsService $pp,
        SessionUserService $su
    ):Response
    {
        $image_token_service->check_and_throw($id, $image_token);

        $img .= '.' . $ext;

        $message = $message_repository->get($id, $pp->schema());

        if (!$message)
        {
            throw new NotFoundHttpException('Message with id ' . $id . ' not found.');
        }

        if (!($su->is_owner($message['user_id']) || $pp->is_admin()))
        {
            throw new AccessDeniedHttpException('Access denied.');
        }

        $image_file_ary = array_values(json_decode($message['image_files'] ?? '[]', true));

        $key = array_search($img, $image_file_ary);

        if ($key === false)
        {
            throw new NotFoundHttpException('Image ' . $img . ' not found.');
        }

        unset($image_file_ary[$key]);

        $message_repository->update_image_files($image_file_ary, $id, $pp->schema());

        return $this->json(['success' => true]);
    }
}
