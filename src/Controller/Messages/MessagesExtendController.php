<?php declare(strict_types=1);

namespace App\Controller\Messages;

use App\Cache\ConfigCache;
use App\Repository\MessageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
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
    /**
     * Link from mail
     */
    #[Route(
        '/{system}/{role_short}/messages/{id}/extend/{days}',
        name: 'messages_extend',
        methods: ['GET'],
        requirements: [
            'id'            => '%assert.id%',
            'days'          => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.user%',
        ],
        defaults: [
            'expire'        => true,
            'module'        => 'messages',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/messages/{id}/remove-expire',
        name: 'messages_remove_expire',
        methods: ['GET'],
        requirements: [
            'id'            => '%assert.id%',
            'days'          => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.user%',
        ],
        defaults: [
            'expire'        => false,
            'days'          => 0,
            'module'        => 'messages',
        ],
    )]

    public function __invoke(
        int $id,
        int $days,
        bool $expire,
        Db $db,
        MessageRepository $message_repository,
        ConfigCache $config_cache,
        AlertService $alert_service,
        PageParamsService $pp,
        SessionUserService $su
    ):Response
    {
        if (!$config_cache->get_bool('messages.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Messages (offers/wants) module not enabled.');
        }

        if (!$config_cache->get_bool('messages.fields.expires_at.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Expire messages submodule not enabled.');
        }

        if (!$expire && $config_cache->get_bool('messages.fields.expires_at.required', $pp->schema()))
        {
            throw new NotFoundHttpException('Configuration requires expiration of messages. Removing expiration is not allowed.');
        }

        if ($expire && $days < 1)
        {
            throw new NotFoundHttpException('Invalid extend value (days)');
        }

        $message = MessagesShowController::get_message($db, $id, $pp->schema());

        if (!($su->is_owner($message['user_id']) || $pp->is_admin()))
        {
            throw new AccessDeniedHttpException('You have not sufficient rights to prolong the validity of the message');
        }

        if (!isset($message['expires_at']))
        {
            $message['expires_at'] =  gmdate('Y-m-d H:i:s');
        }

        $expires_at = null;

        if ($expire)
        {
            $expires_at = gmdate('Y-m-d H:i:s', strtotime($message['expires_at'] . ' UTC') + (86400 * $days));
        }

        $update_ary = [
            'expires_at'	=> $expires_at,
            'exp_user_warn'	=> 'f',
        ];

        $message_repository->update($update_ary, $id, $pp->schema());

        $alert_msg = match($message['offer_want'] . ($expire ? '_expire' : '_no_expire')){
            'offer_expire'      => 'Het aanbod is verlengd',
            'offer_no_expire'   => 'De vervaldatum is verwijderd van het aanbod',
            'want_expire'       => 'De vraag is verlengd',
            'want_expire'       => 'De vervaldatum is verwijderd van de vraag',
            default             => '***ERR ofer_want ***',
        };

        $alert_service->success($alert_msg);

        return $this->redirectToRoute('messages_show', [
            ...$pp->ary(),
            'id' => $id,
        ]);
    }
}
