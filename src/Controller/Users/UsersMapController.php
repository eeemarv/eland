<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Repository\UserRepository;
use App\Service\CacheService;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsController]
class UsersMapController extends AbstractController
{
  #[Route(
    '/{system}/{role_short}/users/map/{status}',
    name: 'users_map',
    methods: ['GET'],
    priority: 20,
    requirements: [
      'status'        => '%assert.account_status%',
      'system'        => '%assert.system%',
      'role_short'    => '%assert.role_short.guest%',
    ],
    defaults: [
      'status'        => 'active',
      'module'        => 'users',
    ],
  )]

  public function __invoke(
    string $status,
    UserRepository $user_repository,
    CacheService $cache_service,
    ItemAccessService $item_access_service,
    PageParamsService $pp,
    SessionUserService $su,
    UrlGeneratorInterface $url_generator,
    string $env_map_access_token,
    string $env_map_tiles_url,
  ):Response
  {
    if (!$pp->is_admin()
      && !in_array($status, ['active', 'new', 'leaving']))
    {
      throw new AccessDeniedHttpException('No access for this user status');
    }

    $ref_geo = [];

    $users = $user_repository->get_all_active_with_addresses(
      schema: $pp->schema_o(),
    );

    $data_users = [];
    $no_address_ary = [];
    $not_geocoded_ary = [];
    $hidden_ary = [];
    $lat_ary = [];
    $lng_ary = [];

    foreach ($users as $user_id => $adr_ary)
    {
      foreach ($adr_ary as $d)
      {
        $user_str = $d['code'] ?? '***';
        $user_str .= ' ';
        $user_str .= $d['name'] ?? '***';
        $user_id = $d['user_id'];
        if (!isset($d['value']))
        {
          $no_address_ary[$user_id] = $user_str;
          continue;
        }
        if (!$item_access_service->is_visible($d['access']))
        {
          $hidden_ary[$user_id] = $user_str;
          continue;
        }
        $geo = $cache_service->get('geo_' . $d['value']);
        if (!$geo)
        {
          $not_geocoded_ary[$user_id] = $user_str;
          continue;
        }
        if ($user_id === $su->id())
        {
          $ref_geo = $geo;
        }
        $lat_ary[] = $geo['lat'];
        $lng_ary[] = $geo['lng'];
        $link =  $url_generator->generate(
          'users_show', [
            ...$pp->ary(),
            'id'  => $user_id,
          ],
          UrlGeneratorInterface::ABSOLUTE_URL);
        $data_users[$user_id] = [
          'link'  => $link,
          'code'  => $d['code'],
          'name'  => $d['name'],
          ...$geo,
        ];
      }
    }

    if (!isset($ref_geo) && count($lat_ary))
    {
      sort($lat_ary);
      sort($lng_ary);
      $g_count = count($lat_ary);
      $lat_acc_ary = [];
      $lng_acc_ary = [];
      $lower_limit = round($g_count / 4);
      $upper_limit = $g_count - $lower_limit;
      foreach ($lat_ary as $i => $lat)
      {
        if ($i < $lower_limit || $i > $upper_limit)
        {
          continue;
        }
        $lat_acc_ary[] = $lat;
        $lng_acc_ary[] = $lng_ary[$i];
      }
      if (count($lat_acc_ary))
      {
        $ref_geo = [
          'lat' => array_sum($lat_acc_ary) / count($lat_acc_ary),
          'lng' => array_sum($lng_acc_ary) / count($lng_acc_ary),
        ];
      }
    }

    $data_map = [
      'users'     => $data_users,
      'lat'       => $ref_geo['lat'] ?? '',
      'lng'       => $ref_geo['lng'] ?? '',
      'token'     => $env_map_access_token,
      'tiles_url' => $env_map_tiles_url,
    ];

    return $this->render('users/users_map.html.twig', [
      'data_map'  => $data_map,
      'no_address_ary'  => $no_address_ary,
      'hidden_ary'      => $hidden_ary,
      'not_geocoded_ary'  => $not_geocoded_ary,
      'users'     => $users,
    ]);
  }
}
