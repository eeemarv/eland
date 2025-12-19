<?php declare(strict_types=1);

namespace App\Controller\Typeahead;

use App\DTO\Schema;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SystemsService;
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
    '/{schema}/{role_short}/typeahead-accounts/{status}/{thumbprint}',
    name: 'typeahead_accounts',
    methods: ['GET'],
    requirements: [
      'status'        => '%assert.account_status.primary%',
      'schema'        => '%assert.schema%',
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
    SystemsService $systems_service,
    ConfigService $config_service,
    PageParamsService $pp,
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

    $inter_ary = $systems_service->get_inter_ary($pp->schema());

    $where_sql = '1 <> 1';

    switch($status)
    {
      case 'extern':
        $where_sql = 'status = 7';
        break;
      case 'inactive':
        $where_sql = 'status = 0';
        break;
      case 'ip':
        $where_sql = 'status = 5';
        break;
      case 'im':
        $where_sql = 'status = 6';
        break;
      case 'active':
        $where_sql = 'status in (1, 2)';
        break;
      default:
        return $this->json([
          'error' => 'Non existing or allowed status code.',
        ], 404);
        break;
    }

    $fetched_users = $db->fetchAllAssociative(
      'select code as c,
        remote_schema,
        name as n,
        extract(epoch from adate) as a,
        status as s
      from ' . $pp->schema() . '.users
      where ' . $where_sql . '
      order by id asc'
    );

    $accounts = [];

    foreach ($fetched_users as $acc)
    {
      if (isset($acc['remote_schema']))
      {
        if (isset($inter_ary[$acc['remote_schema']]))
        {
          $acc['n'] = $config_service->get_str(
            config_id: 'system.name',
            schema: new Schema($acc['remote_schema']),
          );
        }
        else
        {
          continue;
        }
      }
      unset($acc['remote_schema']);
      $accounts[] = $acc;
    }

    $data = json_encode($accounts);
    $typeahead_service->set_thumbprint($thumbprint, $data, $pp, $params);
    return new Response($data, 200, ['Content-Type' => 'application/json']);
  }
}
