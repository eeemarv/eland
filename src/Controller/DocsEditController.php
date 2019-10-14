<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Cnst\AccessCnst;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\ItemAccessService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\TypeaheadService;
use App\Service\XdbService;

class DocsEditController extends AbstractController
{
    public function __invoke(
        Request $request,
        string $doc_id,
        XdbService $xdb_service,
        AlertService $alert_service,
        HeadingRender $heading_render,
        ItemAccessService $item_access_service,
        LinkRender $link_render,
        TypeaheadService $typeahead_service,
        MenuService $menu_service,
        PageParamsService $pp,
        string $env_s3_url
    ):Response
    {
        $row = $xdb_service->get('doc', $doc_id, $pp->schema());

        if ($row)
        {
            $doc = $row['data'];

            $access = AccessCnst::FROM_XDB[$doc['access']];
            $doc['ts'] = $row['event_time'];
        }
        else
        {
            $alert_service->error('Document niet gevonden');
            $link_render->redirect('docs', $pp->ary(), []);
        }

        if ($request->isMethod('POST'))
        {
            $errors = [];

            $access = $request->request->get('access', '');

            if (!$access)
            {
                $errors[] = 'Vul een zichtbaarheid in.';
                $access_xdb = '';
            }
            else
            {
                $access_xdb = AccessCnst::TO_XDB[$access];
            }

            $update = [
                'user_id'		=> $doc['user_id'],
                'filename'		=> $doc['filename'],
                'org_filename'	=> $doc['org_filename'],
                'name'			=> trim($request->request->get('name', '')),
                'access'		=> $access_xdb,
            ];

            if (!count($errors))
            {
                $map_name = trim($request->request->get('map_name', ''));

                if (strlen($map_name))
                {
                    $rows = $xdb_service->get_many(['agg_type' => 'doc',
                        'agg_schema' => $pp->schema(),
                        'data->>\'map_name\'' => $map_name], 'limit 1');

                    if (count($rows))
                    {
                        $map = reset($rows)['data'];
                        $map['id'] = reset($rows)['eland_id'];
                    }
                    else
                    {
                        $map = ['map_name' => $map_name];

                        $mid = substr(sha1(random_bytes(16)), 0, 24);

                        $xdb_service->set('doc', $mid, $map, $pp->schema());

                        $map['id'] = $mid;
                    }

                    $update['map_id'] = $map['id'];
                }
                else
                {
                    $update['map_id'] = '';
                }

                if (isset($doc['map_id'])
                    && ((isset($update['map_id']) && $update['map_id'] !== $doc['map_id'])
                        || !strlen($map_name)))
                {
                    $rows = $xdb_service->get_many(['agg_type' => 'doc',
                        'agg_schema' => $pp->schema(),
                        'data->>\'map_id\'' => $doc['map_id']]);

                    if (count($rows) < 2)
                    {
                        $xdb_service->del('doc', $doc['map_id'], $pp->schema());
                    }
                }

                $xdb_service->set('doc', $doc_id, $update, $pp->schema());

                $typeahead_service->delete_thumbprint('doc_map_names',
                    $pp->ary(), []);

                $alert_service->success('Document aangepast');

                if (!$update['map_id'])
                {
                    $link_render->redirect('docs', $pp->ary(), []);
                }

                $link_render->redirect('docs_map', $pp->ary(),
                    ['map_id' => $update['map_id']]);
            }

            $alert_service->error($errors);
        }

        if (isset($doc['map_id']) && $doc['map_id'] != '')
        {
            $map_id = $doc['map_id'];

            $map = $xdb_service->get('doc', $map_id,
                $pp->schema())['data'];
        }

        $heading_render->add('Document aanpassen');

        $out = '<div class="panel panel-info" id="add">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="post">';

        $out .= '<div class="form-group">';
        $out .= '<label for="location" class="control-label">';
        $out .= 'Locatie</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<span class="fa fa-file-o"></span></span>';
        $out .= '<input type="text" class="form-control" id="location" ';
        $out .= 'name="location" value="';
        $out .= $env_s3_url . $doc['filename'];
        $out .= '" readonly>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="org_filename" class="control-label">';
        $out .= 'Originele bestandsnaam</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<span class="fa fa-file-o"></span></span>';
        $out .= '<input type="text" class="form-control" id="org_filename" ';
        $out .= 'name="org_filename" value="';
        $out .= $doc['org_filename'];
        $out .= '" readonly>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="name" class="control-label">';
        $out .= 'Naam (optioneel)</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<span class="fa fa-file-o"></span></span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="name" name="name" value="';
        $out .= $doc['name'];
        $out .= '">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= $item_access_service->get_radio_buttons('access', $access, 'docs');

        $map_name = $map['map_name'] ?? '';

        $out .= '<div class="form-group">';
        $out .= '<label for="map_name" class="control-label">';
        $out .= 'Map</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-folder-o"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" id="map_name" name="map_name" value="';
        $out .= $map_name;
        $out .= '" ';
        $out .= 'data-typeahead="';

        $out .= $typeahead_service->ini($pp->ary())
            ->add('doc_map_names', [])
            ->str();

        $out .= '">';
        $out .= '</div>';
        $out .= '<p>Optioneel. Creëer een nieuwe map ';
        $out .= 'of selecteer een bestaande.</p>';
        $out .= '</div>';

        $out .= $link_render->btn_cancel('docs', $pp->ary(), []);

        $out .= '&nbsp;';
        $out .= '<input type="submit" name="zend" value="Aanpassen" class="btn btn-primary btn-lg">';

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
