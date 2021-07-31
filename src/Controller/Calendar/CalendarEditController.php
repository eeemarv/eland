<?php declare(strict_types=1);

namespace App\Controller\Calendar;

use App\HtmlProcess\HtmlPurifier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\DateFormatService;
use App\Service\FormTokenService;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CalendarEditController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $id,
        Db $db,
        ConfigService $config_service,
        AlertService $alert_service,
        DateFormatService $date_format_service,
        FormTokenService $form_token_service,
        ItemAccessService $item_access_service,
        LinkRender $link_render,
        PageParamsService $pp,
        HtmlPurifier $html_purifier
    ):Response
    {
        if (!$config_service->get_bool('calendar.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Calendar module not enabled.');
        }

        $errors = [];

        $event_at = trim($request->request->get('event_at', ''));
        $location = trim($request->request->get('location', ''));
        $content = trim($request->request->get('content', ''));
        $subject = trim($request->request->get('subject', ''));
        $access = $request->request->get('access', '');

        if ($request->isMethod('POST'))
        {
            $content = $html_purifier->purify($content);

            if (!$access)
            {
                $errors[] = 'Vul een zichtbaarheid in.';
            }

            if ($event_at)
            {
                $event_at = $date_format_service->reverse($event_at, $pp->schema());

                if ($event_at === '')
                {
                    $errors[] = 'Fout formaat in agendadatum.';
                }
            }

            if (!$subject === '')
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

            if (!count($errors))
            {
                $news = [
                    'subject'   => $subject,
                    'content'   => $content,
                    'location'  => $location,
                    'access'    => $access,
                ];

                if ($event_at)
                {
                    $news['event_at'] = $event_at;
                }
                else
                {
                    $news['event_at'] = null;
                }

                $db->update($pp->schema() . '.news', $news, ['id' => $id]);
                $alert_service->success('Nieuwsbericht aangepast.');
                return $this->redirectToRoute('news_show', array_merge($pp->ary(), ['id' => $id]));
            }

            $alert_service->error($errors);
        }
        else
        {
            $news = $db->fetchAssociative('select *
                from ' . $pp->schema() . '.news
                where id = ?', [$id]);

            $subject = $news['subject'];
            $event_at = $news['event_at'];
            $location = $news['location'];
            $content = $news['content'];
            $access = $news['access'];
        }

        /*
        assets ->add([
            'datepicker',
            'summernote',
            'summernote_forum_post.js',
        ]);
        */

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

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
        $out .= '<label for="content" class="control-label">';
        $out .= 'Bericht</label>';
        $out .= '<textarea name="content" id="content" ';
        $out .= 'class="form-control summernote" rows="10" required>';
        $out .= $content ?? '';
        $out .= '</textarea>';
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

        if ($event_at)
        {
            $out .= $date_format_service->get($event_at, 'day', $pp->schema());
        }

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
        $out .= $location ?? '';
        $out .= '" maxlength="128">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= $item_access_service->get_radio_buttons('access', $access, 'news', $pp->is_user());

        $out .= $link_render->btn_cancel('news_show', $pp->ary(),
            ['id' => $id]);

        $out .= '&nbsp;';
        $out .= '<input type="submit" name="zend" ';
        $out .= 'value="Opslaan" class="btn btn-primary btn-lg">';
        $out .= $form_token_service->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
        ]);
    }
}
