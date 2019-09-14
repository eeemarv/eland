<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class docs_del
{
    public function docs_del(Request $request, app $app, string $doc_id):Response
    {
        $row = $app['xdb']->get('doc', $doc_id, $app['pp_schema']);

        if ($row)
        {
            $doc = $row['data'];
        }
        else
        {
            $app['alert']->error('Document niet gevonden');
            $app['link']->redirect('docs', $app['pp_ary'], []);
        }

        if ($request->isMethod('POST'))
        {
            $errors = [];

            if ($error_token = $app['form_token']->get_error())
            {
                $errors[] = $error_token;
            }

            if (!count($errors))
            {
                $err = $app['s3']->del($doc['filename']);

                if ($err)
                {
                    $app['monolog']->error('doc delete file fail: ' . $err,
                        ['schema' => $app['pp_schema']]);
                }

                if (isset($doc['map_id']))
                {
                    $rows = $app['xdb']->get_many(['agg_schema' => $app['pp_schema'],
                        'agg_type'	=> 'doc',
                        'data->>\'map_id\'' => $doc['map_id']]);

                    if (count($rows) < 2)
                    {
                        $app['xdb']->del('doc', $doc['map_id'], $app['pp_schema']);

                        $app['typeahead']->delete_thumbprint('doc_map_names',
                            $app['pp_ary'], []);

                        unset($doc['map_id']);
                    }
                }

                $app['xdb']->del('doc', $doc_id, $app['pp_schema']);

                $app['alert']->success('Het document werd verwijderd.');

                if (isset($doc['map_id']))
                {
                    $app['link']->redirect('docs_map', $app['pp_ary'],
                        ['map_id' => $doc['map_id']]);
                }

                $app['link']->redirect('docs', $app['pp_ary'], []);
            }

            $app['alert']->error($errors);
        }

        $app['heading']->add('Document verwijderen?');

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';
        $out .= '<form method="post">';

        $out .= '<p>';
        $out .= '<a href="';
        $out .= $app['s3_url'] . $doc['filename'];
        $out .= '" target="_self">';
        $out .= $doc['name'] ?? $doc['org_filename'];
        $out .= '</a>';
        $out .= '</p>';

        if (isset($doc['map_id']))
        {
            $out .= $app['link']->btn_cancel('docs_map', $app['pp_ary'],
                ['map_id' => $doc['map_id']]);
        }
        else
        {
            $out .= $app['link']->btn_cancel('docs', $app['pp_ary'], []);
        }

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Verwijderen" ';
        $out .= 'name="confirm_del" class="btn btn-danger">';
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
