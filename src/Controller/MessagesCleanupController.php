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

class MessagesCleanupController extends AbstractController
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

        $enabled = $request->request->has('enabled');
        $after_days = $request->request->get('after_days', '');
        $expires_at_days_default = $request->request->get('expires_at_days_default', '');
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
                $config_service->set_bool('messages.expire.notify', $expire_notify, $pp->schema());
                $alert_service->success('Opruim instellingen Vraag/aanbod aangepast');
                $link_render->redirect('messages_cleanup', $pp->ary(), []);
            }

            $alert_service->error($errors);
        }

        if ($request->isMethod('GET'))
        {
            $enabled = $config_service->get_bool('messages.cleanup.enabled', $pp->schema());
            $after_days = $config_service->get_int('messages.cleanup.after_days', $pp->schema());
            $expires_at_days_default = $config_service->get_int('messages.fields.expires_at.days_default', $pp->schema());
            $expire_notify = $config_service->get_bool('messages.expire.notify', $pp->schema());
        }

        $heading_render->fa('trash-o');
        $heading_render->add('Opruiming vraag en aanbod');

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

    public static function format(string $value):string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    static public function get_radio(
        array $radio_ary,
        string $name,
        string $selected,
        bool $required
    ):string
    {
        $out = '';

        foreach ($radio_ary as $value => $label)
        {
            $out .= '<label class="radio-inline">';
            $out .= '<input type="radio" name="' . $name . '" ';
            $out .= 'value="' . $value . '"';
            $out .= (string) $value === $selected ? ' checked' : '';
            $out .= $required ? ' required' : '';
            $out .= '>&nbsp;';
            $out .= '<span class="btn btn-default">';
            $out .= $label;
            $out .= '</span>';
            $out .= '</label>';
        }

        return $out;
    }
}
