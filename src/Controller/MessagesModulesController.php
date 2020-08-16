<?php declare(strict_types=1);

namespace App\Controller;

use App\Cnst\BulkCnst;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\FormTokenService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\VarRouteService;

class MessagesModulesController extends AbstractController
{
    public function __invoke(
        Request $request,
        AlertService $alert_service,
        ConfigService $config_service,
        FormTokenService $form_token_service,
        HeadingRender $heading_render,
        LinkRender $link_render,
        MenuService $menu_service,
        PageParamsService $pp,
        VarRouteService $vr
    ):Response
    {
        $errors = [];

        $expires_at_enabled = $request->request->has('expires_at_enabled');
        $category_enabled = $request->request->has('category_enabled');
        $units_enabled = $request->request->has('units_enabled');

        if ($request->isMethod('POST'))
        {
            if ($error_form = $form_token_service->get_error())
            {
                $errors[] = $error_form;
            }

            if (!count($errors))
            {
                $config_service->set_bool('messages.cleanup.enabled', $enabled, $pp->schema());
                $config_service->set_int('messages.cleanup.after_days', (int) $after_days, $pp->schema());
                $config_service->set_int('messages.fields.expires_at.enabled', $expires_at_enabled, $pp->schema());
                $config_service->set_bool('messages.expire.notify', $expire_notify, $pp->schema());
                $alert_service->success('Opruim instellingen Vraag/aanbod aangepast');
                $link_render->redirect('messages_cleanup', $pp->ary(), []);
            }

            $alert_service->error($errors);
        }

        if ($request->isMethod('GET'))
        {
            $expires_at_enabled = $config_service->get_bool('messages.fields.expires_at.enabled', $pp->schema());
            $after_days = $config_service->get_int('messages.cleanup.after_days', $pp->schema());
            $expires_at_days_default = $config_service->get_int('messages.fields.expires_at.days_default', $pp->schema());
            $expire_notify = $config_service->get_bool('messages.expire.notify', $pp->schema());
        }

        $heading_render->fa('trash-o');
        $heading_render->add('Submodules en velden vraag en aanbod');

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="post">';

        $out .= strtr(BulkCnst::TPL_INPUT_ADDON, [
            '%name%'    => 'expires_at_days_default',
            '%label%'   => 'Standaard geldigheidsduur',
            '%addon%'   => 'dagen',
            '%value%'   => $expires_at_days_default,
            '%type%'    => 'number',
            '%attr%'    => ' min="1" max="1460"',
            '%explain%' => 'Bij aanmaak van nieuw vraag of aanbod wordt deze waarde standaard ingevuld in het formulier.',
        ]);

        $lbl_en = 'Ruim vervallen vraag en aanbod op na ';
        $lbl_en .= strtr(BulkCnst::TPL_INLINE_NUMBER_INPUT, [
            '%name%'    => 'after_days',
            '%value%'   => $after_days,
            '%attr%'    => ' min="1" max="365"',
        ]);
        $lbl_en .= ' dagen.';

        $out .= strtr(BulkCnst::TPL_CHECKBOX, [
            '%name%'    => 'enabled',
            '%label%'   => 'Categorieën',
            '%attr%'    => $enabled ? ' checked' : '',
        ]);

        $out .= strtr(BulkCnst::TPL_CHECKBOX, [
            '%name%'    => 'expire_notify',
            '%label%'   => 'Mail een notificatie naar de eigenaar van een vraag of aanbod bericht op het moment dat het vervalt.',
            '%attr%'    => $expire_notify ? ' checked' : '',
        ]);

        $out .= $link_render->btn_cancel($vr->get('messages'), $pp->ary(), []);

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Opslaan" name="zend" class="btn btn-primary btn-lg">';
        $out .= $form_token_service->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('messages_cleanup');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
