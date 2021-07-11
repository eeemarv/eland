<?php declare(strict_types=1);

namespace App\Controller\Docs;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\MenuService;
use App\Service\FormTokenService;
use App\Render\LinkRender;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\TypeaheadService;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class DocsMapEditController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/docs/map/{id}/edit',
        name: 'docs_map_edit',
        methods: ['GET', 'POST'],
        requirements: [
            'id'            => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'module'        => 'docs',
        ],
    )]

    public function __invoke(
        Request $request,
        int $id,
        Db $db,
        ConfigService $config_service,
        AlertService $alert_service,
        LinkRender $link_render,
        TypeaheadService $typeahead_service,
        FormTokenService $form_token_service,
        MenuService $menu_service,
        PageParamsService $pp
    ):Response
    {
        if (!$config_service->get_bool('docs.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Documents module not enabled.');
        }

        $errors = [];

        $name = trim($request->request->get('name', ''));

        $doc_map = $db->fetchAssociative('select *
            from ' . $pp->schema() . '.doc_maps
            where id = ?', [$id], [\PDO::PARAM_INT]);

        if (!$doc_map)
        {
            throw new NotFoundHttpException('Documents map with id ' . $id . ' not found.');
        }

        if ($request->isMethod('POST'))
        {
            if ($error_token = $form_token_service->get_error())
            {
                $errors[] = $error_token;
            }

            if (!strlen($name))
            {
                $errors[] = 'Geen map naam ingevuld!';
            }

            if (!count($errors))
            {
                $test_name = $db->fetchOne('select id
                    from ' . $pp->schema() . '.doc_maps
                    where id <> ?
                        and lower(name) = ?',
                    [$id, strtolower($name)],
                    [\PDO::PARAM_INT, \PDO::PARAM_STR]
                );

                if ($test_name)
                {
                    $errors[] = 'Er bestaat al een map met deze naam.';
                }
            }

            if (!count($errors))
            {
                $db->update($pp->schema() . '.doc_maps', [
                    'name' => $name,
                ], [
                    'id' => $id,
                ]);

                $alert_service->success('Map naam aangepast.');

                $typeahead_service->delete_thumbprint('doc_map_names',
                    $pp->ary(), []);

                $link_render->redirect('docs_map', $pp->ary(),
                    ['id' => $id]);
            }

            $alert_service->error($errors);
        }

        $name = $doc_map['name'];

        $out = '<div class="panel panel-info" id="add">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="post">';

        $out .= '<div class="form-group">';
        $out .= '<label for="name" class="control-label">';
        $out .= 'Map naam</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<span class="fa fa-folder-o"></span></span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="name" name="name" ';
        $out .= 'data-typeahead="';

        $out .= $typeahead_service->ini($pp->ary())
            ->add('doc_map_names', [])
            ->str([
                'render'    => [
                    'check' => 10,
                    'omit'  => $name,
                ],
            ]);

        $out .= '" ';
        $out .= 'value="';
        $out .= $name;
        $out .= '">';
        $out .= '</div>';

        $out .= '<span class="help-block hidden exists_query_results">';
        $out .= 'Bestaat reeds: ';
        $out .= '<span class="query_results">';
        $out .= '</span>';
        $out .= '</span>';
        $out .= '<span class="help-block hidden exists_msg">';
        $out .= 'Deze map bestaat al!';
        $out .= '</span>';

        $out .= '</div>';

        $out .= $link_render->btn_cancel('docs_map', $pp->ary(), ['id' => $id]);

        $out .= '&nbsp;';
        $out .= '<input type="submit" name="zend" value="Aanpassen" class="btn btn-primary btn-lg">';
        $out .= $form_token_service->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('docs');

        return $this->render('docs/docs_map_edit.html.twig', [
            'content'   => $out,
            'doc_map'   => $doc_map,
            'schema'    => $pp->schema(),
        ]);
    }
}
