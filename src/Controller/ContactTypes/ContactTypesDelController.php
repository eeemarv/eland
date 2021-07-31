<?php declare(strict_types=1);

namespace App\Controller\ContactTypes;

use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\FormTokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\PageParamsService;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\Routing\Annotation\Route;

class ContactTypesDelController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/contact-types/{id}/del',
        name: 'contact_types_del',
        methods: ['GET', 'POST'],
        requirements: [
            'id'            => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'module'        => 'users',
            'sub_module'    => 'contact_types',
        ],
    )]

    public function __invoke(
        Request $request,
        int $id,
        Db $db,
        AlertService $alert_service,
        FormTokenService $form_token_service,
        LinkRender $link_render,
        PageParamsService $pp
    ):Response
    {
        $ct = $db->fetchAssociative('select *
            from ' . $pp->schema() . '.type_contact
            where id = ?',
            [$id], [\PDO::PARAM_INT]);

        if (in_array($ct['abbrev'], ContactTypesController::PROTECTED))
        {
            $alert_service->warning('Beschermd contact type.');
            return $this->redirectToRoute('contact_types', $pp->ary());
        }

        $hos_contact = $db->fetchOne('select id
            from ' . $pp->schema() . '.contact
            where id_type_contact = ?',
            [$id], [\PDO::PARAM_INT]) !== false;

        if ($hos_contact)
        {
            $alert_service->warning('Er is ten minste één contact
                van dit contact type, dus kan het contact type
                niet verwijderd worden.');
            return $this->redirectToRoute('contact_types', $pp->ary());
        }

        if($request->isMethod('POST'))
        {
            if ($error_token = $form_token_service->get_error())
            {
                $alert_service->error($error_token);
                return $this->redirectToRoute('contact_types', $pp->ary());
            }

            if ($db->delete($pp->schema() . '.type_contact', ['id' => $id]))
            {
                $alert_service->success('Contact type verwijderd.');
            }
            else
            {
                $alert_service->error('Fout bij het verwijderen.');
            }

            return $this->redirectToRoute('contact_types', $pp->ary());
        }

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';
        $out .= '<p>Ben je zeker dat dit contact type verwijderd mag worden?</p>';
        $out .= '<form method="post">';

        $out .= $link_render->btn_cancel('contact_types', $pp->ary(), []);

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Verwijderen" ';
        $out .= 'name="zend" class="btn btn-danger btn-lg">';
        $out .= $form_token_service->get_hidden_input();

        $out .= '</form>';
        $out .= '</div>';
        $out .= '</div>';

        return $this->render('contact_types/contact_types_del.html.twig', [
            'content'   => $out,
            'name'      => $ct['name'],
        ]);
    }
}
