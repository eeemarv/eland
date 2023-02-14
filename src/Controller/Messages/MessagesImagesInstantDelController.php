<?php declare(strict_types=1);

namespace App\Controller\Messages;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Service\FormTokenService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class MessagesImagesInstantDelController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/messages/{id}/images/{img}/{ext}/del/{form_token}',
        name: 'messages_images_instant_del',
        methods: ['POST'],
        requirements: [
            'id'            => '%assert.id%',
            'img'           => '%assert.message_image%',
            'ext'           => '%assert.image_ext%',
            'form_token'    => '%assert.token%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.user%',
        ],
        defaults: [
            'module'        => 'messages',
        ],
    )]

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

        if (!($su->is_owner($message['user_id']) || $pp->is_admin()))
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
