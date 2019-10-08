<?php declare(strict_types=1);

namespace App\Controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use cnst\access as cnst_access;

class docs_edit
{
    public function docs_edit(Request $request, app $app, string $doc_id):Response
    {
        $row = $app['xdb']->get('doc', $doc_id, $app['pp_schema']);

        if ($row)
        {
            $doc = $row['data'];

            $access = cnst_access::FROM_XDB[$doc['access']];
            $doc['ts'] = $row['event_time'];
        }
        else
        {
            $app['alert']->error('Document niet gevonden');
            $app['link']->redirect('docs', $app['pp_ary'], []);
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
                $access_xdb = cnst_access::TO_XDB[$access];
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
                    $rows = $app['xdb']->get_many(['agg_type' => 'doc',
                        'agg_schema' => $app['pp_schema'],
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

                        $app['xdb']->set('doc', $mid, $map, $app['pp_schema']);

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
                    $rows = $app['xdb']->get_many(['agg_type' => 'doc',
                        'agg_schema' => $app['pp_schema'],
                        'data->>\'map_id\'' => $doc['map_id']]);

                    if (count($rows) < 2)
                    {
                        $app['xdb']->del('doc', $doc['map_id'], $app['pp_schema']);
                    }
                }

                $app['xdb']->set('doc', $doc_id, $update, $app['pp_schema']);

                $app['typeahead']->delete_thumbprint('doc_map_names',
                    $app['pp_ary'], []);

                $app['alert']->success('Document aangepast');

                if (!$update['map_id'])
                {
                    $app['link']->redirect('docs', $app['pp_ary'], []);
                }

                $app['link']->redirect('docs_map', $app['pp_ary'],
                    ['map_id' => $update['map_id']]);
            }

            $app['alert']->error($errors);
        }

        if (isset($doc['map_id']) && $doc['map_id'] != '')
        {
            $map_id = $doc['map_id'];

            $map = $app['xdb']->get('doc', $map_id,
                $app['pp_schema'])['data'];
        }

        $app['heading']->add('Document aanpassen');

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
        $out .= $app['s3_url'] . $doc['filename'];
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

        $out .= $app['item_access']->get_radio_buttons('access', $access, 'docs');

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

        $out .= $app['typeahead']->ini($app['pp_ary'])
            ->add('doc_map_names', [])
            ->str();

        $out .= '">';
        $out .= '</div>';
        $out .= '<p>Optioneel. Creëer een nieuwe map ';
        $out .= 'of selecteer een bestaande.</p>';
        $out .= '</div>';

        $out .= $app['link']->btn_cancel('docs', $app['pp_ary'], []);

        $out .= '&nbsp;';
        $out .= '<input type="submit" name="zend" value="Aanpassen" class="btn btn-primary btn-lg">';

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $app['menu']->set('docs');

        return $app->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $app['pp_schema'],
        ]);
    }
}
