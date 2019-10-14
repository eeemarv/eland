<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Controller\MessagesShowController;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Doctrine\DBAL\Connection as Db;

class MessagesExtendController extends AbstractController
{
    public function __invoke(
        int $id,
        int $days,
        Db $db,
        AlertService $alert_service,
        PageParamsService $pp,
        SessionUserService $su,
        LinkRender $link_render
    ):Response
    {
        $message = MessagesShowController::get_message($db, $id, $pp->schema());

        $s_owner = $su->id()
            && $message['id_user']
            && $message['id_user'] === $su->id();

        if (!($s_owner || $pp->is_admin()))
        {
            $alert_service->error('Je hebt onvoldoende rechten om ' .
                $message['label']['type_this'] . ' te verlengen.');

            $link_render->redirect('messages_show', $pp->ary(), ['id' => $id]);
        }

        $validity = gmdate('Y-m-d H:i:s', strtotime($message['validity']) + (86400 * $days));

        $m = [
            'validity'		=> $validity,
            'mdate'			=> gmdate('Y-m-d H:i:s'),
            'exp_user_warn'	=> 'f',
        ];

        if (!$db->update($pp->schema() . '.messages', $m, ['id' => $id]))
        {
            $alert_service->error('Fout: ' . $message['label']['type_the'] . ' is niet verlengd.');
            $link_render->redirect('messages_show', $pp->ary(), ['id' => $id]);
        }

        $alert_service->success(ucfirst($message['label']['type_the']) . ' is verlengd.');
        $link_render->redirect('messages_show', $pp->ary(), ['id' => $id]);

        return new Response('');
    }
}
