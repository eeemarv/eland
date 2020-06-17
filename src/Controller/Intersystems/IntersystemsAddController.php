<?php declare(strict_types=1);

namespace App\Controller\Intersystems;

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

class IntersystemsAddController extends AbstractController
{
    public function __invoke(
        Request $request,
        Db $db,
        AlertService $alert_service,
        ConfigService $config_service,
        FormTokenService $form_token_service,
        HeadingRender $heading_render,
        IntersystemsService $intersystems_service,
        LinkRender $link_render,
        SelectRender $select_render,
        SystemsService $systems_service,
        PageParamsService $pp,
        VarRouteService $vr,
        MenuService $menu_service
    ):Response
    {
        if ($request->isMethod('POST'))
        {
            [$group, $errors] = IntersystemsEditController::get_post_errors(
                $request,
                $form_token_service
            );

            if ($group['url'] !== '')
            {
                if ($db->fetchColumn('select id
                    from ' . $pp->schema() . '.letsgroups
                    where url = ?', [$group['url']]))
                {
                    $errors[] = 'Er bestaat al een interSysteem met deze URL.';
                }
            }

            if ($db->fetchColumn('select id
                from ' . $pp->schema() . '.letsgroups
                where localletscode = ?', [$group['localletscode']]))
            {
                $errors[] = 'Er bestaat al een interSysteem met deze Lokale Account Code.';
            }

            if (!count($errors))
            {
                if ($db->insert($pp->schema() . '.letsgroups', $group))
                {
                    $alert_service->success('Intersysteem opgeslagen.');

                    $id = $db->lastInsertId($pp->schema() . '.letsgroups_id_seq');

                    $intersystems_service->clear_cache($pp->schema());

                    $link_render->redirect('intersystems_show', $pp->ary(),
                        ['id' => $id]);
                }

                $alert_service->error('InterSysteem niet opgeslagen.');
            }
            else
            {
                $alert_service->error($errors);
            }
        }
        else
        {
            $group = [
                'groupname' 		=> '',
                'apimethod'			=> 'elassoap',
                'localletscode'		=> '',
                'myremoteletscode'	=> '',
                'url'				=> '',
            ];

            if ($add_schema = $request->query->get('add_schema'))
            {
                if ($systems_service->get_system($add_schema))
                {
                    $group['url'] = $systems_service->get_legacy_eland_origin($add_schema);
                    $group['groupname'] = $config_service->get('systemname', $add_schema);
                    $group['localletscode'] = $config_service->get('systemtag', $add_schema);
                }
            }
        }

        $heading_render->add('InterSysteem toevoegen');

        $btn = $link_render->btn_cancel('intersystems', $pp->ary(), []);
        $btn .= '&nbsp;';
        $btn .= '<input type="submit" name="zend" value="Opslaan" ';
        $btn .= 'class="btn btn-success btn-lg">';

        $content = IntersystemsEditController::render_form(
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

        return $this->render('intersystems/intersystems_add.html.twig', [
            'content'   => $content,
            'schema'    => $pp->schema(),
        ]);
    }
}
