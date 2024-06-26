<?php declare(strict_types=1);

namespace App\Controller\Intersystems;

use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\FormTokenService;
use App\Service\IntersystemsService;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class IntersystemsDelController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/intersystems/{id}/del',
        name: 'intersystems_del',
        methods: ['GET', 'POST'],
        requirements: [
            'id'            => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'module'        => 'users',
            'sub_module'    => 'intersystem',
        ],
    )]

    public function __invoke(
        Request $request,
        int $id,
        Db $db,
        ConfigService $config_service,
        IntersystemsService $intersystems_service,
        LinkRender $link_render,
        AlertService $alert_service,
        FormTokenService $form_token_service,
        PageParamsService $pp
    ):Response
    {
        if (!$config_service->get_bool('intersystem.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Intersystem submodule (users) not enabled.');
        }

        $group = $db->fetchAssociative('select *
            from ' . $pp->schema() . '.letsgroups
            where id = ?', [$id]);

        if (!$group)
        {
            $alert_service->error('Systeem niet gevonden.');

            return $this->redirectToRoute('intersystems', $pp->ary());
        }

        if ($request->isMethod('POST'))
        {
            if ($error_token = $form_token_service->get_error())
            {
                $alert_service->error($error_token);

                return $this->redirectToRoute('intersystems', $pp->ary());
            }

            if($db->delete($pp->schema() . '.letsgroups', ['id' => $id]))
            {
                $alert_service->success('InterSysteem verwijderd.');

                $intersystems_service->clear_cache();

                return $this->redirectToRoute('intersystems', $pp->ary());
            }

            $alert_service->error('InterSysteem niet verwijderd.');
        }

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<p class="text-danger">Ben je zeker dat dit interSysteem ';
        $out .= 'moet verwijderd worden?</p>';
        $out .= '<div><p>';
        $out .= '<form method="post">';

        $out .= $link_render->btn_cancel('intersystems', $pp->ary(), []);

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger btn-lg">';
        $out .= $form_token_service->get_hidden_input();

        $out .= '</form></p>';
        $out .= '</div>';

        $out .= '</div>';
        $out .= '</div>';

        return $this->render('intersystems/intersystems_del.html.twig', [
            'content'   => $out,
            'name'      => $group['groupname'],
        ]);
    }
}
