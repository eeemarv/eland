<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Controller\MessagesShowController;
use Doctrine\DBAL\Connection as Db;

class MessagesExtendController extends AbstractController
{
    public function messages_extend(
        app $app,
        int $id,
        int $days,
        Db $db
    ):Response
    {
        $message = messages_show::get_message($db, $id, $app['pp_schema']);

        $s_owner = $app['s_id']
            && $message['id_user']
            && $message['id_user'] === $app['s_id'];

        if (!($s_owner || $app['pp_admin']))
        {
            $alert_service->error('Je hebt onvoldoende rechten om ' .
                $message['label']['type_this'] . ' te verlengen.');

            $link_render->redirect('messages_show', $app['pp_ary'], ['id' => $id]);
        }

        $validity = gmdate('Y-m-d H:i:s', strtotime($message['validity']) + (86400 * $days));

        $m = [
            'validity'		=> $validity,
            'mdate'			=> gmdate('Y-m-d H:i:s'),
            'exp_user_warn'	=> 'f',
        ];

        if (!$db->update($app['pp_schema'] . '.messages', $m, ['id' => $id]))
        {
            $alert_service->error('Fout: ' . $message['label']['type_the'] . ' is niet verlengd.');
            $link_render->redirect('messages_show', $app['pp_ary'], ['id' => $id]);
        }

        $alert_service->success(ucfirst($message['label']['type_the']) . ' is verlengd.');
        $link_render->redirect('messages_show', $app['pp_ary'], ['id' => $id]);

        return new Response('');
    }
}
