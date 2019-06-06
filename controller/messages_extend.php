<?php

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Response;

class messages_extend
{
    public function get(app $app, int $id, int $days):Response
    {
        $msg = $app['fetch_message']->get($id);

        $msg->get_data();

        $msg->is_owner($app['s_id'], $app['s_schema'])


        if (!($s_owner || $app['s_admin']))
        {
            $app['alert']->error('Je hebt onvoldoende rechten om ' .
                $ow_type_this . ' te verlengen.');

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
            $app['alert']->error('Fout: ' . $ow_type_the . ' is niet verlengd.');
            $app['link']->redirect('messages', $app['pp_ary'], ['id' => $id]);
        }

        $app['alert']->success($ow_type_uc_the . ' is verlengd.');
        $app['link']->redirect('messages', $app['pp_ary'], ['id' => $id]);

        return new Response('');
    }
}
