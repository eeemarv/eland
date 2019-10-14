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

        $s_owner = $su->id() === $message['id_user'];

        if (!($s_owner || $pp->is_admin()))
        {
            throw new AccessDeniedHttpException('Geen rechten om deze afbeelding te verwijderen');
        }

        $image = $db->fetchAssoc('select p."PictureFile"
            from ' . $pp->schema() . '.msgpictures p
            where p.msgid = ?
                and p."PictureFile" = ?', [$id, $img]);

        if (!$image)
        {
            throw new NotFoundHttpException('Afbeelding niet gevonden');
        }

        $db->delete($pp->schema() . '.msgpictures', ['"PictureFile"' => $img]);

        return $this->json(['success' => true]);
    }
}
