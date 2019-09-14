<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use controller\messages_list;

class messages_extended
{
    public function messages_extended(Request $request, app $app):Response
    {
        $fetch_and_filter = messages_list::fetch_and_filter($request, $app);

        $messages = $fetch_and_filter['messages'];
        $params = $fetch_and_filter['params'];
        $out = $fetch_and_filter['out'];

        $ids = $imgs = [];

        foreach ($messages as $msg)
        {
            $ids[] = $msg['id'];
        }

        $_imgs = $app['db']->executeQuery('select mp.msgid, mp."PictureFile"
            from ' . $app['pp_schema'] . '.msgpictures mp
            where msgid in (?)',
            [$ids],
            [\Doctrine\DBAL\Connection::PARAM_INT_ARRAY]);

        foreach ($_imgs as $_img)
        {
            if (isset($imgs[$_img['msgid']]))
            {
                continue;
            }

            $imgs[$_img['msgid']] = $_img['PictureFile'];
        }

        messages_list::set_view_btn_nav(
            $app['btn_nav'], $app['pp_ary'],
            $params, 'extended');

        if (!count($messages))
        {
            return messages_list::no_messages($app);
        }

        $time = time();
        $out .= $app['pagination']->get();

        foreach ($messages as $msg)
        {
            $sf_owner = $app['pp_user']
                && $msg['id_user'] === $app['s_id'];

            $exp = strtotime($msg['validity']) < $time;

            $out .= '<div class="panel panel-info printview">';
            $out .= '<div class="panel-body';
            $out .= $exp ? ' bg-danger' : '';
            $out .= '">';

            $out .= '<div class="media">';

            if (isset($imgs[$msg['id']]))
            {
                $out .= '<div class="media-left">';
                $out .= '<a href="';

                $out .= $app['link']->context_path('messages_show', $app['pp_ary'],
                    ['id' => $msg['id']]);

                $out .= '">';
                $out .= '<img class="media-object" src="';
                $out .= $app['s3_url'] . $imgs[$msg['id']];
                $out .= '" width="150">';
                $out .= '</a>';
                $out .= '</div>';
            }

            $out .= '<div class="media-body">';
            $out .= '<h3 class="media-heading">';

            $out .= $app['link']->link_no_attr('messages_show', $app['pp_ary'],
                ['id' => $msg['id']],
                ucfirst($msg['label']['type']) . ': ' . $msg['content']);

            if ($exp)
            {
                $out .= ' <small><span class="text-danger">';
                $out .= 'Vervallen</span></small>';
            }

            $out .= '</h3>';

            $out .= htmlspecialchars($msg['Description'], ENT_QUOTES);

            $out .= '</div>';
            $out .= '</div>';

            $out .= '</div>';

            $out .= '<div class="panel-footer">';
            $out .= '<p><i class="fa fa-user"></i> ';
            $out .= $app['account']->link($msg['id_user'], $app['pp_ary']);
            $out .= $msg['postcode'] ? ', postcode: ' . $msg['postcode'] : '';

            if ($app['pp_admin'] || $sf_owner)
            {
                $out .= '<span class="inline-buttons pull-right hidden-xs">';

                $out .= $app['link']->link_fa('messages_edit', $app['pp_ary'],
                    ['id' => $msg['id']], 'Aanpassen',
                    ['class'	=> 'btn btn-primary'],
                    'pencil');

                $out .= $app['link']->link_fa('messages_del', $app['pp_ary'],
                    ['id' => $msg['id']], 'Verwijderen',
                    ['class' => 'btn btn-danger'],
                    'times');

                $out .= '</span>';
            }
            $out .= '</p>';
            $out .= '</div>';

            $out .= '</div>';
        }

        $out .= $app['pagination']->get();

        $app['menu']->set('messages');

        return $app->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $app['pp_schema'],
        ]);
    }
}
