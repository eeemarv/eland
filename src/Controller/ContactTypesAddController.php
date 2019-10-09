<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use App\Service\AlertService;
use App\Service\MenuService;
use App\Service\FormTokenService;
use App\Render\HeadingRender;
use App\Render\BtnNavRender;
use App\Render\BtnTopRender;
use App\Render\LinkRender;

class ContactTypesAddController extends AbstractController
{
    public function contact_types_add(
        Request $request,
        app $app,
        Db $db
    ):Response
    {
        if ($request->isMethod('POST'))
        {
            if ($error_token = $form_token_service->get_error())
            {
                $alert_service->error($error_token);

                $link_render->redirect('contact_types', $app['pp_ary'], []);
            }

            $tc = [];
            $tc['name'] = $request->request->get('name', '');
            $tc['abbrev'] = $request->request->get('abbrev', '');

            $error = (empty($tc['name'])) ? 'Geen naam ingevuld! ' : '';
            $error .= (empty($tc['abbrev'])) ? 'Geen afkorting ingevuld! ' : $error;

            if (!$error)
            {
                if ($db->insert($app['pp_schema'] . '.type_contact', $tc))
                {
                    $alert_service->success('Contact type toegevoegd.');
                }
                else
                {
                    $alert_service->error('Fout bij het opslaan');
                }

                $link_render->redirect('contact_types', $app['pp_ary'], []);
            }

            $alert_service->error('Corrigeer één of meerdere velden.');
        }

        $heading_render->add('Contact type toevoegen');
        $heading_render->fa('circle-o-notch');

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';
        $out .= '<form method="post">';

        $out .= '<div class="form-group">';
        $out .= '<label for="name" class="control-label">';
        $out .= 'Naam</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon" id="name_addon">';
        $out .= '<span class="fa fa-circle-o-notch"></span></span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="name" name="name" maxlength="20" ';
        $out .= 'value="';
        $out .= $tc['name'] ?? '';
        $out .= '" required>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="abbrev" class="control-label">';
        $out .= 'Afkorting</label>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="abbrev" name="abbrev" maxlength="11" ';
        $out .= 'value="';
        $out .= $tc['abbrev'] ?? '';
        $out .= '" required>';
        $out .= '</div>';

        $out .= $link_render->btn_cancel('contact_types', $app['pp_ary'], []);

        $out .= '&nbsp;';
        $out .= '<input type="submit" name="zend" ';
        $out .= 'value="Opslaan" class="btn btn-success btn-lg">';
        $out .= $form_token_service->get_hidden_input();

        $out .= '</form>';
        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('contact_types');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $app['pp_schema'],
        ]);
    }
}
