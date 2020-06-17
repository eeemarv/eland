<?php declare(strict_types=1);

namespace App\Controller\Typeahead;

use App\Service\PageParamsService;
use App\Service\TypeaheadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;

class TypeaheadAccountCodesController extends AbstractController
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

        $account_codes = [];

        $st = $db->prepare('select code
            from ' . $pp->schema() . '.users
            order by code asc');

        $st->execute();

        while ($row = $st->fetch())
        {
            if (empty($row['code']))
            {
                continue;
            }

            $account_codes[] = $row['code'];
        }

        $typeahead_service->set_thumbprint(
            TypeaheadService::GROUP_ACCOUNTS,
            $thumbprint, $account_codes, []);

        return $this->json($account_codes);
    }
}
