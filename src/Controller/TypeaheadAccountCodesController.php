<?php declare(strict_types=1);

namespace App\Controller;

use App\Service\TypeaheadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;

class TypeaheadAccountCodesController extends AbstractController
{
    public function typeahead_account_codes(
        Db $db,
        TypeaheadService $typeahead_service
    ):Response
    {
        $account_codes = [];

        $st = $db->prepare('select letscode
            from ' . $pp->schema() . '.users
            order by letscode asc');

        $st->execute();

        while ($row = $st->fetch())
        {
            if (empty($row['letscode']))
            {
                continue;
            }

            $account_codes[] = $row['letscode'];
        }

        $crc = (string) crc32(json_encode($account_codes));

        $typeahead_service->set_thumbprint('account_codes', $pp->ary(), [], $crc);

        return $this->json($account_codes);
    }
}
