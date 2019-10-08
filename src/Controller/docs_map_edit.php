<?php declare(strict_types=1);

namespace App\Controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class docs_map_edit
{
    public function docs_map_edit(Request $request, app $app, string $map_id):Response
    {
        $row = $app['xdb']->get('doc', $map_id, $app['pp_schema']);

        if ($row)
        {
            $map_name = $row['data']['map_name'];
        }

        if (!$map_name)
        {
            $app['alert']->error('Map niet gevonden.');
            $app['link']->redirect('docs', $app['pp_ary'], []);
        }

        if ($request->isMethod('POST'))
        {
            if ($error_token = $app['form_token']->get_error())
            {
                $app['alert']->error($error_token);

                $app['link']->redirect('docs_map', $app['pp_ary'],
                    ['map_id' => $map_id]);
            }

            $posted_map_name = trim($request->request->get('map_name', ''));

            if (!strlen($posted_map_name))
            {
                $errors[] = 'Geen map naam ingevuld!';
            }

            if (!count($errors))
            {

                $rows = $app['xdb']->get_many(['agg_schema' => $app['pp_schema'],
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
                $app['xdb']->set('doc', $map_id, [
                        'map_name' => $posted_map_name
                    ], $app['pp_schema']);

                $app['alert']->success('Map naam aangepast.');

                $app['typeahead']->delete_thumbprint('doc_map_names',
                    $app['pp_ary'], []);

                $app['link']->redirect('docs_map', $app['pp_ary'],
                    ['map_id' => $map_id]);
            }

            $app['alert']->error($errors);
        }

        $app['heading']->add('Map aanpassen: ');
        $app['heading']->add_raw($app['link']->link_no_attr('docs_map', $app['pp_ary'],
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

        $out .= $app['typeahead']->ini($app['pp_ary'])
            ->add('doc_map_names', [])
            ->str();

        $out .= '" ';
        $out .= 'value="';
        $out .= $map_name;
        $out .= '">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= $app['link']->btn_cancel('docs_map', $app['pp_ary'], ['map_id' => $map_id]);

        $out .= '&nbsp;';
        $out .= '<input type="submit" name="zend" value="Aanpassen" class="btn btn-primary btn-lg">';
        $out .= $app['form_token']->get_hidden_input();

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
