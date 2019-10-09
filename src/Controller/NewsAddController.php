<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Cnst\AccessCnst;
use Doctrine\DBAL\Connection as Db;

class NewsAddController extends AbstractController
{
    public function news_add(Request $request, app $app, Db $db):Response
    {
        $news = [];

        if ($request->isMethod('POST'))
        {
            $errors = [];

            $news = [
                'itemdate'	=> trim($request->request->get('itemdate', '')),
                'location'	=> trim($request->request->get('location', '')),
                'sticky'	=> $request->request->get('sticky', false) ? 't' : 'f',
                'newsitem'	=> trim($request->request->get('newsitem', '')),
                'headline'	=> trim($request->request->get('headline', '')),
            ];

            $access = $request->request->get('access', '');

            if (!$access)
            {
                $errors[] = 'Vul een zichtbaarheid in.';
            }

            if ($news['itemdate'])
            {
                $news['itemdate'] = $date_format_service->reverse($news['itemdate'], $app['pp_schema']);

                if ($news['itemdate'] === '')
                {
                    $errors[] = 'Fout formaat in agendadatum.';

                    $news['itemdate'] = '';
                }
            }
            else
            {
                $errors[] = 'Geef een agendadatum op.';
            }

            if (!isset($news['headline']) || (trim($news['headline']) == ''))
            {
                $errors[] = 'Titel is niet ingevuld';
            }

            if (strlen($news['headline']) > 200)
            {
                $errors[] = 'De titel mag maximaal 200 tekens lang zijn.';
            }

            if (strlen($news['location']) > 128)
            {
                $errors[] = 'De locatie mag maximaal 128 tekens lang zijn.';
            }

            if ($token_error = $form_token_service->get_error())
            {
                $errors[] = $token_error;
            }

            if (!count($errors))
            {
                $news['approved'] = $app['pp_admin'] ? 't' : 'f';
                $news['published'] = $app['pp_admin'] ? 't' : 'f';
                $news['id_user'] = $app['s_master'] ? 0 : $app['s_id'];
                $news['cdate'] = gmdate('Y-m-d H:i:s');

                if ($db->insert($app['pp_schema'] . '.news', $news))
                {
                    $id = $db->lastInsertId($app['pp_schema'] . '.news_id_seq');

                    $xdb_service->set('news_access', (string) $id, [
                        'access' => AccessCnst::TO_XDB[$access],
                    ], $app['pp_schema']);

                    $alert_service->success('Nieuwsbericht opgeslagen.');

                    $news['id'] = $id;

                    if(!$app['pp_admin'])
                    {
                        $vars = [
                            'news'			=> $news,
                            'user_id'       => $app['s_id'],
                        ];

                        $mail_queue->queue([
                            'schema'	=> $app['pp_schema'],
                            'to' 		=> $app['mail_addr_system']->get_newsadmin($app['pp_schema']),
                            'template'	=> 'news/review_admin',
                            'vars'		=> $vars,
                        ], 7000);

                        $alert_service->success('Nieuwsbericht wacht op goedkeuring en publicatie door een beheerder');
                        $link_render->redirect($app['r_news'], $app['pp_ary'], []);

                    }

                    $link_render->redirect('news_show', $app['pp_ary'],
                        ['id' => $id]);
                }
                else
                {
                    $errors[] = 'Nieuwsbericht niet opgeslagen.';
                }
            }

            $alert_service->error($errors);
        }
        else
        {
            $news['itemdate'] = gmdate('Y-m-d');
            $access = '';
        }

        $assets_service->add(['datepicker']);

        $heading_render->add('Nieuwsbericht toevoegen');
        $heading_render->fa('calendar-o');

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="post">';

        $out .= '<div class="form-group">';
        $out .= '<label for="headline" class="control-label">';
        $out .= 'Titel</label>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="headline" name="headline" ';
        $out .= 'value="';
        $out .= $news['headline'];
        $out .= '" required maxlength="200">';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="itemdate" class="control-label">';
        $out .= 'Agenda datum</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-calendar"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" id="itemdate" name="itemdate" ';
        $out .= 'data-provide="datepicker" ';
        $out .= 'data-date-format="';
        $out .= $date_format_service->datepicker_format($app['pp_schema']);
        $out .= '" ';
        $out .= 'data-date-language="nl" ';
        $out .= 'data-date-today-highlight="true" ';
        $out .= 'data-date-autoclose="true" ';
        $out .= 'data-date-orientation="bottom" ';
        $out .= 'value="';
        $out .= $date_format_service->get($news['itemdate'], 'day', $app['pp_schema']);
        $out .= '" ';
        $out .= 'placeholder="';
        $out .= $date_format_service->datepicker_placeholder($app['pp_schema']);
        $out .= '" ';
        $out .= 'required>';
        $out .= '</div>';
        $out .= '<p>Wanneer gaat dit door?</p>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="sticky" class="control-label">';
        $out .= '<input type="checkbox" id="sticky" name="sticky" ';
        $out .= 'value="1"';
        $out .=  $news['sticky'] ? ' checked="checked"' : '';
        $out .= '>';
        $out .= ' Behoud na datum</label>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="location" class="control-label">';
        $out .= 'Locatie</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-map-marker"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="location" name="location" ';
        $out .= 'value="';
        $out .= $news['location'];
        $out .= '" maxlength="128">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="newsitem" class="control-label">';
        $out .= 'Bericht</label>';
        $out .= '<textarea name="newsitem" id="newsitem" ';
        $out .= 'class="form-control" rows="10" required>';
        $out .= $news['newsitem'];
        $out .= '</textarea>';
        $out .= '</div>';

        $out .= $item_access_service->get_radio_buttons('access', $access, 'news', $app['pp_user']);

        $out .= $link_render->btn_cancel($app['r_news'], $app['pp_ary'], []);

        $out .= '&nbsp;';
        $out .= '<input type="submit" name="zend" ';
        $out .= 'value="Opslaan" class="btn btn-lg btn-success">';
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
