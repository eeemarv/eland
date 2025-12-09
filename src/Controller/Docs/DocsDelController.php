<?php declare(strict_types=1);

namespace App\Controller\Docs;

use App\Command\Docs\DocsCommand;
use App\Form\Type\Docs\DocsDelType;
use App\Repository\DocRepository;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\S3Service;
use App\Service\TypeaheadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class DocsDelController extends AbstractController
{
  #[Route(
    '/{system}/{role_short}/docs/{id}/del',
    name: 'docs_del',
    methods: ['GET', 'POST'],
    requirements: [
      'id'            => '%assert.id%',
      'system'        => '%assert.system%',
      'role_short'    => '%assert.role_short.admin%',
    ],
    defaults: [
      'module'        => 'docs',
    ],
  )]

  public function __invoke(
    Request $request,
    int $id,
    DocRepository $doc_repository,
    ConfigService $config_service,
    LoggerInterface $logger,
    S3Service $s3_service,
    TypeaheadService $typeahead_service,
    PageParamsService $pp,
    string $env_s3_url,
  ):Response
  {
    if (!$config_service->get_bool(
      config_id: 'docs.enabled',
      schema: $pp->schema_o(),
    ))
    {
      throw new NotFoundHttpException('Documents module not enabled.');
    }

    $command = new DocsCommand();

    $doc = $doc_repository->get(
      id: $id,
      schema: $pp->schema_o(),
    );

    $command->file_location = $env_s3_url . $doc['filename'];
    $command->original_filename = $doc['original_filename'];
    $command->name = $doc['name'];
    $command->access = $doc['access'];

    if (isset($doc['map_id']))
    {
      $doc_map = $doc_repository->get_map(
        map_id: $doc['map_id'],
        schema: $pp->schema_o(),
      );
      $command->map_name = $doc_map['name'];
    }

    $form = $this->createForm(
      type: DocsDelType::class,
      data: $command,
    );
    $form->handleRequest($request);

    if ($form->isSubmitted()
      && $form->isValid())
    {
      $alert_success_msg = [];

      $doc_repository->del(
        id: $id,
        schema: $pp->schema_o(),
      );

      $err = $s3_service->del($doc['filename']);

      if ($err)
      {
        $logger->error('doc delete file fail: ' . $err,
          ['schema' => $pp->schema()]);
      }

      $name = $doc['name'] ?? $doc['original_filename'];
      $alert_success_msg[] = 'Document "' . $name . '" is verwijderd.';

      if (isset($doc['map_id']))
      {
        $map_doc_count = $doc_repository->get_count_for_map_id(
          map_id: $doc['map_id'],
          schema: $pp->schema_o(),
        );

        if ($map_doc_count === 0)
        {
          $alert_success_msg[] = 'Map "' . $doc_map['name'] . '" bevatte geen items meer en werd automatisch gewist.';
          $doc_repository->del_map(
            map_id: $doc['map_id'],
            schema: $pp->schema_o(),
          );
          $typeahead_service->clear_cache($pp->schema());
          unset($doc['map_id']);
        }
      }

      foreach ($alert_success_msg as $success)
      {
        $this->addFlash('success', $success);
      }

      if (!isset($doc['map_id']))
      {
        return $this->redirectToRoute('docs', $pp->ary());
      }

      return $this->redirectToRoute('docs_map', [
        ...$pp->ary(),
        'id' => $doc['map_id'],
      ]);
    }

    return $this->render('docs/docs_del.html.twig', [
      'form'  => $form->createView(),
      'doc'   => $doc,
    ]);
  }
}
