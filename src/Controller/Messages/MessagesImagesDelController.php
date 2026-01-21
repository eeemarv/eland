<?php declare(strict_types=1);

namespace App\Controller\Messages;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Render\LinkRender;
use App\Service\ConfigService;
use App\Service\FormTokenService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class MessagesImagesDelController extends AbstractController
{
    #[Route(
        '/{schema}/{role_short}/messages/{id}/images/del',
        name: 'messages_images_del',
        methods: ['GET', 'POST'],
        requirements: [
            'id'            => '%assert.id%',
            'schema'        => '%assert.schema%',
            'role_short'    => '%assert.role_short.user%',
        ],
        defaults: [
            'module'        => 'messages',
        ],
    )]

    public function __invoke(
        Request $request,
        int $id,
        Db $db,
        ConfigService $config_service,
        FormTokenService $form_token_service,
        LinkRender $link_render,
        PageParamsService $pp,
        SessionUserService $su,
        string $env_s3_url
    ):Response
    {
        if (!$config_service->get_bool(
          config_id: 'messages.enabled',
          schema: $pp->schema_o(),
        ))
        {
            throw $this->createNotFoundException('Messages (offers/wants) module not enabled.');
        }

        $errors = [];

        $message = MessagesShowController::get_message($db, $id, $pp->schema());

        if (!($su->is_owner($message['user_id']) || $pp->is_admin()))
        {
            throw $this->createAccessDeniedException('No access');
        }

        $images = array_values(json_decode($message['image_files'] ?? '[]', true));

        if (!count($images))
        {
            $this->addFlash('error', ucfirst($message['label']['offer_want_the']) . ' heeft geen afbeeldingen.');

            return $this->redirectToRoute('messages_show', [
                ...$pp->ary(),
                'id' => $id,
            ]);
        }

        if ($request->isMethod('POST'))
        {
            if ($error_form = $form_token_service->get_error())
            {
                $errors[] = $error_form;
            }

            if (!count($errors))
            {
                $db->update($pp->schema() . '.messages', ['image_files' => '[]'], ['id' => $id]);

                $this->addFlash('success', 'De afbeeldingen voor ' . $message['label']['offer_want_this'] .
                    ' zijn verwijderd.');

                return $this->redirectToRoute('messages_show', [
                    ...$pp->ary(),
                    'id' => $id,
                ]);
            }

            foreach ($errors as $error)
            {
              $this->addFlash('error', $error);
            }
        }

        $out = '<div class="row">';

        foreach ($images as $img)
        {
            $out .= '<div class="col-xs-6 col-md-3">';
            $out .= '<div class="thumbnail">';
            $out .= '<img src="';
            $out .= $env_s3_url . $img;
            $out .= '" class="img-rounded">';

            $out .= '<div class="caption">';
            $out .= '<span class="btn btn-danger btn-lg" data-img="';
            $out .= $img;
            $out .= '" ';
            $out .= 'data-url="';

            $form_token = $form_token_service->get();

            [$img_base, $ext] = explode('.', $img);

            $out .= $link_render->context_path('messages_images_instant_del', $pp->ary(), [
                'img'           => $img_base,
                'ext'           => $ext,
                'form_token'    => $form_token,
                'id'            => $id,
            ]);

            $out .= '" role="button">';
            $out .= '<i class="fa fa-times"></i> ';
            $out .= 'Verwijderen</span>';
            $out .= '</div>';
            $out .= '</div>';
            $out .= '</div>';
        }

        $out .= '</div>';

        $out .= '<form method="post">';

        $out .= '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<h3>Alle afbeeldingen verwijderen voor ';
        $out .= $message['label']['offer_want_this'];
        $out .= ' "';
        $out .= $message['subject'];
        $out .= '"?</h3>';

        $out .= $link_render->btn_cancel('messages_show', $pp->ary(), ['id' => $id]);

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Alle verwijderen" name="zend" class="btn btn-danger btn-lg">';

        $out .= $form_token_service->get_hidden_input();
        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        return $this->render('messages/messages_images_del.html.twig', [
            'content'   => $out,
            'message'   => $message,
        ]);
    }
}
