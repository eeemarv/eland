<?php declare(strict_types=1);

namespace App\Controller\Typeahead;

use App\Service\PageParamsService;
use App\Service\TypeaheadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class TypeaheadAccountsController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/typeahead-accounts/{status}/{thumbprint}',
        name: 'typeahead_accounts',
        methods: ['GET'],
        requirements: [
            'status'        => '%assert.account_status.typeahead%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.guest%',
            'thumbprint'    => '%assert.thumbprint%',
        ],
        defaults: [
            'module'        => 'users',
        ],
    )]

    public function __invoke(
        string $status,
        string $thumbprint,
        Db $db,
        TypeaheadService $typeahead_service,
        PageParamsService $pp
    ):Response
    {
        if ($pp->is_guest() && $status !== 'active')
        {
            return $this->json(['error' => 'No access.'], 403);
        }

        if(!$pp->is_admin() && !in_array($status, ['active', 'intersystem']))
        {
            return $this->json(['error' => 'No access.'], 403);
        }

        $params = [
            'status' => $status,
        ];

        $cached = $typeahead_service->get_cached_response_body($thumbprint, $pp, $params);

        if ($cached !== false)
        {
            return new Response($cached, 200, ['Content-Type' => 'application/json']);
        }

        $wh_ary = match($status)
        {
            'intersystem'   => [
                [
                    'u.remote_schema is not null',
                    'u.remote_email is not null'
                ],
                'u.is_active',
            ],
            'active'  => [
                'u.is_active',
                'u.remote_schema is null',
                'u.remote_email is null',
            ],
            'pre-active'    => [
                'not u.is_active',
                'u.activated_at is null',
            ],
            'post-active'    => [
                'not u.is_active',
                'u.activated_at is not null',
            ],
            default => [],
        };

        if (!count($wh_ary))
        {
            return $this->json([
                'error' => 'Non existing or allowed status code.',
            ], 404);
        }

        $sql_where = '';

        foreach ($wh_ary as $wh)
        {
            if ($sql_where !== '')
            {
                $sql_where .= ' and ';
            }

            if (is_array($wh))
            {
                $sql_where .= '(';
                $sql_where .= implode(' or ', $wh);
                $sql_where .= ')';
                continue;
            }

            $sql_where .= $wh;
        }

        $res = $db->executeQuery(
            'select id,
                code as c,
                name as n,
                extract(epoch from activated_at)::int as aa,
                is_active as ia,
                is_leaving as il,
                remote_schema as rs,
                remote_email as re
            from ' . $pp->schema() . '.users u
            where ' . $sql_where . '
            order by id asc');

        $accounts = [];

        while ($row = $res->fetchAssociative())
        {
            if (!isset($row['rs']))
            {
                unset($row['rs']);
            }

            if (!isset($row['re']))
            {
                unset($row['re']);
            }

            $accounts[] = $row;
        }

        $response_body = json_encode($accounts);
        $typeahead_service->store_response_body($response_body, $pp, $params);
        return new Response($response_body, 200, ['Content-Type' => 'application/json']);
    }
}
