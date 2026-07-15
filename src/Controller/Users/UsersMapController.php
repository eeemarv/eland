<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Repository\UserRepository;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsController]
class UsersMapController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/users/map/{status}',
    name: 'users_map',
    methods: ['GET'],
    priority: 20,
    requirements: [
      'status'        => '%assert.account.status2%',
      'schema'        => '%assert.schema%',
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
    ItemAccessService $item_access_service,
    PageParamsService $pp,
    SessionUserService $su,
    UrlGeneratorInterface $url_generator,
  ):Response
  {
    if (!$pp->is_admin()
      && !in_array($status, ['active', 'new', 'leaving']))
    {
      throw $this->createAccessDeniedException('No access for this user status');
    }

    $map_markers = [];

    $users_adr_ary = $user_repository->get_all_active_with_addresses(
      current_user_id: $su->id(),
      current_user_schema: $su->schema_o(),
      schema: $pp->schema_o(),
    );

    error_log(' **** $users_adr_ary **** ');
    error_log(json_encode($users_adr_ary));

    $users = [];
    $no_address_ary = [];
    $not_geocoded_ary = [];
    $hidden_ary = [];

    foreach ($users_adr_ary as $user_id => $u_ary)
    {
      $username = $u_ary[0]['code'] ?? '***';
      $username .= ' ';
      $username .= $u_ary[0]['name'] ?? '***';

      $users[$user_id] = [
        ...$u_ary,
        'username'  => $username,
      ];

      foreach ($u_ary as $u)
      {
        if (!isset($u['address']))
        {
          $no_address_ary[] = $user_id;
          continue;
        }

        if (!$item_access_service->is_visible($u['access']))
        {
          if (!isset($hidden_ary[$user_id]))
          {
            $hidden_ary[$user_id] = 0;
          }
          $hidden_ary[$user_id]++;
          continue;
        }

        if (!isset($u['latitude']) || !isset($u['longitude']))
        {
          if (!isset($not_geocoded_ary[$user_id]))
          {
            $not_geocoded_ary[$user_id] = 0;
          }
          $not_geocoded_ary[$user_id]++;
          continue;
        }

        $map_markers[] = [
          'lat' => $u['latitude'],
          'lng' => $u['longitude'],
          'distance'  => $u['distance'],
          'address'  => $u['address'],
          'username'  => $username,
          'url'  => $url_generator->generate(
            'users_show', [
              ...$pp->ary(),
              'id'  => $user_id,
            ],
            UrlGeneratorInterface::ABSOLUTE_URL),
        ];
      }
    }

    return $this->render('users/users_map.html.twig', [
      'no_address_ary'  => $no_address_ary,
      'hidden_ary'      => $hidden_ary,
      'not_geocoded_ary'  => $not_geocoded_ary,
      'users'     => $users,
      'no_address_count'  => count($no_address_ary),
      'hidden_count'      => array_sum($hidden_ary),
      'not_geocoded_count'=> array_sum($not_geocoded_ary),
      'map_markers'  => $map_markers,
    ]);
  }
}
