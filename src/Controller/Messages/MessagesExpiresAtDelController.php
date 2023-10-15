<?php declare(strict_types=1);

namespace App\Controller\Messages;

use App\Command\Messages\MessagesExpiresAtCommand;
use App\Form\Type\Messages\MessagesExpiresAtDelType;
use App\Repository\MessageRepository;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class MessagesExpiresAtDelController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/messages/{id}/expires-at/del',
        name: 'messages_expires_at_del',
        methods: ['GET', 'POST'],
        requirements: [
            'id'            => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.user%',
        ],
        defaults: [
            'module'        => 'messages',
        ],
    )]

    public function __invoke(
        Request $request,
        int $id,
        AlertService $alert_service,
        MessageRepository $message_repository,
        ConfigService $config_service,
        PageParamsService $pp,
        SessionUserService $su
    ):Response
    {
        if (!$config_service->get_bool('messages.fields.expires_at.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Expireation of messages not enabled in configuration');
        }

        if ($config_service->get_bool('messages.fields.expires_at.required', $pp->schema()))
        {
            throw new NotFoundHttpException('Expireation of messages is required in configuration');
        }

        $message = $message_repository->get($id, $pp->schema());

        if (!isset($message['expires_at']))
        {
            throw new NotFoundHttpException('No expiration set for message ' . $id);
        }

        $user_id = $message['user_id'];

        if (!$pp->is_admin() && !$su->is_owner($user_id))
        {
            throw new AccessDeniedHttpException('You are not allowed to modify this message');
        }

        $command = new MessagesExpiresAtCommand();

        $command->expires_at = $message['expires_at'];

        $form = $this->createForm(MessagesExpiresAtDelType::class, $command);

        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $update_ary = [
                'expires_at' => null,
            ];

            $message_repository->update($update_ary, $id, $pp->schema());

            $alert_service->success('De vervaldatum is verwijderd');

            return $this->redirectToRoute('messages_show', [
                ...$pp->ary(),
                'id'    => $id,
            ]);
        }

        return $this->render('messages/messages_expires_at_del.html.twig', [
            'form'      => $form->createView(),
            'message'   => $message,
            'user_id'   => $user_id,
        ]);
    }
}
