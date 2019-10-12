<?php declare(strict_types=1);

namespace App\Controller;

use App\Service\CacheService;
use App\Service\PageParamsService;
use App\Service\TypeaheadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;

class TypeaheadElasIntersystemAccountsController extends AbstractController
{
    public function typeahead_elas_intersystem_accounts(
        int $group_id,
        Db $db,
        LoggerInterface $logger,
        CacheService $cache_service,
        TypeaheadService $typeahead_service,
        PageParamsService $pp
    ):Response
    {
        $group = $db->fetchAssoc('select *
            from ' . $pp->schema() . '.letsgroups
            where id = ?', [$group_id]);

        if (!$group || !$group['url'])
        {
            return $this->json([], 404);
        }

        if ($group['apimethod'] != 'elassoap')
        {
            return $this->json([], 404);
        }

        $domain = strtolower(parse_url($group['url'], PHP_URL_HOST));

        $accounts = $cache_service->get($domain . '_typeahead_data');

        if (!$accounts)
        {
            $logger->debug('typeahead/elas_intersystem_accounts: empty for id ' .
                $group_id . ', url: ' . $group['url'],
                ['schema' => $pp->schema()]);

            return $this->json([], 404);
        }

        $params = [
            'group_id'  => $group_id,
        ];

        $crc = (string) crc32(json_encode($accounts));

        $typeahead_service->set_thumbprint(
            'elas_intersystem_accounts', $pp->ary(), $params, $crc);

        return $this->json($accounts);
    }
}
