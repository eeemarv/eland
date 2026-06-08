<?php declare(strict_types=1);

namespace App\Controller\Mollie;

use App\Repository\SecretRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class MollieConfigHistoryController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/mollie/config/history',
    name: 'mollie_config_history',
    methods: ['GET', 'POST'],
    requirements: [
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.admin%',
    ],
    defaults: [
      'module'        => 'users',
      'sub_module'    => 'mollie',
    ],
  )]

  public function __invoke(
    ConfigService $config_service,
    SecretRepository $secret_repository,
    PageParamsService $pp,
  ):Response
  {
    if (!$config_service->get_bool(
      config_id: 'mollie.enabled',
      schema: $pp->schema_o(),
    ))
    {
      throw $this->createNotFoundException(
        'Mollie submodule (users) not enabled.'
      );
    }

    $history_ary = $secret_repository->get_mollie_config_history(
      schema: $pp->schema_o(),
    );

    return $this->render('mollie/mollie_config_history.html.twig', [
      'history_ary' => $history_ary,
    ]);
  }
}
