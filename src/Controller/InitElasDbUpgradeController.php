<?php declare(strict_types=1);

namespace App\Controller;

use App\Render\LinkRender;
use App\Service\ElasDbUpgradeService;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;

class InitElasDbUpgradeController extends AbstractController
{
    public function __invoke(
        Request $request,
        Db $db,
        PageParamsService $pp,
        ElasDbUpgradeService $elas_db_upgrade_service,
        LoggerInterface $logger,
        LinkRender $link_render
    ):Response
    {
        set_time_limit(300);

        $schemaversion = 31000;

        $currentversion = $dbversion = $db->fetchColumn('select value
            from ' . $pp->schema() . '.parameters
            where parameter = \'schemaversion\'');

        if ($currentversion >= $schemaversion)
        {
            error_log('-- Database already up to date -- ');
        }
        else
        {
            error_log(' -- eLAS/eLAND database needs to
                upgrade from ' . $currentversion .
                ' to ' . $schemaversion . ' -- ');

            while($currentversion < $schemaversion)
            {
                $currentversion++;

                $elas_db_upgrade_service->run($currentversion, $pp->schema());
            }

            $m = 'Upgraded database from schema version ' .
                $dbversion . ' to ' . $currentversion;

            error_log(' -- ' . $m . ' -- ');
            $logger->info('DB: ' . $m, ['schema' => $pp->schema()]);
        }

        $link_render->redirect('init', $pp->ary(),
            ['ok' => $request->attributes->get('_route')]);

        return new Response('');
    }
}
