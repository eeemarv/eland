<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Controller\MessagesShowController;
use App\Service\FormTokenService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Doctrine\DBAL\Connection as Db;

class MessagesImagesInstantDelController extends AbstractController
{
    public function __invoke(
        int $id,
        string $img,
        string $ext,
        string $form_token,
        Db $db,
        PageParamsService $pp,
        SessionUserService $su,
        FormTokenService $form_token_service
    ):Response
    {
        $img .= '.' . $ext;

        if ($error = $form_token_service->get_ajax_error($form_token))
        {
            throw new BadRequestHttpException('Form token fout: ' . $error);
        }

        $message = MessagesShowController::get_message($db, $id, $pp->schema());

        if (!$message)
        {
            throw new NotFoundHttpException('Bericht niet gevonden.');
        }

        if (!($su->is_owner($message['id_user']) || $pp->is_admin()))
        {
            throw new AccessDeniedHttpException('Geen rechten om deze afbeelding te verwijderen');
        }

        $image_file_ary = array_values(json_decode($message['image_files'] ?? '[]', true));

        $key = array_search($img, $image_file_ary);

        if ($key === false)
        {
            throw new NotFoundHttpException('Afbeelding niet gevonden');
        }

        unset($image_file_ary[$key]);

        $image_files = json_encode(array_values($image_file_ary));

        $db->update($pp->schema() . '.messages',
            ['image_files' => $image_files],
            ['id' => $id]);

        return $this->json(['success' => true]);
    }
}
