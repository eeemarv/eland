<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Controller\MessagesShowController;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MessagesExtendController extends AbstractController
{
    public function __invoke(
        int $id,
        int $days,
        Db $db,
        ConfigService $config_service,
        AlertService $alert_service,
        PageParamsService $pp,
        SessionUserService $su,
        LinkRender $link_render
    ):Response
    {
        if (!$config_service->get_bool('messages.fields.expires_at.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Expire messages module not enabled.');
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
            $link_render->redirect('messages_show', $pp->ary(), ['id' => $id]);
        }

        $alert_service->success(ucfirst($message['label']['offer_want_the']) . ' is verlengd.');
        $link_render->redirect('messages_show', $pp->ary(), ['id' => $id]);

        return new Response('');
    }
}
