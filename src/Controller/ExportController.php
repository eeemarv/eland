<?php declare(strict_types=1);

namespace App\Controller;

use App\Render\HeadingRender;
use App\Service\FormTokenService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class ExportController extends AbstractController
{
    public function __invoke(
        Request $request,
        Db $db,
        LoggerInterface $logger,
        HeadingRender $heading_render,
        PageParamsService $pp,
        MenuService $menu_service,
        FormTokenService $form_token_service,
        string $cache_dir,
        string $env_database_url
    ):Response
    {
        $table_ary = [];

        set_time_limit(300);

        exec('echo "Throw exception when php exec() function is not available" > /dev/null');

        $download_sql = $request->request->has('_sql');
        $download_zip_csv = $request->request->has('_zip_csv');

        $stmt = $db->prepare('select table_name from information_schema.tables
            where table_schema = ?
            order by table_name asc');
        $stmt->bindValue(1, $pp->schema());
        $stmt->execute();

        while($table_name = $stmt->fetchColumn(0))
        {
            $table_ary[] = $table_name;

            if ($request->request->has($table_name))
            {
                $download_table_csv = $table_name;
            }
        }

        $download_en = $download_sql || isset($download_table_csv) || $download_zip_csv;

        if ($download_en)
        {
            $download_id = $download_sql ? 'db' : '';
            $download_id = $download_zip_csv ? 'db-csv' : '';
            $download_id = isset($download_table_csv) ? $download_table_csv : $download_id;
            $download_ext = $download_sql ? 'sql' : 'csv';
            $download_ext = $download_zip_csv ? 'zip' : $download_ext;

            $filename = $pp->schema() . '-';
            $filename .= $download_id;
            $filename .= gmdate('-Y-m-d-H-i-s-');
            $filename .= substr(sha1(microtime()), 0, 4);
            $filename .= '.';
            $filename .= $download_ext;

            $file_path = $cache_dir . '/' . $filename;

            if ($token_error = $form_token_service->get_error(false))
            {
                $error_message = 'Form token error: ' . $token_error;
            }
            else if ($download_sql)
            {
                $process_ary = [
                    'pg_dump',
                    '-d',
                    $env_database_url,
                    '-n',
                    $pp->schema(),
                    '-O',
                    '-x',
                    '-f',
                    $file_path,
                ];
                $process = new Process($process_ary);
                $process->run();
                if (!$process->isSuccessful())
                {
                    throw new ProcessFailedException($process);
                }
                error_log($process->getOutput());
            }
            else if ($download_zip_csv)
            {
                $unlink_ary = [];
                $zip = new \ZipArchive();

                if ($zip->open($file_path, \ZipArchive::CREATE))
                {
                    foreach($table_ary as $table)
                    {
                        $tmp_file = $cache_dir . '/' . 'tmp_csv_' . hash('crc32b', random_bytes(4)) . '.csv';
                        $local_filename = $table . '.csv';
                        $copy_cmd = '\\copy ' . $pp->schema() . '.' . $table . ' to \'';
                        $copy_cmd .= $tmp_file . '\' delimiter \',\' csv header;';
                        $process_ary = [
                            'psql',
                            '-d',
                            $env_database_url,
                            '-c',
                            $copy_cmd,
                        ];
                        $process = new Process($process_ary);
                        $process->run();
                        if (!$process->isSuccessful())
                        {
                            throw new ProcessFailedException($process);
                        }
                        error_log($process->getOutput());
                        $zip->addFile($tmp_file, $local_filename);
                        $unlink_ary[] = $tmp_file;
                    }

                    $zip->close();

                    foreach($unlink_ary as $unlink)
                    {
                        unlink($unlink);
                    }
                }
                else
                {
                    $error_message = 'ZIP kon niet gecreëerd worden';
                }
            }
            else if (isset($download_table_csv))
            {
                $copy_cmd = '\\copy ' . $pp->schema() . '.' . $download_table_csv . ' to \'';
                $copy_cmd .= $file_path . '\' delimiter \',\' csv header;';
                $process_ary = [
                    'psql',
                    '-d',
                    $env_database_url,
                    '-c',
                    $copy_cmd
                ];
                $process = new Process($process_ary);
                $process->run();
                if (!$process->isSuccessful())
                {
                    throw new ProcessFailedException($process);
                }
                error_log($process->getOutput());
            }
            else
            {
                $error_message = 'Interne fout';
            }

            if (isset($error_message))
            {
                throw new HttpException(500, $error_message);
            }

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

        $heading_render->add('Export');
        $heading_render->fa('download');

        $out = '<div class="card fcard fcard-info">';
        $out .= '<div class="card-body">';

        $out = '<form method="post">';

        $out .= '<h3>Database download (SQL)';
        $out .= '</h3>';
        $out .= '</div>';
        $out .= '<div class="card-body">';
        $out .= '<input type="submit" value="Download" name="_sql" ';
        $out .= 'class="btn btn-default btn-lg">';
        $out .= '</div></div>';

        $out .= '<div class="card fcard fcard-info">';
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

        $out .= '</div>';
        $out .= '<div class="card-body">';
        $out .= '<input type="submit" value="ZIP van alle tabellen als CSV" ';
        $out .= 'name="_zip_csv" class="btn btn-default btn-lg margin-bottom">';
        $out .= '<p><i>De creatie van het ZIP-bestand kan wat tijd in beslag nemen.</i></p>';
        $out .= '</div></div>';
        $out .= $form_token_service->get_hidden_input();
        $out .= '</form>';
        $out .= '</div></div>';

        $menu_service->set('export');

        return $this->render('export/export.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
