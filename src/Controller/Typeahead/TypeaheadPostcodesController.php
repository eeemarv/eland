<?php declare(strict_types=1);

namespace App\Controller\Typeahead;

use App\Service\PageParamsService;
use App\Service\TypeaheadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;

class TypeaheadPostcodesController extends AbstractController
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

        $postcodes = [];

        $st = $db->prepare('select distinct postcode
            from ' . $pp->schema() . '.users
            order by postcode asc');

        $st->execute();

        while ($row = $st->fetch())
        {
            if (empty($row['postcode']))
            {
                continue;
            }

            $postcodes[] = $row['postcode'];
        }

        $typeahead_service->set_thumbprint(
            TypeaheadService::GROUP_USERS,
            $thumbprint, $postcodes, []);

        return $this->json($postcodes);
    }
}
