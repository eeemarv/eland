<?php declare(strict_types=1);

namespace App\Controller\Messages;

use App\Cache\ConfigCache;
use App\Command\Messages\MessagesServiceStuffCommand;
use App\Form\Type\Messages\MessagesServiceStuffEditType;
use App\Repository\MessageRepository;
use App\Service\AlertService;
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
class MessagesServiceStuffEditController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/messages/{id}/service-stuff/edit',
        name: 'messages_service_stuff_edit',
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
        ConfigCache $config_cache,
        PageParamsService $pp,
        SessionUserService $su
    ):Response
    {
        if (!$config_cache->get_bool('messages.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Messages module not enabled in configuration');
        }

        if (!$config_cache->get_bool('messages.fields.service_stuff.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('service_stuff submodule not enabled in configuration');
        }

        $message = $message_repository->get($id, $pp->schema());

        $user_id = $message['user_id'];

        if (!$pp->is_admin() && !$su->is_owner($user_id))
        {
            throw new AccessDeniedHttpException('You are not allowed to modify this message');
        }

        $command = new MessagesServiceStuffCommand();

        $command->service_stuff = $message['service_stuff'];
        $service_stuff = $message['service_stuff'];

        $form = $this->createForm(MessagesServiceStuffEditType::class, $command);

        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();

            if ($service_stuff === $command->service_stuff)
            {
                $alert_service->warning('Keuze diensten of spullen niet gewijzigd');
            }
            else
            {
                $update_ary = [
                    'service_stuff'   => $command->service_stuff,
                ];

                $message_repository->update($update_ary, $id, $pp->schema());

                $alert_service->success('Keuze diensten of spullen aangepast');
            }

            return $this->redirectToRoute('messages_show', [
                ...$pp->ary(),
                'id'    => $id,
            ]);

        }

        return $this->render('messages/messages_service_stuff_edit.html.twig', [
            'form'      => $form->createView(),
            'message'   => $message,
            'user_id'   => $user_id,
        ]);
    }
}
