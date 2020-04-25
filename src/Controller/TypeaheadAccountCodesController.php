<?php declare(strict_types=1);

namespace App\Controller;

use App\Service\PageParamsService;
use App\Service\TypeaheadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;

class TypeaheadAccountCodesController extends AbstractController
{
    public function __invoke(
        Db $db,
        TypeaheadService $typeahead_service,
        PageParamsService $pp
    ):Response
    {
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

        $crc = (string) crc32(json_encode($account_codes));

        $typeahead_service->set_thumbprint('account_codes', $pp->ary(), [], $crc);

        return $this->json($account_codes);
    }
}
