<?php declare(strict_types=1);

namespace App\Controller\Export;

use App\Repository\SchemaRepository;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Annotation\Route;

class ExportController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/export',
        name: 'export',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'module'        => 'export',
        ],
    )]

    public function __invoke(
        Request $request,
        SchemaRepository $schema_repository,
        LoggerInterface $logger,
        PageParamsService $pp,
        string $cache_dir,
        string $env_database_url
    ):Response
    {
        set_time_limit(300);
        exec('echo "Throw exception when php exec() function is not available" > /dev/null');

        $table_ary = $schema_repository->get_tables($pp->schema());
        $builder = $this->createFormBuilder(null, [
            'form_token_prevent_double' => false,
        ]);

        $builder->add('sql', SubmitType::class);

        foreach($table_ary as $table)
        {
            $builder->add('csv__' . $table, SubmitType::class);
        }

        $builder->add('csv_zip', SubmitType::class);
        $download_form = $builder->getForm();
        $download_form->handleRequest($request);

        if ($download_form->isSubmitted()
            && $download_form->isValid())
        {
            $btn = $download_form->getClickedButton()->getName();

            $download_sql = $btn === 'sql';
            $download_zip_csv = $btn === 'csv_zip';

            if (str_starts_with($btn, 'csv__'))
            {
                $download_table_csv = strtr($btn, ['csv__' => '']);
            }

            $download_id = $download_sql ? 'db' : '';
            $download_id = $download_zip_csv ? 'db-csv' : '';
            $download_id = isset($download_table_csv) ? $download_table_csv : $download_id;
            $download_ext = $download_sql ? 'sql' : 'csv';
            $download_ext = $download_zip_csv ? 'zip' : $download_ext;

            $filename = $pp->schema() . '-';
            $filename .= $download_id;
            $filename .= gmdate('-Y-m-d-H-i-s-');
            $filename .= substr(sha1(random_bytes(4)), 0, 4);
            $filename .= '.';
            $filename .= $download_ext;

            $file_path = $cache_dir . '/' . $filename;

            if ($download_sql)
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
                    $error_message = 'ZIP could not be created';
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
                $error_message = 'Internal error';
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

        return $this->render('export/export.html.twig', [
            'download_form' => $download_form->createView(),
        ]);
    }
}
