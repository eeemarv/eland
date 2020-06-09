<?php declare(strict_types=1);

namespace App\Controller\Typeahead;

use App\Service\PageParamsService;
use App\Service\TypeaheadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;

class TypeaheadDocMapNamesController extends AbstractController
{
    public function __invoke(
        string $thumbprint,
        Db $db,
        TypeaheadService $typeahead_service,
        PageParamsService $pp
    ):Response
    {
        $cached = $typeahead_service->get_cached_data($thumbprint, []);

        if (isset($cached) && $cached)
        {
            return new Response($cached, 200, ['Content-Type' => 'application/json']);
        }

        $map_names = [];

        $st = $db->executeQuery('select name
            from ' . $pp->schema() . '.doc_maps
            order by name asc');

        while ($name = $st->fetchColumn())
        {
            $map_names[] = $name;
        }

        $typeahead_service->set_thumbprint(
            TypeaheadService::GROUP_DOC_MAP_NAMES,
            $thumbprint, $map_names, []);

        return $this->json($map_names);
    }
}
