<?php declare(strict_types=1);

namespace App\Controller\Typeahead;

use App\Service\PageParamsService;
use App\Service\TypeaheadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\Routing\Annotation\Route;

class TypeaheadLogTypesController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/typeahead-log-types',
        name: 'typeahead_log_types',
        methods: ['GET'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'module'        => 'logs',
        ],
    )]

    public function __invoke(
        Db $db,
        TypeaheadService $typeahead_service,
        PageParamsService $pp
    ):Response
    {
        $log_types = [];

        $st = $db->prepare('select distinct type
            from xdb.logs
            where schema = ?
            order by type asc');

        $st->bindValue(1, $pp->schema());

        $st->execute();

        while ($row = $st->fetch())
        {
            $log_types[] = $row['type'];
        }

        $crc = (string) crc32(json_encode($log_types));

        $typeahead_service->set_thumbprint('log_types', $pp->ary(), [], $crc);

        return $this->json($log_types);
    }
}
