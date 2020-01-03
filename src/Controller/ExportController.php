<?php declare(strict_types=1);

namespace App\Controller;

use App\Render\HeadingRender;
use App\Service\MenuService;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;

class ExportController extends AbstractController
{
    public function __invoke(
        Request $request,
        Db $db,
        LoggerInterface $logger,
        HeadingRender $heading_render,
        PageParamsService $pp,
        MenuService $menu_service,
        string $cache_dir,
        string $env_database_url
    ):Response
    {
        $table_ary = [];

        set_time_limit(300);

        exec('echo "Throw exception when php exec() function is not available" > /dev/null');

        $download_sql = $request->query->has('_sql');
        $download_ag_csv = $request->query->has('_ag_csv');
        $download_ev_csv = $request->query->has('_ev_csv');

        $stmt = $db->prepare('select table_name from information_schema.tables
            where table_schema = ?
            order by table_name asc');
        $stmt->bindValue(1, $pp->schema());
        $stmt->execute();

        while($table_name = $stmt->fetchColumn(0))
        {
            $table_ary[] = $table_name;

            if ($request->query->has($table_name))
            {
                $download_table_csv = $table_name;
            }
        }

        $download_en = $download_sql || $download_ag_csv || $download_ev_csv || isset($download_table_csv);

        if ($download_en)
        {
            $send_file = true;

            $download_id = $download_sql ? 'db' : '';
            $download_id = $download_ag_csv ? 'extra-data' : $download_id;
            $download_id = $download_ev_csv ? 'extra-events' : $download_id;
            $download_id = isset($download_table_csv) ? $download_table_csv : $download_id;
            $download_ext = $download_sql ? 'sql' : 'csv';

            $filename = $pp->schema() . '-';
            $filename .= $download_id;
            $filename .= gmdate('-Y-m-d-H-i-s-');
            $filename .= substr(sha1(microtime()), 0, 4);
            $filename .= '.';
            $filename .= $download_ext;

            $file_path = $cache_dir . '/' . $filename;

            if ($download_sql)
            {
                $exec = 'pg_dump -d ';
                $exec .= $env_database_url;
                $exec .= ' -n ' . $pp->schema();
                $exec .= ' -O -x > ' . $file_path;
            }
            else if ($download_ag_csv || $download_ev_csv)
            {
                $exec = 'psql -d ' . $env_database_url . ' -c "';
                $exec .= '\\copy (select * ';
                $exec .= 'from xdb.';
                $exec .= $download_ag_csv ? 'aggs ' : 'events ';
                $exec .= 'where agg_schema = \'';
                $exec .= $pp->schema() . '\') ';
                $exec .= 'to \'' . $file_path . '\' ';
                $exec .= 'delimiter \',\' ';
                $exec .= 'csv header;"';
            }
            else if (isset($download_table_csv))
            {
                $exec = 'psql -d ' . $env_database_url . ' -c "';
                $exec .= '\\copy ' . $pp->schema() . '.' . $download_table_csv . ' to \'';
                $exec .= $file_path . '\' delimiter \',\' csv header;"';
            }
            else
            {
                $exec .= 'echo "Interne fout" > ' . $file_path;
            }

            exec($exec);

            if ($send_file)
            {
                $handle = fopen($file_path, 'rb');

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

                unlink($file_path);

                $logger->info($filename . ' downloaded',
                    ['schema' => $pp->schema()]);

                $response = new Response($out);

                $response->headers->set('Content-Type', 'application/octet-stream');
                $response->headers->set('Content-Disposition', 'attachment; filename=' . $filename);
                $response->headers->set('Content-Transfer-Encoding', 'binary');

                return $response;
            }
        }

        $heading_render->add('Export');
        $heading_render->fa('download');

        $out = '<form>';

        $out .= '<div class="card bg-info">';
        $out .= '<div class="card-body">';
        $out .= '<h3>Database download (SQL)';
        $out .= '</h3>';
        $out .= '</div>';
        $out .= '<div class="card-body">';
        $out .= '<input type="submit" value="Download" name="_sql" ';
        $out .= 'class="btn btn-default btn-lg">';
        $out .= '</div></div>';

        $out .= '<div class="card bg-info">';
        $out .= '<div class="card-body">';
        $out .= '<h3>eLAND extra data (CSV)';
        $out .= '</h3>';
        $out .= '</div>';
        $out .= '<div class="card-body">';
        $out .= '<p>';
        $out .= 'Naast de database bevat eLAND nog ';
        $out .= 'deze extra data die je hier kan downloaden ';
        $out .= 'als csv-file. ';
        $out .= '"Data" bevat de huidige staat en "Events" de ';
        $out .= 'gebeurtenissen die de huidige staat veroorzaakt hebben.';
        $out .= '</p>';
        $out .= '</div>';
        $out .= '<div class="card-body">';

        $out .= '<input type="submit" value="Data" ';
        $out .= 'name="_ag_csv" ';
        $out .= 'class="btn btn-default btn-lg">';
        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Events" ';
        $out .= 'name="_ev_csv" ';
        $out .= 'class="btn btn-default btn-lg">';

        $out .= '</div></div>';

        $out .= '<div class="card bg-info">';
        $out .= '<div class="card-body">';
        $out .= '<h3>CSV export</h3>';
        $out .= '<p>Per database tabel</p>';
        $out .= '</div>';
        $out .= '<div class="card-body">';

        foreach ($table_ary as $table)
        {
            $out .= '<input type="submit" value="';
            $out .= $table;
            $out .= '" name="';
            $out .= $table;
            $out .= '" class="btn btn-default btn-lg">&nbsp;';
        }

        $out .= '</div></div>';
        $out .= '</form>';

        $menu_service->set('export');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
