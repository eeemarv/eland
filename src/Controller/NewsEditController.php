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
use Doctrine\DBAL\Connection as Db;

class NewsEditController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $id,
        Db $db,
        AlertService $alert_service,
        AssetsService $assets_service,
        DateFormatService $date_format_service,
        FormTokenService $form_token_service,
        HeadingRender $heading_render,
        ItemAccessService $item_access_service,
        LinkRender $link_render,
        MenuService $menu_service,
        PageParamsService $pp,
        HtmlPurifier $html_purifier
    ):Response
    {
        $errors = [];

        $itemdate = trim($request->request->get('itemdate', ''));
        $location = trim($request->request->get('location', ''));
        $sticky = $request->request->has('sticky');
        $newsitem = trim($request->request->get('newsitem', ''));
        $headline = trim($request->request->get('headline', ''));
        $access = $request->request->get('access', '');

        if ($request->isMethod('POST'))
        {
            $newsitem = $html_purifier->purify($newsitem);

            if (!$access)
            {
                $errors[] = 'Vul een zichtbaarheid in.';
            }

            if ($itemdate)
            {
                $itemdate = $date_format_service->reverse($itemdate, $pp->schema());

                if ($itemdate === '')
                {
                    $errors[] = 'Fout formaat in agendadatum.';
                }
            }
            else
            {
                $errors[] = 'Geef een agendadatum op.';
            }

            if (!$headline === '')
            {
                $errors[] = 'Titel is niet ingevuld';
            }

            if (strlen($headline) > 200)
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
                    'headline'  => $headline,
                    'newsitem'  => $newsitem,
                    'location'  => $location,
                    'sticky'    => $sticky ? 't' : 'f',
                    'itemdate'  => $itemdate,
                    'access'    => $access,
                ];

                $db->update($pp->schema() . '.news', $news, ['id' => $id]);
                $alert_service->success('Nieuwsbericht aangepast.');
                $link_render->redirect('news_show', $pp->ary(), ['id' => $id]);
            }

            $alert_service->error($errors);
        }
        else
        {
            $news = $db->fetchAssoc('select *
                from ' . $pp->schema() . '.news
                where id = ?', [$id]);

            $headline = $news['headline'];
            $itemdate = $news['itemdate'];
            $location = $news['location'];
            $sticky = $news['sticky'];
            $newsitem = $news['newsitem'];
            $access = $news['access'];
        }

        $assets_service->add([
            'datepicker',
            'summernote',
            'summernote_forum_post.js',
        ]);

        $heading_render->add('Nieuwsbericht aanpassen');
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
        $out .= $headline;
        $out .= '" required maxlength="200">';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="newsitem" class="control-label">';
        $out .= 'Bericht</label>';
        $out .= '<textarea name="newsitem" id="newsitem" ';
        $out .= 'class="form-control summernote" rows="10" required>';
        $out .= $newsitem ?? '';
        $out .= '</textarea>';
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
        $out .= $date_format_service->datepicker_format($pp->schema());
        $out .= '" ';
        $out .= 'data-date-language="nl" ';
        $out .= 'data-date-today-highlight="true" ';
        $out .= 'data-date-autoclose="true" ';
        $out .= 'data-date-orientation="bottom" ';
        $out .= 'value="';
        $out .= $date_format_service->get($itemdate, 'day', $pp->schema());
        $out .= '" ';
        $out .= 'placeholder="';
        $out .= $date_format_service->datepicker_placeholder($pp->schema());
        $out .= '" ';
        $out .= 'required>';
        $out .= '</div>';
        $out .= '<p>Wanneer gaat dit door?</p>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="sticky" class="control-label">';
        $out .= '<input type="checkbox" id="sticky" name="sticky" ';
        $out .= 'value="1"';
        $out .=  $sticky ? ' checked="checked"' : '';
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

        $menu_service->set('news');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
