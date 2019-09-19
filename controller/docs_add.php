<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use cnst\access as cnst_access;

class docs_add
{
    public function docs_add(Request $request, app $app, string $map_id):Response
    {
        if ($request->isMethod('POST'))
        {
            $errors = [];

            $f_file = $request->files->get('file');

            if (!$f_file)
            {
                $errors[] = 'Geen bestand geselecteerd.';
            }
            else
            {
                $ext = $f_file->guessExtension();
                $original_filename = $f_file->getClientOriginalName();
                $file_size = $f_file->getSize();
                $tmpfile = $f_file->getRealPath();

                if ($file_size > 1024 * 1024 * 10)
                {
                    $errors[] = 'Het bestand is te groot. De maximum grootte is 10MB.';
                }
            }

            $access = $request->request->get('access', '');

            if (!$access)
            {
                $errors[] = 'Vul een zichtbaarheid in';
            }

            if ($token_error = $app['form_token']->get_error())
            {
                $errors[] = $token_error;
            }

            if (count($errors))
            {
                $app['alert']->error($errors);
            }
            else
            {
                $doc_id = substr(sha1(random_bytes(16)), 0, 24);

                $filename = $app['pp_schema'] . '_d_' . $doc_id . '.' . $ext;

                $error = $app['s3']->doc_upload($filename, $tmpfile);

                if ($error)
                {
                    $app['monolog']->error('doc upload fail: ' . $error);
                    $app['alert']->error('Bestand opladen mislukt.',
                        ['schema' => $app['pp_schema']]);
                }
                else
                {
                    $doc = [
                        'filename'		=> $filename,
                        'org_filename'	=> $original_filename,
                        'access'		=> cnst_access::TO_XDB[$access],
                        'user_id'		=> $app['s_id'],
                    ];

                    $map_name = trim($request->request->get('map_name', ''));

                    if (strlen($map_name))
                    {
                        $rows = $app['xdb']->get_many(['agg_schema' => $app['pp_schema'],
                            'agg_type' => 'doc',
                            'data->>\'map_name\'' => $map_name], 'limit 1');

                        if (count($rows))
                        {
                            $map = reset($rows)['data'];
                            $map_id = reset($rows)['eland_id'];
                        }

                        if (!$map)
                        {
                            $map_id = substr(sha1(random_bytes(16)), 0, 24);

                            $map = ['map_name' => $map_name];

                            $app['xdb']->set('doc', $map_id, $map, $app['pp_schema']);

                            $app['typeahead']->delete_thumbprint('doc_map_names',
                                $app['pp_ary'], []);
                        }

                        $doc['map_id'] = $map_id;
                    }

                    $name = trim($request->request->get('name', ''));

                    if ($name)
                    {
                        $doc['name'] = $name;
                    }

                    $app['xdb']->set('doc', $doc_id, $doc, $app['pp_schema']);


                    $app['alert']->success('Het bestand is opgeladen.');

                    if (isset($doc['map_id']))
                    {
                        $app['link']->redirect('docs_map', $app['pp_ary'],
                            ['map_id' => $doc['map_id']]);
                    }

                    $app['link']->redirect('docs', $app['pp_ary'], []);
                }
            }
        }

        if ($map_id)
        {
            $row = $app['xdb']->get('doc', $map_id, $app['pp_schema']);

            if ($row)
            {
                $map_name = $row['data']['map_name'];
            }
        }

        $app['heading']->add('Nieuw document opladen');
        $app['heading']->fa('files-o');

        $out = '<div class="panel panel-info" id="add">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="post" enctype="multipart/form-data">';

        $out .= '<div class="form-group">';
        $out .= '<label for="file" class="control-label">';
        $out .= 'Bestand</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-file-o"></i>';
        $out .= '</span>';
        $out .= '<input type="file" class="form-control" id="file" name="file" ';
        $out .= 'required>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="name" class="control-label">';
        $out .= 'Naam (optioneel)</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<span class="fa fa-file-o"></span></span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="name" name="name">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= $app['item_access']->get_radio_buttons('access', $access ?? '', 'docs');

        $out .= '<div class="form-group">';
        $out .= '<label for="map_name" class="control-label">';
        $out .= 'Map</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-folder-o"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" id="map_name" name="map_name" value="';
        $out .= $map_name ?? '';
        $out .= '" ';
        $out .= 'data-typeahead="';

        $out .= $app['typeahead']->ini($app['pp_ary'])
            ->add('doc_map_names', [])
            ->str();

        $out .= '">';
        $out .= '</div>';
        $out .= '<p>Optioneel. Creëer een nieuwe map of ';
        $out .= 'selecteer een bestaande.</p>';
        $out .= '</div>';

        if ($map_id)
        {
            $out .= $app['link']->btn_cancel('docs_map', $app['pp_ary'],
                ['map_id' => $map_id]);
        }
        else
        {
            $out .= $app['link']->btn_cancel('docs', $app['pp_ary'], []);
        }

        $out .= '&nbsp;';
        $out .= '<input type="submit" name="zend" ';
        $out .= 'value="Document opladen" class="btn btn-success btn-lg">';
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
