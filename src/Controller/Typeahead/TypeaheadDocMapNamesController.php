<?php declare(strict_types=1);

namespace App\Controller\Typeahead;

use App\Service\PageParamsService;
use App\Service\TypeaheadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\Routing\Annotation\Route;

class TypeaheadDocMapNamesController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/typeahead-doc-map-names/{thumbprint}',
        name: 'typeahead_doc_map_names',
        methods: ['GET'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
            'thumbprint'    => '%assert.thumbprint%',
        ],
        defaults: [
            'module'        => 'docs',
        ],
    )]

    public function __invoke(
        string $thumbprint,
        Db $db,
        TypeaheadService $typeahead_service,
        PageParamsService $pp
    ):Response
    {
        $map_names = [];

        $stmt = $db->prepare('select name
            from ' . $pp->schema() . '.doc_maps
            order by name asc');
        $stmt->execute();

        while ($name = $stmt->fetchOne())
        {
            $map_names[] = $name;
        }

        $crc = (string) crc32(json_encode($map_names));

        $typeahead_service->set_thumbprint('doc_map_names', $pp->ary(), [], $crc);

        return $this->json($map_names);
    }
}
