<?php declare(strict_types=1);

namespace App\Controller;

use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\FormTokenService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use controller\contact_types;
use Doctrine\DBAL\Connection as Db;

class ContactTypesEditController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $id,
        Db $db,
        FormTokenService $form_token_service,
        HeadingRender $heading_render,
        AlertService $alert_service,
        LinkRender $link_render,
        PageParamsService $pp,
        MenuService $menu_service
    ):Response
    {
        $tc_prefetch = $db->fetchAssoc('select *
            from ' . $pp->schema() . '.type_contact
            where id = ?', [$id]);

        if (in_array($tc_prefetch['abbrev'], contact_types::PROTECTED))
        {
            $alert_service->warning('Beschermd contact type.');
            $link_render->redirect('contact_types', $pp->ary(), []);
        }

        if($request->isMethod('POST'))
        {
            if ($error_token = $form_token_service->get_error())
            {
                $alert_service->error($error_token);
                $link_render->redirect('contact_types', $pp->ary(), []);
            }

            $tc = [
                'name'		=> $request->request->get('name', ''),
                'abbrev'	=> $request->request->get('abbrev', ''),
                'id'		=> $id,
            ];

            $error = empty($tc['name']) ? 'Geen naam ingevuld! ' : '';
            $error .= empty($tc['abbrev']) ? 'Geen afkorting ingevuld! ' : $error;

            if (!$error)
            {
                if ($db->update($pp->schema() . '.type_contact',
                    $tc,
                    ['id' => $id]))
                {
                    $alert_service->success('Contact type aangepast.');
                    $link_render->redirect('contact_types', $pp->ary(), []);
                }
                else
                {
                    $alert_service->error('Fout bij het opslaan.');
                }
            }
            else
            {
                $alert_service->error('Fout in één of meer velden. ' . $error);
            }
        }
        else
        {
            $tc = $tc_prefetch;
        }

        $heading_render->add('Contact type aanpassen');
        $heading_render->fa('circle-o-notch');

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';
        $out .= '<form method="post">';

        $out .= '<div class="form-group">';
        $out .= '<label for="name" class="control-label">Naam</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon" id="name_addon">';
        $out .= '<span class="fa fa-circle-o-notch"></span></span>';
        $out .= '<input type="text" class="form-control" id="name" ';
        $out .= 'name="name" maxlength="20" ';
        $out .= 'value="';
        $out .= $tc['name'];
        $out .= '" required>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="abbrev" class="control-label">Afkorting</label>';
        $out .= '<input type="text" class="form-control" id="abbrev" ';
        $out .= 'name="abbrev" maxlength="11" ';
        $out .= 'value="';
        $out .= $tc['abbrev'];
        $out .= '" required>';
        $out .= '</div>';

        $out .= $link_render->btn_cancel('contact_types', $pp->ary(), []);

        $out .= '&nbsp;';
        $out .= '<input type="submit" name="zend" ';
        $out .= 'value="Opslaan" class="btn btn-primary btn-lg">';
        $out .= $form_token_service->get_hidden_input();

        $out .= '</form>';
        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('contact_types');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
