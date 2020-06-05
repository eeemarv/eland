<?php declare(strict_types=1);

namespace App\Controller\Docs;

use App\Command\Docs\DocsMapEditCommand;
use App\Form\Post\Docs\DocsMapType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\MenuService;
use App\Service\FormTokenService;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Repository\DocRepository;
use App\Service\PageParamsService;
use App\Service\TypeaheadService;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DocsMapEditController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $id,
        Db $db,
        DocRepository $doc_repository,
        AlertService $alert_service,
        LinkRender $link_render,
        TypeaheadService $typeahead_service,
        FormTokenService $form_token_service,
        MenuService $menu_service,
        PageParamsService $pp,
        HeadingRender $heading_render
    ):Response
    {
        $name = trim($request->request->get('name', ''));

        $map = $doc_repository->get_map($id, $pp->schema());

        $docs_map_edit_command = new DocsMapEditCommand();

        $docs_map_edit_command->name = $map['name'];

        $form = $this->createForm(DocsMapType::class,
                $docs_map_edit_command)
            ->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $docs_map_edit_command = $form->getData();
            $name = $docs_map_edit_command->name;

            $doc_repository->update_map_name($name, $id, $pp->schema());
            $typeahead_service->clear(TypeaheadService::GROUP_DOC_MAP_NAMES);

            $alert_service->success('docs_map_edit.success');

            $link_render->redirect('docs_map', $pp->ary(),
                ['id' => $id]);
        }


        /*

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
                $test_name = $db->fetchColumn('select id
                    from ' . $pp->schema() . '.doc_maps
                    where id <> ?
                        and lower(name) = ?',
                    [$id, strtolower($name)]);

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

                $typeahead_service->clear(TypeaheadService::GROUP_DOC_MAP_NAMES);

                $link_render->redirect('docs_map', $pp->ary(),
                    ['id' => $id]);
            }

            $alert_service->error($errors);
        }

        $name = $map['name'];

        $heading_render->add('Map aanpassen: ');
        $heading_render->add_raw($link_render->link_no_attr('docs_map', $pp->ary(),
            ['id' => $id], $name));

        $out = '<div class="card fcard fcard-info" id="add">';
        $out .= '<div class="card-body">';

        $out .= '<form method="post">';

        $out .= '<div class="form-group">';
        $out .= '<label for="name" class="control-label">';
        $out .= 'Map naam</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-prepend">';
        $out .= '<span class="input-group-text form-control">';
        $out .= '<span class="fa fa-folder-o"></span>';
        $out .= '</span>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="name" name="name" ';
        $out .= 'data-typeahead="';

        $out .= $typeahead_service->ini()
            ->add('doc_map_names', [])
            ->str([
                'render_unique'    => true,
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
        */

        $menu_service->set('docs');

        return $this->render('docs/docs_map_edit.html.twig', [
//            'content'   => $out,
            'form'      => $form->createView(),
            'map'       => $map,
            'schema'    => $pp->schema(),
        ]);
    }
}
