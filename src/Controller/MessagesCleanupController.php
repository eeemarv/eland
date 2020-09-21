<?php declare(strict_types=1);

namespace App\Controller;

use App\Cnst\BulkCnst;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\AssetsService;
use App\Service\ConfigService;
use App\Service\FormTokenService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\VarRouteService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MessagesCleanupController extends AbstractController
{
    public function __invoke(
        Request $request,
        AlertService $alert_service,
        AssetsService $assets_service,
        ConfigService $config_service,
        FormTokenService $form_token_service,
        HeadingRender $heading_render,
        LinkRender $link_render,
        MenuService $menu_service,
        PageParamsService $pp,
        VarRouteService $vr
    ):Response
    {
        if (!$config_service->get_bool('messages.fields.expires_at.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Messages cleanup submodule not enabled.');
        }

        if (!$config_service->get_bool('messages.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Messages (offers/wants) module not enabled.');
        }

        $errors = [];

        $enabled = $request->request->has('enabled');
        $after_days = $request->request->get('after_days', '');
        $expires_at_days_default = $request->request->get('expires_at_days_default', '');
        $expires_at_required = $request->request->has('expires_at_required');
        $expires_at_switch_enabled = $request->request->has('expires_at_switch_enabled');
        $expire_notify = $request->request->has('expire_notify');

        if ($expires_at_days_default === '')
        {
            $expires_at_days_default = null;
        }

        if ($request->isMethod('POST'))
        {
            if ($error_form = $form_token_service->get_error())
            {
                $errors[] = $error_form;
            }

            if (!ctype_digit((string) $after_days) || $after_days <= 0)
            {
                $errors[] = 'Het aantal dagen moet een positief getal zijn.';
            }

            if (isset($expires_at_days_default)
                && (!ctype_digit((string) $expires_at_days_default) || $expires_at_days_default <= 0))
            {
                $errors[] = 'Het aantal dagen moet een positief getal zijn.';
            }

            if (!count($errors))
            {
                $config_service->set_bool('messages.cleanup.enabled', $enabled, $pp->schema());
                $config_service->set_int('messages.cleanup.after_days', (int) $after_days, $pp->schema());
                $config_service->set_int('messages.fields.expires_at.days_default', (int) $expires_at_days_default, $pp->schema());
                $config_service->set_bool('messages.fields.expires_at.required', $expires_at_required, $pp->schema());
                $config_service->set_bool('messages.fields.expires_at.switch_enabled', $expires_at_switch_enabled, $pp->schema());
                $config_service->set_bool('messages.expire.notify', $expire_notify, $pp->schema());
                $alert_service->success('Geldigheid en opruiming instellingen van vraag en aanbod aangepast');
                $link_render->redirect('messages_cleanup', $pp->ary(), []);
            }

            $alert_service->error($errors);
        }

        if ($request->isMethod('GET'))
        {
            $enabled = $config_service->get_bool('messages.cleanup.enabled', $pp->schema());
            $after_days = $config_service->get_int('messages.cleanup.after_days', $pp->schema());
            $expires_at_days_default = $config_service->get_int('messages.fields.expires_at.days_default', $pp->schema());
            $expires_at_required = $config_service->get_bool('messages.fields.expires_at.required', $pp->schema());
            $expires_at_switch_enabled = $config_service->get_bool('messages.fields.expires_at.switch_enabled', $pp->schema());
            $expire_notify = $config_service->get_bool('messages.expire.notify', $pp->schema());
        }

        $assets_service->add(['messages_cleanup.js']);

        $heading_render->fa('trash-o');
        $heading_render->add('Geldigheid en opruiming instellingen vraag en aanbod');

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

        $out .= strtr(BulkCnst::TPL_CHECKBOX, [
            '%name%'    => 'expires_at_required',
            '%label%'   => 'De geldigheidsduur moet verplicht ingevuld worden bij vraag of aanbod M.a.w. aangemaakt vraag en aanbod vervalt altijd en blijft niet permanent geldig.',
            '%attr%'    => $expires_at_required ? ' checked' : '',
        ]);

        $out .= strtr(BulkCnst::TPL_CHECKBOX, [
            '%name%'    => 'expires_at_switch_enabled',
            '%label%'   => 'Gebruik een eenvoudige schakelaar permanent/tijdelijk bij aanmaak vraag of aanbod. Hiertoe moet hierboven een standaard geldigheidsduur ingevuld zijn en verplichte geldigheidsduur afgevinkt.',
            '%attr%'    => $expires_at_switch_enabled ? ' checked' : '',
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
            '%label%'   => $lbl_en,
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
