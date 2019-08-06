<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class news_del
{
    public function match(Request $request, app $app, int $id):Response
    {
        if ($app['request']->isMethod('POST'))
        {
            if ($error_token = $app['form_token']->get_error())
            {
                $app['alert']->error($error_token);
                $app['link']->redirect('news_list', $app['pp_ary'], []);
            }

            if($app['db']->delete($app['tschema'] . '.news', ['id' => $id]))
            {
                $app['xdb']->del('news_access', (string) $id, $app['tschema']);

                $app['alert']->success('Nieuwsbericht verwijderd.');
                $app['link']->redirect('news_list', $app['pp_ary'], []);
            }

            $app['alert']->error('Nieuwsbericht niet verwijderd.');
        }

        $news = $app['db']->fetchAssoc('select n.*
            from ' . $app['tschema'] . '.news n
            where n.id = ?', [$id]);

        $news_access = $app['xdb']->get('news_access', (string) $id,
            $app['tschema'])['data']['access'];

        $app['heading']->add('Nieuwsbericht ' . $news['headline'] . ' verwijderen?');
        $app['heading']->fa('calendar-o');

        $out = '<div class="panel panel-default printview">';
        $out .= '<div class="panel-body';
        $out .= $news['approved'] ? '' : ' bg-inactive';
        $out .= '">';

        $out .= '<dl>';

        $out .= '<dt>Goedgekeurd en gepubliceerd door Admin</dt>';
        $out .= '<dd>';
        $out .= $news['approved'] ? 'Ja' : 'Nee';
        $out .= '</dd>';

        $out .= '<dt>Agendadatum</dt>';

        $out .= '<dd>';

        if ($news['itemdate'])
        {
            $out .= $app['date_format']->get($news['itemdate'], 'day', $app['tschema']);
        }
        else
        {
            $out .= '<i class="fa fa-times"></i>';
        }

        $out .= '</dd>';

        $out .= '<dt>Behoud na Datum?</dt>';
        $out .= '<dd>';
        $out .= $news['sticky'] ? 'Ja' : 'Nee';
        $out .= '</dd>';

        $out .= '<dt>Locatie</dt>';
        $out .= '<dd>';

        if ($news['location'])
        {
            $out .= htmlspecialchars($news['location'], ENT_QUOTES);
        }
        else
        {
            $out .= '<i class="fa fa-times"></i>';
        }

        $out .= '</dd>';

        $out .= '<dt>Bericht/Details</dt>';
        $out .= '<dd>';
        $out .= nl2br(htmlspecialchars($news['newsitem'],ENT_QUOTES));
        $out .= '</dd>';

        $out .= '<dt>Zichtbaarheid</dt>';
        $out .= '<dd>';
        $out .= $app['item_access']->get_label_xdb($news_access);
        $out .= '</dd>';

        $out .= '<dt>Ingegeven door</dt>';
        $out .= '<dd>';
        $out .= $app['account']->link($news['id_user'], $app['pp_ary']);
        $out .= '</dd>';

        $out .= '</dl>';

        $out .= '</div></div>';

        $out .= '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<p class="text-danger"><strong>';
        $out .= 'Ben je zeker dat dit nieuwsbericht ';
        $out .= 'moet verwijderd worden?</strong></p>';

        $out .= '<form method="post">';
        $out .= $app['link']->btn_cancel('news_show', $app['pp_ary'], ['id' => $id]);
        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Verwijderen" ';
        $out .= 'name="zend" class="btn btn-danger">';
        $out .= $app['form_token']->get_hidden_input();
        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $app['tpl']->add($out);
        $app['tpl']->menu('news');

        return $app['tpl']->get($request);
    }
}
