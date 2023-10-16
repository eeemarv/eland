<?php declare(strict_types=1);

namespace App\Controller\Messages;

use App\Command\Messages\MessagesUserCommand;
use App\Form\Type\Messages\MessagesUserEditType;
use App\Render\AccountRender;
use App\Repository\MessageRepository;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class MessagesUserEditController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/messages/{id}/user/edit',
        name: 'messages_user_edit',
        methods: ['GET', 'POST'],
        requirements: [
            'id'            => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'module'        => 'messages',
        ],
    )]

    public function __invoke(
        Request $request,
        int $id,
        AlertService $alert_service,
        AccountRender $account_render,
        MessageRepository $message_repository,
        ConfigService $config_service,
        PageParamsService $pp
    ):Response
    {
        if (!$config_service->get_bool('messages.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Messages module not enabled in configuration');
        }

        $message = $message_repository->get($id, $pp->schema());

        $user_id = $message['user_id'];

        $command = new MessagesUserCommand();

        $command->user_id = $message['user_id'];
        $user_id = $message['user_id'];

        $form = $this->createForm(MessagesUserEditType::class, $command);

        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();

            if ($user_id === $command->user_id)
            {
                $alert_service->warning('Eigenaar van bericht niet gewijzigd');
            }
            else
            {
                $update_ary = [
                    'user_id'   => $command->user_id,
                ];

                $old = $account_render->link($user_id, $pp->ary());
                $new = $account_render->link($command->user_id, $pp->ary());

                $message_repository->update($update_ary, $id, $pp->schema());

                $alert_service->success('Eigenaar gewijzigd van ' . $old . ' naar ' . $new);
            }

            return $this->redirectToRoute('messages_show', [
                ...$pp->ary(),
                'id'    => $id,
            ]);
        }

        return $this->render('messages/messages_user_edit.html.twig', [
            'form'      => $form->createView(),
            'message'   => $message,
            'user_id'   => $user_id,
        ]);
    }
}
