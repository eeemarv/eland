<?php declare(strict_types=1);

namespace App\Controller\Messages;

use App\Cache\ConfigCache;
use App\Command\Messages\MessagesUnitsCommand;
use App\Form\Type\Messages\MessagesUnitsEditType;
use App\Repository\MessageRepository;
use App\Service\AlertService;
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
class MessagesUnitsEditController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/messages/{id}/units/add',
        name: 'messages_units_add',
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
        '/{system}/{role_short}/messages/{id}/units/edit',
        name: 'messages_units_edit',
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

    #[Route(
        '/{system}/{role_short}/messages/{id}/units/del',
        name: 'messages_units_del',
        methods: ['GET', 'POST'],
        requirements: [
            'id'            => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.user%',
        ],
        defaults: [
            'mode'          => 'del',
            'module'        => 'messages',
        ],
    )]

    public function __invoke(
        Request $request,
        int $id,
        string $mode,
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

        if (!$config_cache->get_bool('messages.fields.units.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Units submodule not enabled in configuration');
        }

        $message = $message_repository->get($id, $pp->schema());

        $user_id = $message['user_id'];

        if (!$pp->is_admin() && !$su->is_owner($user_id))
        {
            throw new AccessDeniedHttpException('You are not allowed to modify this message');
        }

        if (isset($message['amount']))
        {
            if ($mode === 'add')
            {
                throw new NotAcceptableHttpException('Wrong route, use edit route');
            }
        }
        else
        {
            if ($mode === 'edit')
            {
                throw new NotAcceptableHttpException('Wrong route, use add route');
            }

            if ($mode === 'del')
            {
                throw new NotAcceptableHttpException('Wrong route, del for empty value');
            }
        }

        $command = new MessagesUnitsCommand();

        $command->amount = $message['amount'];
        $amount = $message['amount'];
        $command->units = $message['units'];
        $units = $message['units'];

        $form_options = [];

        if ($mode === 'del')
        {
            $form_options['validation_groups'] = false;
        }

        $form = $this->createForm(MessagesUnitsEditType::class, $command, $form_options);

        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            if ($mode === 'del')
            {
                $update_ary = [
                    'amount'    => null,
                    'units'     => null,
                ];

                $message_repository->update($update_ary, $id, $pp->schema());

                $alert_service->success('De richtprijs is verwijderd');

                return $this->redirectToRoute('messages_show', [
                    ...$pp->ary(),
                    'id'    => $id,
                ]);
            }

            $command = $form->getData();

            if ($command->amount === $amount && $command->units === $units)
            {
                if ($mode === 'add')
                {
                    $alert_service->warning('De richtprijs is niet bepaald');
                }
                else
                {
                    $alert_service->warning('De richtprijs is niet gewijzigd');
                }

                return $this->redirectToRoute('messages_show', [
                    ...$pp->ary(),
                    'id'    => $id,
                ]);
            }

            $update_ary = [
                'amount'    => $command->amount,
                'units'     => $command->units,
            ];

            $message_repository->update($update_ary, $id, $pp->schema());

            if ($mode === 'add')
            {
                $alert_service->success('De richtprijs is ingesteld');
            }
            else
            {
                $alert_service->success('De richtprijs is aangepast');
            }

            return $this->redirectToRoute('messages_show', [
                ...$pp->ary(),
                'id'    => $id,
            ]);
        }

        return $this->render('messages/units/messages_units_' . $mode . '.html.twig', [
            'form'      => $form->createView(),
            'message'   => $message,
            'user_id'   => $user_id,
            'mode'      => $mode,
        ]);
    }
}
