<?php declare(strict_types=1);

namespace App\Controller\Typeahead;

use App\Service\PageParamsService;
use App\Service\TypeaheadService;
use Doctrine\DBAL\ArrayParameterType;
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
            'status'        => '%assert.account_status.primary%',
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

        if(!$pp->is_admin() && !in_array($status, ['active', 'extern']))
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

        $status_ary = [];

        switch($status)
        {
            case 'extern':
                $status_ary[] = 7;
                break;
            case 'inactive':
                $status_ary[] = 0;
                break;
            case 'ip':
                $status_ary[] = 5;
                break;
            case 'im':
                $status_ary[] = 6;
                break;
            case 'active':
                $status_ary[] = 1;
                $status_ary[] = 2;
                break;
            default:
                return $this->json([
                    'error' => 'Non existing or allowed status code.',
                ], 404);
                break;
        }

        $res = $db->executeQuery(
            'select id,
                code as c,
                name as n,
                extract(epoch from adate)::int as a,
                status as s,
                remote_schema,
                remote_email
            from ' . $pp->schema() . '.users
            where status in (?)
            order by id asc',
            [$status_ary],
            [ArrayParameterType::INTEGER]
        );

        $accounts = [];

        while ($row = $res->fetchAssociative())
        {
            if (!isset($row['remote_schema']))
            {
                unset($row['remote_schema']);
            }

            if (!isset($row['remote_email']))
            {
                unset($row['remote_email']);
            }

            $accounts[] = $row;
        }

        $response_body = json_encode($accounts);
        $typeahead_service->store_response_body($response_body, $pp, $params);
        return new Response($response_body, 200, ['Content-Type' => 'application/json']);
    }
}
