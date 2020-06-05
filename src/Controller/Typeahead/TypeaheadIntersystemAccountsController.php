<?php declare(strict_types=1);

namespace App\Controller\Typeahead;

use App\Service\IntersystemsService;
use App\Service\PageParamsService;
use App\Service\TypeaheadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;

class TypeaheadIntersystemAccountsController extends AbstractController
{
    public function __invoke(
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

        $params = [
            'remote_schema' => $remote_schema,
        ];

        $cached = $typeahead_service->get_cached_data($thumbprint, $params);

        if (isset($cached) && $cached)
        {
            return new Response($cached, 200, ['Content-Type' => 'application/json']);
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

        $typeahead_service->set_thumbprint(
            TypeaheadService::GROUP_ACCOUNTS,
            $thumbprint, $accounts, $params);

        return $this->json($accounts);
    }
}
