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

        $category_enabled = $request->request->has('category_enabled');
        $expires_at_enabled = $request->request->has('expires_at_enabled');
        $units_enabled = $request->request->has('units_enabled');

        if ($request->isMethod('POST'))
        {
            if ($error_form = $form_token_service->get_error())
            {
                $errors[] = $error_form;
            }

            if (!count($errors))
            {
                $config_service->set_bool('messages.fields.category.enabled', $category_enabled, $pp->schema());
                $config_service->set_bool('messages.fields.expires_at.enabled', $expires_at_enabled, $pp->schema());
                $config_service->set_bool('messages.fields.units.enabled', $units_enabled, $pp->schema());

                $alert_service->success('Submodules/velden vraag en aanbod aangepast');
                $link_render->redirect('messages_modules', $pp->ary(), []);
            }

            $alert_service->error($errors);
        }

        if ($request->isMethod('GET'))
        {
            $category_enabled = $config_service->get_bool('messages.fields.category.enabled', $pp->schema());
            $expires_at_enabled = $config_service->get_bool('messages.fields.expires_at.enabled', $pp->schema());
            $units_enabled = $config_service->get_bool('messages.fields.units.enabled', $pp->schema());
        }

        $heading_render->fa('newspaper-o');
        $heading_render->add('Submodules en velden vraag en aanbod');

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="post">';

        $out .= strtr(BulkCnst::TPL_CHECKBOX, [
            '%name%'    => 'category_enabled',
            '%label%'   => 'Categorieën',
            '%attr%'    => $category_enabled ? ' checked' : '',
        ]);

        $out .= strtr(BulkCnst::TPL_CHECKBOX, [
            '%name%'    => 'expires_at_enabled',
            '%label%'   => 'Geldigheid en opruiming',
            '%attr%'    => $expires_at_enabled ? ' checked' : '',
        ]);

        $out .= strtr(BulkCnst::TPL_CHECKBOX, [
            '%name%'    => 'units_enabled',
            '%label%'   => 'Richtprijs',
            '%attr%'    => $units_enabled ? ' checked' : '',
        ]);

        $out .= $link_render->btn_cancel($vr->get('messages'), $pp->ary(), []);

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Opslaan" name="zend" class="btn btn-primary btn-lg">';
        $out .= $form_token_service->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $out .= '<ul>';
        $out .= '<li>Noot: Uitgeschakelde submodules blijven hun data behouden.</li>';
        $out .= '</ul>';

        $menu_service->set('messages_modules');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
