<?php declare(strict_types=1);

namespace App\Controller\Autocomplete;

use App\Repository\LogRepository;
use App\Routing\AccessLevel;
use App\Routing\AppModule;
use App\Routing\SchemaRoute;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class AutocompleteLogTypesController extends AbstractController
{
  use EtagJsonResponseTrait;

  #[SchemaRoute(
    access_level: AccessLevel::Admin,
    module: AppModule::Logs,
    path: '/autocomplete/log-types',
    name: 'autocomplete_log_types',
    methods: ['GET'],
  )]

  public function __invoke(
    Request $request,
    LogRepository $log_repository,
    PageParamsService $pp,
  ):Response
  {
    $log_types = $log_repository->get_types(
      schema: $pp->schema_o(),
    );

    return $this->etagJsonResponse($request, $log_types);
  }
}
