<?php declare(strict_types=1);

namespace App\Controller\Messages;

use App\Cache\ConfigCache;
use App\Command\Messages\MessagesAccessCommand;
use App\Form\Type\Messages\MessagesAccessType;
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
class MessagesAccessEditController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/messages/{id}/access/edit',
        name: 'messages_access_edit',
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

        if (!$config_cache->get_bool('intersystem.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('InterSystem module not enabled in configuration');
        }

        $message = $message_repository->get($id, $pp->schema());

        $user_id = $message['user_id'];

        if (!$pp->is_admin() && !$su->is_owner($user_id))
        {
            throw new AccessDeniedHttpException('You are not allowed to modify this message');
        }

        $command = new MessagesAccessCommand();

        $command->access = $message['access'];
        $access = $message['access'];

        $form = $this->createForm(MessagesAccessType::class, $command);

        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();

            if ($access === $command->access)
            {
                $alert_service->warning('Zichtbaarheid niet gewijzigd');
            }
            else
            {
                $update_ary = [
                    'access'   => $command->access,
                ];

                $message_repository->update($update_ary, $id, $pp->schema());

                if ($access === 'user')
                {
                    $alert_service->success('Categorie gewijzigd van "Leden" naar "InterSysteem"');
                }
                else
                {
                    $alert_service->success('Categorie gewijzigd van "InterSysteem" naar "Leden"');
                }
            }

            return $this->redirectToRoute('messages_show', [
                ...$pp->ary(),
                'id'    => $id,
            ]);
        }

        return $this->render('messages/messages_access_edit.html.twig', [
            'form'      => $form->createView(),
            'message'   => $message,
            'user_id'   => $user_id,
        ]);
    }
}
