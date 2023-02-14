<?php declare(strict_types=1);

namespace App\Controller\Intersystems;

use App\Render\LinkRender;
use App\Render\SelectRender;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\FormTokenService;
use App\Service\IntersystemsService;
use App\Service\PageParamsService;
use App\Service\SystemsService;
use App\Service\VarRouteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class IntersystemsEditController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/intersystems/{id}/edit',
        name: 'intersystems_edit',
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
        AlertService $alert_service,
        IntersystemsService $intersystems_service,
        LinkRender $link_render,
        PageParamsService $pp,
        FormTokenService $form_token_service,
        SelectRender $select_render,
        ConfigService $config_service,
        SystemsService $systems_service,
        VarRouteService $vr
    ):Response
    {
        if (!$config_service->get_bool('intersystem.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Intersystem submodule (users) not enabled.');
        }

        if ($request->isMethod('POST'))
        {
            [$group, $errors] = self::get_post_errors(
                $request,
                $form_token_service
            );

            if ($db->fetchOne('select id
                from ' . $pp->schema() . '.letsgroups
                where url = ?
                    and id <> ?',
                    [$group['url'], $id],
                    [\PDO::PARAM_STR, \PDO::PARAM_INT]))
            {
                $errors[] = 'Er bestaat al een interSysteem met deze url.';
            }

            if ($db->fetchOne('select id
                from ' . $pp->schema() . '.letsgroups
                where localletscode = ?
                    and id <> ?', [$group['localletscode'], $id], [\PDO::PARAM_STR, \PDO::PARAM_INT]))
            {
                $errors[] = 'Er bestaat al een interSysteem met deze Account Code.';
            }

            if (!count($errors))
            {
                if ($db->update($pp->schema() . '.letsgroups',
                    $group,
                    ['id' => $id]))
                {
                    $alert_service->success('InterSysteem aangepast.');

                    $intersystems_service->clear_cache();

                    return $this->redirectToRoute('intersystems_show', [
                        ...$pp->ary(),
                        'id'	=> $id,
                    ]);
                }

                $alert_service->error('InterSysteem niet aangepast.');
            }
            else
            {
                $alert_service->error($errors);
            }
        }
        else
        {
            $group = $db->fetchAssociative('select *
            from ' . $pp->schema() . '.letsgroups
            where id = ?',
            [$id], [\PDO::PARAM_INT]);

            if (!$group)
            {
                $alert_service->error('Systeem niet gevonden.');

                return $this->redirectToRoute('intersystems', $pp->ary());
            }
        }

        $btn = $link_render->btn_cancel('intersystems_show', $pp->ary(),
            ['id' => $id]);
        $btn .= '&nbsp;';
        $btn .= '<input type="submit" name="zend" value="Opslaan" ';
        $btn .= 'class="btn btn-primary btn-lg">';

        $content = self::render_form(
            $group,
            $btn,
            $db,
            $select_render,
            $form_token_service,
            $config_service,
            $link_render,
            $systems_service,
            $pp,
            $vr
        );

        return $this->render('intersystems/intersystems_edit.html.twig', [
            'content'   => $content,
        ]);
    }

    public static function get_post_errors(
        Request $request,
        FormTokenService $form_token_service
    ):array
    {
        $errors = [];

        $group = [
            'url' 				=> $request->request->get('url', ''),
            'groupname' 		=> $request->request->get('groupname', ''),
            'apimethod' 		=> $request->request->get('apimethod', ''),
            'localletscode' 	=> $request->request->get('localletscode', ''),
        ];

        if (strlen($group['groupname']) > 128)
        {
            $errors[] = 'De Systeem Naam mag maximaal 128 tekens lang zijn.';
        }

        if (strlen($group['localletscode']) > 20)
        {
            $errors[] = 'De Account Code mag maximaal 20 tekens lang zijn.';
        }

        if (strlen($group['url']) > 256)
        {
            $errors[] = 'De url mag maximaal 256 tekens lang zijn.';
        }

        if ($error_token = $form_token_service->get_error())
        {
            $errors[] = $error_token;
        }

        return [$group, $errors];
    }

    public static function render_form(
        array $group,
        string $btn,
        Db $db,
        SelectRender $select_render,
        FormTokenService $form_token_service,
        ConfigService $config_service,
        LinkRender $link_render,
        SystemsService $systems_service,
        PageParamsService $pp
    ):string
    {
        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="post">';

        $out .= '<div class="form-group">';
        $out .= '<label for="groupname" class="control-label">';
        $out .= 'Systeem Naam';
        $out .= '</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<span class="fa fa-share-alt"></span></span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="groupname" name="groupname" ';
        $out .= 'value="';
        $out .= $group['groupname'];
        $out .= '" required maxlength="128">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="apimethod" class="control-label">';
        $out .= 'API methode</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= 'API</span>';
        $out .= '<select class="form-control" id="apimethod" name="apimethod" >';

        $out .= $select_render->get_options([
            'elassoap'	=> 'eLAND naar eLAND',
            'internal'	=> 'Intern (eigen Systeem - niet gebruiken)',
            'mail'		=> 'E-mail',
        ], $group['apimethod']);

        $out .= '</select>';
        $out .= '</div>';
        $out .= '<p>';
        $out .= 'Het type connectie naar het andere Systeem. ';
        $out .= '</p>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="localletscode" class="control-label">';
        $out .= 'Account Code</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<span class="fa fa-user"></span></span>';
        $out .= '<input type="text" class="form-control" id="localletscode" name="localletscode" ';
        $out .= 'value="';
        $out .= $group['localletscode'];
        $out .= '" maxlength="20">';
        $out .= '</div>';
        $out .= '<p>';
        $out .= 'De Account Code waarmee het andere ';
        $out .= 'Systeem in dit Systeem bekend is.';
        $out .= '</p>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="url" class="control-label">';
        $out .= 'URL ';
        $out .= '</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<span class="fa fa-link"></span></span>';
        $out .= '<input type="url" class="form-control" id="url" name="url" ';
        $out .= 'value="';
        $out .= $group['url'];
        $out .= '" maxlength="256">';
        $out .= '</div>';
        $out .= '<ul>';
        $out .= '<li>';
        $out .= 'De basis-URL van het andere Systeem, inclusief het protocol, http:// of https://';
        $out .= '</li>';
        $out .= '<li>';
        $out .= '<em>Gebruik http://systeemnaam.letsa.net en ';
        $out .= 'vervang "systeemnaam" door de naam van het systeem.</em>';
        $out .= '</li>';
        $out .= '</ul>';
        $out .= '</div>';

        $out .= $btn;
        $out .= $form_token_service->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $out .= IntersystemsController::get_schemas_groups(
            $db,
            $config_service,
            $systems_service,
            $pp,
            $link_render
        );

        return $out;
    }
}
