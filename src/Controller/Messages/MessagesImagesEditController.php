<?php declare(strict_types=1);

namespace App\Controller\Messages;

use App\Command\Messages\MessagesImagesCommand;
use App\Form\Type\Messages\MessagesImagesEditType;
use App\Repository\MessageRepository;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\FormTokenService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class MessagesImagesEditController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/messages/{id}/images/add',
        name: 'messages_images_add',
        methods: ['GET', 'POST'],
        requirements: [
            'id'            => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.user%',
        ],
        defaults: [
            'mode'          => 'add',
            'module'        => 'messages',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/messages/{id}/images/edit',
        name: 'messages_images_edit',
        methods: ['GET', 'POST'],
        requirements: [
            'id'            => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.user%',
        ],
        defaults: [
            'mode'          => 'edit',
            'module'        => 'messages',
        ],
    )]

    public function __invoke(
        Request $request,
        int $id,
        string $mode,
        AlertService $alert_service,
        FormTokenService $form_token_service,
        MessageRepository $message_repository,
        ConfigService $config_service,
        PageParamsService $pp,
        SessionUserService $su
    ):Response
    {
        if (!$config_service->get_bool('messages.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Messages module not enabled in configuration');
        }

        $message = $message_repository->get($id, $pp->schema());

        $user_id = $message['user_id'];

        if (!$pp->is_admin() && !$su->is_owner($user_id))
        {
            throw new AccessDeniedHttpException('You are not allowed to modify this message');
        }

        $command = new MessagesImagesCommand();

        $command->image_files = $message['image_files'];
        $image_files = $message['image_files'];

        $image_ary = array_values(json_decode($image_files ?? '[]', true));

        if (count($image_ary))
        {
            if ($mode === 'add')
            {
                throw new NotAcceptableHttpException('Images are already uploaded for this message. Use the "edit" route');
            }
        }
        else
        {
            if ($mode === 'edit')
            {
                throw new NotAcceptableHttpException('No images uploaded yet for this message. Use the "add" route');
            }
        }

        $form = $this->createForm(MessagesImagesEditType::class, $command);

        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();

            if ($image_files === $command->image_files)
            {
                if ($mode === 'add')
                {
                    $alert_service->warning('Geen afbeeldingen opgeladen');
                }
                else
                {
                    $alert_service->warning('Afbeeldingen niet gewijzigd');
                }
            }
            else
            {
                $update_ary = [
                    'image_files'   => $command->image_files,
                ];

                $message_repository->update($update_ary, $id, $pp->schema());

                if ($mode === 'add')
                {
                    $alert_service->success('Afbeeldingen opgeladen');
                }
                else
                {
                    $alert_service->success('Afbeeldingen gewijzigd');
                }
            }

            return $this->redirectToRoute('messages_show', [
                ...$pp->ary(),
                'id'    => $id,
            ]);
        }

        $form_token = $form_token_service->get();

        return $this->render('messages/images/messages_images_' . $mode . '.html.twig', [
            'form'          => $form->createView(),
            'message'       => $message,
            'id'            => $id,
            'user_id'       => $user_id,
            'image_ary'     => $image_ary,
            'form_token'    => $form_token,
            'mode'          => 'edit',
        ]);
    }
}
