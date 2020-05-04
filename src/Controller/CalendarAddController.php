<?php declare(strict_types=1);

namespace App\Controller;

use App\HtmlProcess\HtmlPurifier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\AssetsService;
use App\Service\DateFormatService;
use App\Service\FormTokenService;
use App\Service\ItemAccessService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\VarRouteService;
use Doctrine\DBAL\Connection as Db;

class CalendarAddController extends AbstractController
{
    public function __invoke(
        Request $request,
        Db $db,
        DateFormatService $date_format_service,
        HeadingRender $heading_render,
        MenuService $menu_service,
        AlertService $alert_service,
        AssetsService $assets_service,
        FormTokenService $form_token_service,
        ItemAccessService $item_access_service,
        LinkRender $link_render,
        PageParamsService $pp,
        SessionUserService $su,
        VarRouteService $vr,
        HtmlPurifier $html_purifier
    ):Response
    {
        $news = [];
        $errors = [];

        $event_at = trim($request->request->get('event_at', ''));
        $location = trim($request->request->get('location', ''));
        $content = trim($request->request->get('content', ''));
        $subject = trim($request->request->get('subject', ''));
        $access = $request->request->get('access', '');

        if ($request->isMethod('POST'))
        {
            if (!$access)
            {
                $errors[] = 'Vul een zichtbaarheid in.';
            }

            $content = $html_purifier->purify($content);

            if ($event_at)
            {
                $event_at_formatted = $date_format_service->reverse($event_at, $pp->schema());

                if ($event_at_formatted === '')
                {
                    $errors[] = 'Fout formaat in agendadatum.';
                }
            }

            if ($subject === '')
            {
                $errors[] = 'Titel is niet ingevuld';
            }

            if (strlen($subject) > 200)
            {
                $errors[] = 'De titel mag maximaal 200 tekens lang zijn.';
            }

            if (strlen($location) > 128)
            {
                $errors[] = 'De locatie mag maximaal 128 tekens lang zijn.';
            }

            if ($token_error = $form_token_service->get_error())
            {
                $errors[] = $token_error;
            }

            if ($su->is_master())
            {
                $errors[] = 'Het master account kan geen berichten aanmaken.';
            }

            if (!count($errors))
            {
                $news = [
                    'user_id'       => $su->id(),
                    'content'	    => $content,
                    'subject'	    => $subject,
                    'access'        => $access,
                ];

                if ($location)
                {
                    $news['location'] = $location;
                }

                if ($event_at)
                {
                    $news['event_at'] = $event_at_formatted;
                }

                if ($db->insert($pp->schema() . '.news', $news))
                {
                    $id = $db->lastInsertId($pp->schema() . '.news_id_seq');

                    $alert_service->success('Nieuwsbericht opgeslagen.');

                    $news['id'] = $id;

                    $link_render->redirect('news_show', $pp->ary(),
                        ['id' => $id]);
                }
                else
                {
                    $errors[] = 'Nieuwsbericht niet opgeslagen.';
                }
            }

            $alert_service->error($errors);
        }

        $assets_service->add([
            'datepicker',
            'summernote',
            'summernote_forum_post.js',
        ]);

        $heading_render->add('Nieuwsbericht toevoegen');
        $heading_render->fa('calendar-o');

        $out = '<div class="card fcard fcard-info">';
        $out .= '<div class="card-heading">';

        $out .= '<form method="post">';

        $out .= '<div class="form-group">';
        $out .= '<label for="subject" class="control-label">';
        $out .= 'Titel</label>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="subject" name="subject" ';
        $out .= 'value="';
        $out .= $subject;
        $out .= '" required maxlength="200">';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="event_at" class="control-label">';
        $out .= 'Agenda datum</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-calendar"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" id="event_at" name="event_at" ';
        $out .= 'data-provide="datepicker" ';
        $out .= 'data-date-format="';
        $out .= $date_format_service->datepicker_format($pp->schema());
        $out .= '" ';
        $out .= 'data-date-language="nl" ';
        $out .= 'data-date-today-highlight="true" ';
        $out .= 'data-date-autoclose="true" ';
        $out .= 'data-date-orientation="bottom" ';
        $out .= 'value="';
        $out .= $date_format_service->get($event_at, 'day', $pp->schema());
        $out .= '" ';
        $out .= 'placeholder="';
        $out .= $date_format_service->datepicker_placeholder($pp->schema());
        $out .= '">';
        $out .= '</div>';
        $out .= '<p>Wanneer gaat dit door?</p>';
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
        $out .= $location;
        $out .= '" maxlength="128">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="content" class="control-label">';
        $out .= 'Bericht</label>';
        $out .= '<textarea name="content" id="content" ';
        $out .= 'class="form-control summernote" rows="10" required>';
        $out .= $content;
        $out .= '</textarea>';
        $out .= '</div>';

        $out .= $item_access_service->get_radio_buttons('access', $access, 'news', $pp->is_user());

        $out .= $link_render->btn_cancel($vr->get('news'), $pp->ary(), []);

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
            'schema'    => $pp->schema(),
        ]);
    }
}
