<?php declare(strict_types=1);

namespace App\Controller\Autocomplete;

use App\Repository\LogRepository;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class AutocompleteLogTypesController extends AbstractController
{
  use EtagJsonResponseTrait;

  #[Route(
    '/{schema}/{role_short}/autocomplete/log-types',
    name: 'autocomplete_log_types',
    methods: ['GET'],
    requirements: [
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.admin%',
    ],
    defaults: [
      'module'        => 'logs',
    ],
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
