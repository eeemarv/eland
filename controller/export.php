<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class export
{
    public function export(Request $request, app $app):Response
    {
        set_time_limit(60);

        $db_elas = $request->query->get('db_elas', '') ? true : false;
        $db_eland_aggs = !$db_elas && ($request->query->get('db_eland_aggs', '') ? true : false);
        $db_eland_events = !$db_eland_aggs && ($request->query->get('db_eland_events', '') ? true : false);
        $db_download = $db_elas || $db_eland_aggs || $db_eland_events;

        $exec_en = function_exists('exec');

        $export_ary = [
            'users'		=> [
                'label'		=> 'Gebruikers',
                'sql'		=> 'select *
                    from ' . $app['tschema'] . '.users
                    order by letscode',
                'columns'	=> [
                    'letscode',
                    'cdate',
                    'comments',
                    'hobbies',
                    'name',
                    'postcode',
                    'login',
                    'mailinglist',
                    'password',
                    'accountrole',
                    'status',
                    'lastlogin',
                    'minlimit',
                    'maxlimit',
                    'fullname',
                    'admincomment',
                    'adate' => 'activeringsdatum'
                ],
            ],
            'contacts'	=> [
                'label'	=> 'Contactgegevens',
                'sql'	=> 'select c.*, tc.abbrev, u.letscode, u.name
                    from ' . $app['tschema'] . '.contact c, ' .
                        $app['tschema'] . '.type_contact tc, ' .
                        $app['tschema'] . '.users u
                    where c.id_type_contact = tc.id
                        and c.id_user = u.id',
                'columns'	=> [
                    'letscode',
                    'username',
                    'abbrev',
                    'comments',
                    'value',
                    'flag_public',
                ],
            ],
            'categories'	=> [
                'label'		=> 'Categorieën',
                'sql'		=> 'select * from ' . $app['tschema'] . '.categories',
                'columns'	=> [
                    'name',
                    'id_parent',
                    'description',
                    'cdate',
                    'fullname',
                    'leafnote',
                ],
            ],
            'messages'	=> [
                'label'		=> 'Vraag en Aanbod',
                'sql'		=> 'select m.*, u.name as username, u.letscode
                    from ' . $app['tschema'] . '.messages m, ' .
                        $app['tschema'] . '.users u
                    where m.id_user = u.id
                        and validity > ?',
                'sql_bind'	=> [gmdate('Y-m-d H:i:s')],
                'columns'	=> [
                    'letscode',
                    'username',
                    'cdate',
                    'validity',
                    'content',
                    'msg_type',
                ],
            ],
            'transactions'	=> [
                'label'		=> 'Transacties',
                'sql'		=> 'select t.transid, t.description,
                                    concat(fu.letscode, \' \', fu.name) as from_user,
                                    concat(tu.letscode, \' \', tu.name) as to_user,
                                    t.cdate, t.real_from, t.real_to, t.amount
                                from ' . $app['tschema'] . '.transactions t, ' .
                                    $app['tschema'] . '.users fu, ' .
                                    $app['tschema'] . '.users tu
                                where t.id_to = tu.id
                                    and t.id_from = fu.id
                                order by t.date desc',
                'columns'	=> [
                    'cdate'			=> 'Datum',
                    'from_user'		=> 'Van',
                    'real_from'		=> 'interSysteem',
                    'to_user'		=> 'Aan',
                    'real_to'		=> 'interSysteem',
                    'amount'		=> 'Bedrag',
                    'description'	=> 'Dienst',
                    'transid'		=> 'transactie id',
                ],
            ],
        ];

        $buttons = '';
        $r = "\r\n";

        if ($exec_en && $db_download)
        {
            $filename = $app['tschema'] . '-';
            $filename .= $db_elas ? 'elas-db' : 'eland-xdb';
            $filename .= $db_eland_aggs ? '-aggs' : '';
            $filename .= $db_eland_events ? '-events' : '';
            $filename .= gmdate('-Y-m-d-H-i-s-');
            $filename .= substr(sha1(microtime()), 0, 4);
            $filename .= '.';
            $filename .= $db_elas ? 'sql' : 'csv';

            if ($db_elas)
            {
                $exec = 'pg_dump --dbname=';
                $exec .= getenv('DATABASE_URL');
                $exec .= ' --schema=' . $app['tschema'];
                $exec .= ' --no-owner --no-acl > ' . $filename;
            }
            else
            {
                $exec = 'psql -d ';
                $exec .= getenv('DATABASE_URL');
                $exec .= ' -c "\\copy ';
                $exec .= '(select * ';
                $exec .= 'from xdb.';
                $exec .= $db_eland_aggs ? 'aggs' : 'events';
                $exec .= ' where agg_schema = \'';
                $exec .= $app['tschema'] . '\')';
                $exec .= ' TO ' . $filename;
                $exec .= ' with delimiter \',\' ';
                $exec .= 'csv header;"';
            }

            exec($exec);

            $handle = fopen($filename, 'rb');

            if (!$handle)
            {
                exit;
            }

            $out = '';

            while (!feof($handle))
            {
                $out .= fread($handle, 8192);
            }

            fclose($handle);

            unlink($filename);

            $download_log = $db_elas ? 'elas db sql' : 'eland xdb csv ';
            $download_log .= $db_eland_aggs ? 'aggs' : '';
            $download_log .= $db_eland_events ? 'events' : '';

            $app['monolog']->info($download_log . ' downloaded',
                ['schema' => $app['tschema']]);

            $response = new Response($out);

            $response->headers->set('Content-Type', 'application/octet-stream');
            $response->headers->set('Content-Disposition', 'attachment; filename=' . $filename);
            $response->headers->set('Content-Transfer-Encoding', 'binary');

            return $response;
        }

        foreach ($export_ary as $ex_key => $export)
        {
            if (isset($_GET[$ex_key]))
            {
                $columns = $fields = [];

                $sql_bind = $export['sql_bind'] ?? [];

                $data = $app['db']->fetchAll($export['sql'], $sql_bind);

                foreach($export['columns'] as $key => $name)
                {
                    $fields[] = $name;

                    $columns[] = (ctype_digit((string) $key)) ? $name : $key;
                }

                $out = '"' . implode('","', $fields) . '"' . $r;

                foreach($data as $row)
                {
                    $fields = [];

                    foreach($columns as $c)
                    {
                        $fields[] = $row[$c] ?? '';
                    }

                    $out .= '"' . implode('","', $fields) . '"' . $r;
                }

                $app['monolog']->info('csv ' . $ex_key . ' exported.',
                    ['schema' => $app['tschema']]);

                $filename = 'elas-' . $ex_key . '-'.date('Y-m-d-H-i-S').'.csv';

                $response = new Response($out);

                $response->headers->set('Content-Type', 'text/csv');
                $response->headers->set('Content-Disposition', 'attachment; filename=' . $filename);

                return $response;
            }

            $buttons .= '<form><input type="submit" name="' . $ex_key . '" ';
            $buttons .= 'value="' . $export['label'] . '" class="btn btn-default margin-bottom">';
            $buttons .= '<input type="hidden" value="admin" name="r">';
            $buttons .= '<input type="hidden" value="' . $app['s_id'] . '" name="u">';
            $buttons .= '</form>';
        }

        $app['heading']->add('Export');
        $app['heading']->fa('download');

        if ($exec_en)
        {
            $out = '<div class="panel panel-info">';
            $out .= '<div class="panel-heading">';
            $out .= '<h3>eLAS database download (SQL)';
            $out .= '</h3>';
            $out .= '</div>';
            $out .= '<div class="panel-heading">';

            $out .= '<form>';
            $out .= '<input type="submit" value="Download" name="db_elas" class="btn btn-default margin-bottom">';
            $out .= '<input type="hidden" value="admin" name="r">';
            $out .= '<input type="hidden" value="';
            $out .= $app['s_id'];
            $out .= '" name="u">';
            $out .= '</form>';

            $out .= '</div></div>';

            $out .= '<div class="panel panel-info">';
            $out .= '<div class="panel-heading">';
            $out .= '<h3>eLAND extra data (CSV)';
            $out .= '</h3>';
            $out .= '</div>';
            $out .= '<div class="panel-heading">';
            $out .= '<p>';
            $out .= 'Naast de eLAS database bevat eLAND nog ';
            $out .= 'deze extra data die je hier kan downloaden ';
            $out .= 'als csv-file. ';
            $out .= '"Data" bevat de huidige staat en "Events" de ';
            $out .= 'gebeurtenissen die de huidige staat veroorzaakt hebben.';
            $out .= '</p>';
            $out .= '</div>';
            $out .= '<div class="panel-heading">';

            $out .= '<form>';
            $out .= '<input type="submit" value="Download Data" ';
            $out .= 'name="db_eland_aggs" ';
            $out .= 'class="btn btn-default margin-bottom">';
            $out .= '&nbsp;';
            $out .= '<input type="submit" value="Download Events" ';
            $out .= 'name="db_eland_events" ';
            $out .= 'class="btn btn-default margin-bottom">';
            $out .= '<input type="hidden" value="admin" name="r">';
            $out .= '<input type="hidden" value="';
            $out .= $app['s_id'];
            $out .= '" name="u">';
            $out .= '</form>';

            $out .= '</div></div>';
        }

        $out .= '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';
        $out .= '<h3>eLAS Csv export</h3>';
        $out .= '</div>';
        $out .= '<div class="panel-heading">';

        $out .= $buttons;

        $out .= '</div></div>';

        $app['menu']->set('export');

        return $app->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $app['tschema'],
        ]);
    }
}
