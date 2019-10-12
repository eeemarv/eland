<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\MenuService;
use App\Service\FormTokenService;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\PageParamsService;
use App\Service\TypeaheadService;
use App\Service\XdbService;

class DocsMapEditController extends AbstractController
{
    public function docs_map_edit(
        Request $request,
        string $map_id,
        XdbService $xdb_service,
        AlertService $alert_service,
        LinkRender $link_render,
        TypeaheadService $typeahead_service,
        FormTokenService $form_token_service,
        MenuService $menu_service,
        PageParamsService $pp,
        HeadingRender $heading_render
    ):Response
    {
        $row = $xdb_service->get('doc', $map_id, $pp->schema());

        if ($row)
        {
            $map_name = $row['data']['map_name'];
        }

        if (!$map_name)
        {
            $alert_service->error('Map niet gevonden.');
            $link_render->redirect('docs', $pp->ary(), []);
        }

        if ($request->isMethod('POST'))
        {
            if ($error_token = $form_token_service->get_error())
            {
                $alert_service->error($error_token);

                $link_render->redirect('docs_map', $pp->ary(),
                    ['map_id' => $map_id]);
            }

            $posted_map_name = trim($request->request->get('map_name', ''));

            if (!strlen($posted_map_name))
            {
                $errors[] = 'Geen map naam ingevuld!';
            }

            if (!count($errors))
            {

                $rows = $xdb_service->get_many(['agg_schema' => $pp->schema(),
                    'agg_type' => 'doc',
                    'eland_id' => ['<>' => $map_id],
                    'data->>\'map_name\'' => $posted_map_name]);

                if (count($rows))
                {
                    $errors[] = 'Er bestaat al een map met deze naam!';
                }
            }

            if (!count($errors))
            {
                $xdb_service->set('doc', $map_id, [
                        'map_name' => $posted_map_name
                    ], $pp->schema());

                $alert_service->success('Map naam aangepast.');

                $typeahead_service->delete_thumbprint('doc_map_names',
                    $pp->ary(), []);

                $link_render->redirect('docs_map', $pp->ary(),
                    ['map_id' => $map_id]);
            }

            $alert_service->error($errors);
        }

        $heading_render->add('Map aanpassen: ');
        $heading_render->add_raw($link_render->link_no_attr('docs_map', $pp->ary(),
            ['map_id' => $map_id], $map_name));

        $out = '<div class="panel panel-info" id="add">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="post">';

        $out .= '<div class="form-group">';
        $out .= '<label for="map_name" class="control-label">';
        $out .= 'Map naam</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<span class="fa fa-folder-o"></span></span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="map_name" name="map_name" ';
        $out .= 'data-typeahead="';

        $out .= $typeahead_service->ini($pp->ary())
            ->add('doc_map_names', [])
            ->str();

        $out .= '" ';
        $out .= 'value="';
        $out .= $map_name;
        $out .= '">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= $link_render->btn_cancel('docs_map', $pp->ary(), ['map_id' => $map_id]);

        $out .= '&nbsp;';
        $out .= '<input type="submit" name="zend" value="Aanpassen" class="btn btn-primary btn-lg">';
        $out .= $form_token_service->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('docs');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
