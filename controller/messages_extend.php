<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Response;
use cnst\msg_type;

class messages_extend
{
    public function messages_extend(app $app, int $id, int $days):Response
    {
        $message = $app['db']->fetchAssoc('select m.*
            from ' . $app['pp_schema'] . '.messages m
            where m.id = ?', [$id]);

        $s_owner = $app['s_id']
            && $message['id_user']
            && $message['id_user'] === $app['s_id'];

        if (!($s_owner || $app['pp_admin']))
        {
            $app['alert']->error('Je hebt onvoldoende rechten om ' .
                msg_type::THIS[$message['msg_type']] . ' te verlengen.');

            $app['link']->redirect('messages_show', $app['pp_ary'], ['id' => $id]);
        }

        $validity = gmdate('Y-m-d H:i:s', strtotime($message['validity']) + (86400 * $days));

        $m = [
            'validity'		=> $validity,
            'mdate'			=> gmdate('Y-m-d H:i:s'),
            'exp_user_warn'	=> 'f',
        ];

        if (!$app['db']->update($app['pp_schema'] . '.messages', $m, ['id' => $id]))
        {
            $app['alert']->error('Fout: ' . msg_type::THE[$message['msg_type']] . ' is niet verlengd.');
            $app['link']->redirect('messages_show', $app['pp_ary'], ['id' => $id]);
        }

        $app['alert']->success(msg_type::UC_THE[$message['msg_type']] . ' is verlengd.');
        $app['link']->redirect('messages_show', $app['pp_ary'], ['id' => $id]);

        return new Response('');
    }
}
