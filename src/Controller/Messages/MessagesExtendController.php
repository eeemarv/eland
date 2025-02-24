<?php declare(strict_types=1);

namespace App\Controller\Messages;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class MessagesExtendController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/messages/{id}/extend/{days}',
        name: 'messages_extend',
        methods: ['GET', 'POST'],
        requirements: [
            'id'            => '%assert.id%',
            'days'          => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.user%',
        ],
        defaults: [
            'module'        => 'messages',
        ],
    )]

    public function __invoke(
        int $id,
        int $days,
        Db $db,
        ConfigService $config_service,
        AlertService $alert_service,
        PageParamsService $pp,
        SessionUserService $su
    ):Response
    {
        if (!$config_service->get_bool('messages.fields.expires_at.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Expire messages submodule not enabled.');
        }

        if (!$config_service->get_bool('messages.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Messages (offers/wants) module not enabled.');
        }

        $message = MessagesShowController::get_message($db, $id, $pp->schema());

        if (!($su->is_owner($message['user_id']) || $pp->is_admin()))
        {
            throw new AccessDeniedHttpException('Je hebt onvoldoende rechten om ' .
                $message['label']['offer_want_this'] . ' te verlengen.');
        }

        if (!isset($message['expires_at']))
        {
            $message['expires_at'] =  gmdate('Y-m-d H:i:s');
        }

        $expires_at = gmdate('Y-m-d H:i:s', strtotime($message['expires_at'] . ' UTC') + (86400 * $days));

        $m = [
            'expires_at'	=> $expires_at,
            'exp_user_warn'	=> 'f',
        ];

        if (!$db->update($pp->schema() . '.messages', $m, ['id' => $id]))
        {
            $alert_service->error('Fout: ' . $message['label']['offer_want_the'] . ' is niet verlengd.');

            return $this->redirectToRoute('messages_show', [
                ...$pp->ary(),
                'id' => $id,
            ]);
        }

        $alert_service->success(ucfirst($message['label']['offer_want_the']) . ' is verlengd.');

        return $this->redirectToRoute('messages_show', [
            ...$pp->ary(),
            'id' => $id,
        ]);
    }
}
