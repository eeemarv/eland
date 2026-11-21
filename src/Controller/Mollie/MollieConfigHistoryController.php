<?php declare(strict_types=1);

namespace App\Controller\Mollie;

use App\Repository\SecretRepository;
use App\Routing\AccessLevel;
use App\Routing\SchemaRoute;
use App\Routing\UsersSubModule;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Service\PageParamsService;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class MollieConfigHistoryController extends AbstractController
{
  #[SchemaRoute(
    access_level: AccessLevel::Admin,
    module: UsersSubModule::Mollie,
    path: '/mollie/config/history',
    name: 'mollie_config_history',
    methods: ['GET'],
  )]

  public function __invoke(
    SecretRepository $secret_repository,
    PageParamsService $pp,
  ):Response
  {
    $history_ary = $secret_repository->get_mollie_config_history(
      schema: $pp->schema_o(),
    );

    return $this->render('mollie/mollie_config_history.html.twig', [
      'history_ary' => $history_ary,
    ]);
  }
}
