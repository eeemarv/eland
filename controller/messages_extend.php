<?php

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Response;
use cnst\msg_type;

class messages_extend
{
    public function get(app $app, int $id, int $days):Response
    {
        $message = $app['db']->fetchAssoc('select m.*
            from ' . $app['tschema'] . '.messages m
            where m.id = ?', [$id]);

        $s_owner = $app['s_id']
            && $message['id_user']
            && $message['id_user'] === $app['s_id'];

        if (!($s_owner || $app['s_admin']))
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

        if (!$app['db']->update($app['tschema'] . '.messages', $m, ['id' => $id]))
        {
            $app['alert']->error('Fout: ' . msg_type::THE[$message['msg_type']] . ' is niet verlengd.');
            $app['link']->redirect('messages', $app['pp_ary'], ['id' => $id]);
        }

        $app['alert']->success(msg_type::UC_THE[$message['msg_type']] . ' is verlengd.');
        $app['link']->redirect('messages', $app['pp_ary'], ['id' => $id]);

        return new Response('');
    }
}