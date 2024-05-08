<?php declare(strict_types=1);

namespace App\Controller\Messages;

use App\Cache\ConfigCache;
use App\Command\Messages\MessagesExpiresAtCommand;
use App\Form\Type\Messages\MessagesExpiresAtEditType;
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
class MessagesExpiresAtEditController extends AbstractController
{
    const PROLONG_BTNS = [
        '1_week'    => 7,
        '2_weeks'   => 14,
        '4_weeks'   => 28,
        '2_months'  => 60,
        '6_months'  => 180,
        '1_year'    => 365,
        '5_years'   => 1825,
    ];

    #[Route(
        '/{system}/{role_short}/messages/{id}/expires-at/add',
        name: 'messages_expires_at_add',
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
        '/{system}/{role_short}/messages/{id}/expires-at/edit',
        name: 'messages_expires_at_edit',
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

        if (!$config_cache->get_bool('messages.fields.expires_at.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Expireation of messages not enabled in configuration');
        }

        $message = $message_repository->get($id, $pp->schema());

        $user_id = $message['user_id'];

        if (!$pp->is_admin() && !$su->is_owner($user_id))
        {
            throw new AccessDeniedHttpException('You are not allowed to modify this message');
        }

        if (isset($message['expires_at']))
        {
            if ($mode === 'add')
            {
                throw new NotAcceptableHttpException('Wrong route, choose "edit" route');
            }
        }
        else
        {
            if ($mode === 'edit')
            {
                throw new NotAcceptableHttpException('Wrong route, choose "add" route');
            }
        }

        $form_options = [];
        $submit_buttons = [];

        $prolong_keys = array_keys(self::PROLONG_BTNS);

        foreach ($prolong_keys as $p)
        {
            $submit_buttons[] = 'prolong_' . $p;
        }

        $form_options['submit_buttons'] = $submit_buttons;

        $command = new MessagesExpiresAtCommand();

        $command->expires_at = $message['expires_at'];

        $mode = isset($message['expires_at']) ? 'edit' : 'add';

        $form = $this->createForm(MessagesExpiresAtEditType::class, $command, $form_options);

        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            /** @var Form $form */
            $btn = $form->getClickedButton()->getName();

            if ($btn === 'submit')
            {
                $expires_at = $command->expires_at;

                if ($expires_at === $message['expires_at'])
                {
                    if ($mode === 'add')
                    {
                        // normally blocked by validation (should not happen)
                        $alert_service->warning('Geen vervaldatum bepaald');
                    }
                    else
                    {
                        $alert_service->warning('Vervaldatum niet gewijzigd');
                    }
                }
                else
                {
                    if ($mode === 'add')
                    {
                        $alert_service->success('Vervaldatum bepaald');
                    }
                    else
                    {
                        $alert_service->success('Vervaldatum gewijzigd');
                    }

                    $update_ary = [
                        'expires_at'    => $expires_at,
                        'exp_user_warn' => 'f',
                    ];

                    $message_repository->update($update_ary, $id, $pp->schema());
                }

                return $this->redirectToRoute('messages_show', [
                    ...$pp->ary(),
                    'id'    => $id,
                ]);
            }

            if (!str_starts_with($btn, 'submit_prolong_'))
            {
                throw new NotAcceptableHttpException('Wrong submit name');
            }

            $prolong_key = strtr($btn, [
                'submit_prolong_'   => '',
            ]);

            $prolong_days = self::PROLONG_BTNS[$prolong_key];

            $timezone = new \DateTimeZone('UTC');
            $expires_at = new \DateTimeImmutable('+ ' . $prolong_days . ' days', $timezone);

            $update_ary = [
                'expires_at'    => $expires_at->format('Y-m-d H:i:s'),
                'exp_user_warn' => 'f',
            ];

            $message_repository->update($update_ary, $id, $pp->schema());

            if ($mode === 'add')
            {
                $alert_service->success('De vervaldatum is ingesteld');
            }
            else
            {
                $alert_service->success('De vervaldatum is aangepast');
            }

            return $this->redirectToRoute('messages_show', [
                ...$pp->ary(),
                'id'    => $id,
            ]);

        }

        return $this->render('messages/expires_at/messages_expires_at_' . $mode . '.html.twig', [
            'form'      => $form->createView(),
            'message'   => $message,
            'user_id'   => $user_id,
        ]);
    }
}
