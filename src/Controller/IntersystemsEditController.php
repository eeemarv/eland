<?php declare(strict_types=1);

namespace App\Controller;

use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Render\SelectRender;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\FormTokenService;
use App\Service\IntersystemsService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SystemsService;
use App\Service\VarRouteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;

class IntersystemsEditController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $id,
        Db $db,
        AlertService $alert_service,
        HeadingRender $heading_render,
        IntersystemsService $intersystems_service,
        LinkRender $link_render,
        PageParamsService $pp,
        FormTokenService $form_token_service,
        SelectRender $select_render,
        ConfigService $config_service,
        SystemsService $systems_service,
        VarRouteService $vr,
        MenuService $menu_service
    ):Response
    {
        if ($request->isMethod('POST'))
        {
            [$group, $errors] = self::get_post_errors(
                $request,
                $form_token_service
            );

            if ($db->fetchColumn('select id
                from ' . $pp->schema() . '.letsgroups
                where url = ?
                    and id <> ?', [$group['url'], $id]))
            {
                $errors[] = 'Er bestaat al een interSysteem met deze url.';
            }

            if ($db->fetchColumn('select id
                from ' . $pp->schema() . '.letsgroups
                where localletscode = ?
                    and id <> ?', [$group['localletscode'], $id]))
            {
                $errors[] = 'Er bestaat al een interSysteem met deze Lokale Account Code.';
            }

            if (!count($errors))
            {
                if ($db->update($pp->schema() . '.letsgroups',
                    $group,
                    ['id' => $id]))
                {
                    $alert_service->success('InterSysteem aangepast.');

                    $intersystems_service->clear_cache($pp->schema());

                    $link_render->redirect('intersystems_show', $pp->ary(),
                        ['id'	=> $id]);
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
            $group = $db->fetchAssoc('select *
            from ' . $pp->schema() . '.letsgroups
            where id = ?', [$id]);

            if (!$group)
            {
                $alert_service->error('Systeem niet gevonden.');
                $link_render->redirect('intersystems', $pp->ary(), []);
            }
        }

        $heading_render->add('InterSysteem aanpassen');

        $btn = $link_render->btn_cancel('intersystems_show', $pp->ary(),
            ['id' => $id]);
        $btn .= '&nbsp;';
        $btn .= '<input type="submit" name="zend" value="Opslaan" ';
        $btn .= 'class="btn btn-primary btn-lg">';

        $content = self::render_form(
            $group,
            $btn,
            $db,
            $heading_render,
            $select_render,
            $form_token_service,
            $config_service,
            $link_render,
            $systems_service,
            $pp,
            $vr,
            $menu_service
        );

        return $this->render('base/navbar.html.twig', [
            'content'   => $content,
            'schema'    => $pp->schema(),
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
            'myremoteletscode'	=> $request->request->get('myremoteletscode', ''),
        ];

        if (strlen($group['groupname']) > 128)
        {
            $errors[] = 'De Systeem Naam mag maximaal 128 tekens lang zijn.';
        }

        if (strlen($group['localletscode']) > 20)
        {
            $errors[] = 'De Lokale Account Code mag maximaal 20 tekens lang zijn.';
        }

        if (strlen($group['myremoteletscode']) > 20)
        {
            $errors[] = 'De Remote Account Code mag maximaal 20 tekens lang zijn.';
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
        HeadingRender $heading_render,
        SelectRender $select_render,
        FormTokenService $form_token_service,
        ConfigService $config_service,
        LinkRender $link_render,
        SystemsService $systems_service,
        PageParamsService $pp,
        VarRouteService $vr,
        MenuService $menu_service
    ):string
    {
        $heading_render->fa('share-alt');

        $out = '<div class="card bg-info">';
        $out .= '<div class="card-body">';

        $out .= '<form method="post">';

        $out .= '<div class="form-group">';
        $out .= '<label for="groupname" class="control-label">';
        $out .= 'Systeem Naam';
        $out .= '</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-prepend">';
        $out .= '<span class="input-group-text">';
        $out .= '<span class="fa fa-share-alt"></span>';
        $out .= '</span>';
        $out .= '</span>';
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
        $out .= '<span class="input-group-prepend">';
        $out .= '<span class="input-group-text">';
        $out .= 'API';
        $out .= '</span>';
        $out .= '</span>';
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
        $out .= 'Lokale Account Code</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-prepend">';
        $out .= '<span class="input-group-text">';
        $out .= '<span class="fa fa-user"></span>';
        $out .= '</span>';
        $out .= '</span>';
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
        $out .= '<label for="myremoteletscode" class="control-label">';
        $out .= 'Remote Account Code';
        $out .= '</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-prepend">';
        $out .= '<span class="input-group-text">';
        $out .= '<span class="fa fa-user"></span>';
        $out .= '</span>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="myremoteletscode" name="myremoteletscode" ';
        $out .= 'value="';
        $out .= $group['myremoteletscode'];
        $out .= '" maxlength="20">';
        $out .= '</div>';
        $out .= '<p>';
        $out .= 'De Account Code waarmee dit Systeem bij het andere Systeem bekend is. ';
        $out .= 'Enkel in te vullen wanneer het andere Systeem draait op eLAS.';
        $out .= '</p>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="url" class="control-label">';
        $out .= 'URL ';
        $out .= '</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-prepend">';
        $out .= '<span class="input-group-text">';
        $out .= '<span class="fa fa-link"></span>';
        $out .= '</span>';
        $out .= '</span>';
        $out .= '<input type="url" class="form-control" id="url" name="url" ';
        $out .= 'value="';
        $out .= $group['url'];
        $out .= '" maxlength="256">';
        $out .= '</div>';
        $out .= '<lu>';
        $out .= '<li>';
        $out .= 'De basis-URL van het andere Systeem, inclusief het protocol, http:// of https://';
        $out .= '</li>';
        $out .= '<li>';
        $out .= 'Wanneer het een eLAND-systeem betreft, gebruik http://systeemnaam.letsa.net en ';
        $out .= 'vervang "systeemnaam" door de naam van het systeem.';
        $out .= '</li>';
        $out .= '</lu>';
        $out .= '</div>';

        $out .= $btn;
        $out .= $form_token_service->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

/*
        $out .= IntersystemsController::get_schemas_groups(
            $db,
            $config_service,
            $systems_service,
            $pp,
            $vr,
            $link_render
        );
*/

        $menu_service->set('intersystems');

        return $out;
    }
}
