<?php declare(strict_types=1);

namespace App\Controller\Typeahead;

use App\Service\IntersystemsService;
use App\Service\PageParamsService;
use App\Service\TypeaheadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

class TypeaheadIntersystemAccountsController extends AbstractController
{
    public function __invoke(
        Request $request,
        string $thumbprint,
        string $remote_schema,
        Db $db,
        LoggerInterface $logger,
        IntersystemsService $intersystems_service,
        TypeaheadService $typeahead_service,
        PageParamsService $pp
    ):Response
    {
        $eland_intersystems = $intersystems_service->get_eland($pp->schema());

        if (!isset($eland_intersystems[$remote_schema]))
        {
            $logger->debug('typeahead/intersystem_accounts: ' .
                $remote_schema . ' not valid',
                ['schema' => $pp->schema()]);

            return $this->json([], 404);
        }

        $fetched_users = $db->fetchAll(
            'select code as c,
                name as n,
                extract(epoch from adate) as a,
                status as s
            from ' . $remote_schema . '.users
            where status in (1, 2)
            order by id asc'
        );

        $accounts = [];

        foreach ($fetched_users as $account)
        {
            if ($account['s'] == 1)
            {
                unset($account['s']);
            }

            $accounts[] = $account;
        }

        $params = [
            'remote_schema' => $remote_schema,
        ];

        $crc = (string) crc32(json_encode($accounts));

        $typeahead_service->calc_thumbprint('accounts', $thumbprint, $accounts, null, $remote_shema);

        $typeahead_service->set_thumbprint(
            'intersystem_accounts', $pp->ary(), $params, $crc);

        return $this->json($accounts);
    }
}
