<?php declare(strict_types=1);

namespace App\Controller\Messages;

use App\Command\Messages\MessagesSubjectCommand;
use App\Form\Type\Messages\MessagesSubjectEditType;
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
class MessagesSubjectEditController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/messages/{id}/subject/edit',
        name: 'messages_subject_edit',
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

        $command = new MessagesSubjectCommand();

        $command->subject = $message['subject'];
        $subject = $message['subject'];
        $command->offer_want = $message['offer_want'];
        $offer_want = $message['offer_want'];

        $form = $this->createForm(MessagesSubjectEditType::class, $command);

        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();

            $update_ary = [];
            $alert_ary = [];

            if ($subject === $command->subject && $offer_want === $command->offer_want)
            {
                $alert_service->warning('Titel niet gewijzigd');
            }
            else
            {
                if ($subject !== $command->subject)
                {
                    $update_ary['subject'] = $command->subject;

                    $alert_ary[] = 'De titel is veranderd van "' . $subject . '" naar "' . $command->subject . '"';
                }

                if ($offer_want !== $command->offer_want)
                {
                    $update_ary['offer_want'] = $command->offer_want;

                    if ($offer_want === 'offer')
                    {
                        $alert_ary[] = 'Aangepast van "aanbod" naar "vraag"';
                    }
                    else
                    {
                        $alert_ary[] = 'Aangepast van "vraag" naar "aanbod"';
                    }
                }

                $message_repository->update($update_ary, $id, $pp->schema());

                $alert_service->success($alert_ary);
            }

            return $this->redirectToRoute('messages_show', [
                ...$pp->ary(),
                'id'    => $id,
            ]);
        }

        return $this->render('messages/messages_subject_edit.html.twig', [
            'form'      => $form->createView(),
            'message'   => $message,
            'user_id'   => $user_id,
        ]);
    }
}
