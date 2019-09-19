<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use cnst\access as cnst_access;

class news_edit
{
    public function news_edit(Request $request, app $app, int $id):Response
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
                $news['itemdate'] = $app['date_format']->reverse($news['itemdate'], $app['pp_schema']);

                if ($news['itemdate'] === '')
                {
                    $errors[] = 'Fout formaat in agendadatum.';
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

            if ($token_error = $app['form_token']->get_error())
            {
                $errors[] = $token_error;
            }

            if (!count($errors))
            {
                if($app['db']->update($app['pp_schema'] . '.news', $news, ['id' => $id]))
                {
                    $app['xdb']->set('news_access', (string) $id, [
                        'access' => cnst_access::TO_XDB[$access]
                    ], $app['pp_schema']);

                    $app['alert']->success('Nieuwsbericht aangepast.');
                    $app['link']->redirect('news_show', $app['pp_ary'], ['id' => $id]);
                }

                $errors[] = 'Nieuwsbericht niet aangepast.';
            }

            $app['alert']->error($errors);
        }
        else
        {
            $news = $app['db']->fetchAssoc('select *
                from ' . $app['pp_schema'] . '.news
                where id = ?', [$id]);

            $access = $app['xdb']->get('news_access', (string) $id,
                $app['pp_schema'])['data']['access'];

            $access = cnst_access::FROM_XDB[$access];
        }

        $app['assets']->add(['datepicker']);

        $app['heading']->add('Nieuwsbericht aanpassen');
        $app['heading']->fa('calendar-o');

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
        $out .= $app['date_format']->datepicker_format($app['pp_schema']);
        $out .= '" ';
        $out .= 'data-date-language="nl" ';
        $out .= 'data-date-today-highlight="true" ';
        $out .= 'data-date-autoclose="true" ';
        $out .= 'data-date-orientation="bottom" ';
        $out .= 'value="';
        $out .= $app['date_format']->get($news['itemdate'], 'day', $app['pp_schema']);
        $out .= '" ';
        $out .= 'placeholder="';
        $out .= $app['date_format']->datepicker_placeholder($app['pp_schema']);
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

        $out .= $app['item_access']->get_radio_buttons('access', $access, 'news', $app['pp_user']);

        $out .= $app['link']->btn_cancel('news_show', $app['pp_ary'],
            ['id' => $id]);

        $out .= '&nbsp;';
        $out .= '<input type="submit" name="zend" ';
        $out .= 'value="Opslaan" class="btn btn-primary btn-lg">';
        $out .= $app['form_token']->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $app['menu']->set('news');

        return $app->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $app['pp_schema'],
        ]);
    }
}
