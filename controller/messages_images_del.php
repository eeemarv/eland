<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use controller\messages_show;

class messages_images_del
{
    public function messages_images_del(Request $request, app $app, int $id):Response
    {
        $message = messages_show::get_message($app['db'], $id, $app['tschema']);

        if ($message['access'] === 'user' && $app['s_guest'])
        {
            throw new AccessDeniedHttpException('Je hebt geen toegang tot dit bericht.');
        }

        $s_owner = !$app['s_guest']
            && $app['s_system_self']
            && $app['s_id'] === $message['id_user']
            && $message['id_user'];

        if (!($s_owner || $app['s_admin']))
        {
            throw new AccessDeniedHttpException(
                'Je hebt onvoldoende rechten om dit bericht te verwijderen.');
        }

        $ow_type_this = $message['msg_type'] ? 'dit aanbod' : 'deze vraag';






        $app['tpl']->add($out);
        $app['tpl']->menu('messages');

        return $app['tpl']->get();
    }
}
