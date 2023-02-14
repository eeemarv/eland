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

        $cached = $typeahead_service->get_cached_data($thumbprint, $pp, $params);

        if ($cached !== false)
        {
            return new Response($cached, 200, ['Content-Type' => 'application/json']);
        }

        switch($status)
        {
            case 'extern':
                $status_sql = '= 7';
                break;
            case 'inactive':
                $status_sql = '= 0';
                break;
            case 'ip':
                $status_sql = '= 5';
                break;
            case 'im':
                $status_sql = '= 6';
                break;
            case 'active':
                $status_sql = 'in (1, 2)';
                break;
            default:
                return $this->json([
                    'error' => 'Non existing or allowed status code.',
                ], 404);
                break;
        }

        $fetched_users = $db->fetchAllAssociative(
            'select code as c,
                name as n,
                extract(epoch from adate) as a,
                status as s
            from ' . $pp->schema() . '.users
            where status ' . $status_sql . '
            order by id asc', [], []
        );

        $accounts = [];

        foreach ($fetched_users as $account)
        {
            $accounts[] = $account;
        }

        $data = json_encode($accounts);
        $typeahead_service->set_thumbprint($thumbprint, $data, $pp, $params);
        return new Response($data, 200, ['Content-Type' => 'application/json']);
    }
}
