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
        '/{system}/{role_short}/typeahead-log-types/{thumbprint}',
        name: 'typeahead_log_types',
        methods: ['GET'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
            'thumbprint'    => '%assert.thumbprint%',
        ],
        defaults: [
            'module'        => 'logs',
        ],
    )]

    public function __invoke(
        string $thumbprint,
        Db $db,
        TypeaheadService $typeahead_service,
        PageParamsService $pp
    ):Response
    {
        $cached = $typeahead_service->get_cached_data($thumbprint, $pp, []);

        if ($cached !== false)
        {
            return new Response($cached, 200, ['Content-Type' => 'application/json']);
        }

        $log_types = [];

        $stmt = $db->prepare('select distinct type
            from xdb.logs
            where schema = ?
            order by type asc');

        $stmt->bindValue(1, $pp->schema());

        $res = $stmt->executeQuery();

        while ($row = $res->fetchAssociative())
        {
            $log_types[] = $row['type'];
        }

        $data = json_encode($log_types);
        $typeahead_service->set_thumbprint($thumbprint, $data, $pp, []);
        return new Response($data, 200, ['Content-Type' => 'application/json']);
    }
}
