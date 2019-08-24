<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Doctrine\DBAL\Connection as db;
use cnst\access as cnst_access;

class messages_show
{
    public function messages_show(Request $request, app $app, int $id):Response
    {
        $message = self::get_message($app['db'], $id, $app['tschema']);

        if ($message['access'] === 'user' && $app['s_guest'])
        {
            throw new AccessDeniedHttpException('Je hebt geen toegang tot dit bericht.');
        }

        $s_owner = !$app['s_guest']
            && $app['s_system_self']
            && $app['s_id'] === $message['id_user']
            && $message['id_user'];







        $app['tpl']->add($out);
        $app['tpl']->menu('messages');

        return $app['tpl']->get();
    }

    public static function get_message(db $db, int $id, string $tschema):array
    {
        $message = $db->fetchAssoc('select m.*,
                c.id as cid,
                c.fullname as catname
            from ' . $tschema . '.messages m, ' .
                $tschema . '.categories c
            where m.id = ?
                and c.id = m.id_category', [$id]);

        if (!$message)
        {
            throw new NotFoundHttpException('Dit bericht bestaat niet of werd verwijderd.');
        }

        $message['access'] = cnst_access::FROM_LOCAL[$message['local']];

        return $message;
    }
}
