<?php declare(strict_types=1);

namespace App\Controller\Calendar;

use App\Render\LinkRender;
use App\Service\ConfigService;
use App\Service\FormTokenService;
use App\Service\PageParamsService;
use App\Service\VarRouteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class CalendarDelController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $id,
        Db $db,
        ConfigService $config_service,
        FormTokenService $form_token_service,
        LinkRender $link_render,
        PageParamsService $pp,
        VarRouteService $vr
    ):Response
    {
        if (!$config_service->get_bool(
          config_id: 'calendar.enabled',
          schema: $pp->schema_o(),
        ))
        {
            throw $this->createNotFoundException('Calendar module not enabled.');
        }

        if ($request->isMethod('POST'))
        {
            if ($error_token = $form_token_service->get_error())
            {
                $this->addFlash('error', $error_token);
                return $this->redirectToRoute($vr->get('news'), $pp->ary());
            }

            if($db->delete($pp->schema() . '.news', ['id' => $id]))
            {
                $this->addFlash('success', 'Nieuwsbericht verwijderd.');
                return $this->redirectToRoute($vr->get('news'), $pp->ary());
            }

            $this->addFlash('error', 'Nieuwsbericht niet verwijderd.');
        }

        $news = $db->fetchAssociative('select n.*
            from ' . $pp->schema() . '.news n
            where n.id = ?', [$id]);

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<p class="text-danger"><strong>';
        $out .= 'Ben je zeker dat dit nieuwsbericht ';
        $out .= 'moet verwijderd worden?</strong></p>';

        $out .= '<form method="post">';
        $out .= $link_render->btn_cancel('news_show', $pp->ary(), ['id' => $id]);
        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Verwijderen" ';
        $out .= 'name="zend" class="btn btn-danger btn-lg">';
        $out .= $form_token_service->get_hidden_input();
        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
        ]);
    }
}
