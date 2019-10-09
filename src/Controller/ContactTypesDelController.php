<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use controller\contact_types;
use Doctrine\DBAL\Connection as Db;
use App\Service\AlertService;
use App\Service\MenuService;
use App\Service\FormTokenService;
use App\Render\HeadingRender;
use App\Render\BtnNavRender;
use App\Render\BtnTopRender;
use App\Render\LinkRender;

class ContactTypesDelController extends AbstractController
{
    public function contact_types_del(
        Request $request,
        app $app,
        int $id,
        Db $db
    ):Response
    {
        $ct = $db->fetchAssoc('select *
            from ' . $app['pp_schema'] . '.type_contact
            where id = ?', [$id]);

        if (in_array($ct['abbrev'], contact_types::PROTECTED))
        {
            $alert_service->warning('Beschermd contact type.');
            $link_render->redirect('contact_types', $app['pp_ary'], []);
        }

        if ($db->fetchColumn('select id
            from ' . $app['pp_schema'] . '.contact
            where id_type_contact = ?', [$id]))
        {
            $alert_service->warning('Er is ten minste één contact
                van dit contact type, dus kan het contact type
                niet verwijderd worden.');
            $link_render->redirect('contact_types', $app['pp_ary'], []);
        }

        if($request->isMethod('POST'))
        {
            if ($error_token = $form_token_service->get_error())
            {
                $alert_service->error($error_token);
                $link_render->redirect('contact_types', $app['pp_ary'], []);
            }

            if ($db->delete($app['pp_schema'] . '.type_contact', ['id' => $id]))
            {
                $alert_service->success('Contact type verwijderd.');
            }
            else
            {
                $alert_service->error('Fout bij het verwijderen.');
            }

            $link_render->redirect('contact_types', $app['pp_ary'], []);
        }

        $heading_render->add('Contact type verwijderen: ' . $ct['name']);
        $heading_render->fa('circle-o-notch');

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';
        $out .= '<p>Ben je zeker dat dit contact type verwijderd mag worden?</p>';
        $out .= '<form method="post">';

        $out .= $link_render->btn_cancel('contact_types', $app['pp_ary'], []);

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Verwijderen" ';
        $out .= 'name="zend" class="btn btn-danger btn-lg">';
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
