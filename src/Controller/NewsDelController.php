<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;

class NewsDelController extends AbstractController
{
    public function news_del(Request $request, app $app, int $id, Db $db):Response
    {
        if ($request->isMethod('POST'))
        {
            if ($error_token = $form_token_service->get_error())
            {
                $alert_service->error($error_token);
                $link_render->redirect($app['r_news'], $app['pp_ary'], []);
            }

            if($db->delete($app['pp_schema'] . '.news', ['id' => $id]))
            {
                $xdb_service->del('news_access', (string) $id, $app['pp_schema']);

                $alert_service->success('Nieuwsbericht verwijderd.');
                $link_render->redirect($app['r_news'], $app['pp_ary'], []);
            }

            $alert_service->error('Nieuwsbericht niet verwijderd.');
        }

        $news = $db->fetchAssoc('select n.*
            from ' . $app['pp_schema'] . '.news n
            where n.id = ?', [$id]);

        $news_access = $xdb_service->get('news_access', (string) $id,
            $app['pp_schema'])['data']['access'];

        $heading_render->add('Nieuwsbericht ' . $news['headline'] . ' verwijderen?');
        $heading_render->fa('calendar-o');

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
            $out .= $app['date_format']->get($news['itemdate'], 'day', $app['pp_schema']);
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
        $out .= $link_render->btn_cancel('news_show', $app['pp_ary'], ['id' => $id]);
        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Verwijderen" ';
        $out .= 'name="zend" class="btn btn-danger btn-lg">';
        $out .= $form_token_service->get_hidden_input();
        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('news');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $app['pp_schema'],
        ]);
    }
}
