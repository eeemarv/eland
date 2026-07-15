<?php declare(strict_types=1);

namespace App\Controller\Docs;

use App\Command\Docs\DocsCommand;
use App\Form\Type\Docs\DocsEditType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\DocRepository;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class DocsEditController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/docs/{id}/edit',
    name: 'docs_edit',
    methods: ['GET', 'POST'],
    requirements: [
      'id'            => '%assert.id%',
      'schema'        => '%assert.schema%',
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
    PageParamsService $pp,
    SessionUserService $su,
    string $env_s3_url,
  ):Response
  {
    if (!$config_service->get_bool(
      config_id: 'docs.enabled',
      schema: $pp->schema_o(),
    ))
    {
      throw $this->createNotFoundException('Documents module not enabled.');
    }

    $command = new DocsCommand();

    $doc = $doc_repository->get(
      id: $id,
      schema: $pp->schema_o(),
    );

    if ($doc === false)
    {
      throw $this->createNotFoundException(
        'Document with id ' . $id . ' not found'
      );
    }

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

      if ($doc_map === false)
      {
        throw $this->createNotFoundException(
          'Document with id ' . $doc['map_id'] . ' not found'
        );
      }

      $command->map_name = $doc_map['name'];
    }

    $form_options = ['validation_groups' => ['edit']];

    $form = $this->createForm(DocsEditType::class,
      $command, $form_options);
    $form->handleRequest($request);

    if ($form->isSubmitted()
      && $form->isValid())
    {
      $name = $command->name;
      $map_name = $command->map_name;
      $access = $command->access;

      $alert_success_msg = [];

      $update = [
        'access'    => $access,
        'name'      => $name,
      ];

      if (isset($doc['map_id']))
      {
        $map_doc_count = $doc_repository->get_count_for_map_id(
          map_id: $doc['map_id'],
          schema: $pp->schema_o(),
        );
      }
      else
      {
        $map_doc_count = 0;
      }

      if (isset($map_name) && strlen($map_name))
      {
        $map_id = $doc_repository->get_map_id_by_name(
          map_name: $map_name,
          schema: $pp->schema_o(),
        );

        if (!$map_id)
        {
          $map_id = $doc_repository->insert_map(
            map_name: $map_name,
            user_id: $su->id(),
            schema: $pp->schema_o(),
          );
          $alert_success_msg[] = 'Nieuwe map "' . $map_name . '" gecreëerd.';
        }

        if ($map_doc_count === 1 && $map_id !== $doc['map_id'])
        {
          $delete_map = true;
        }
      }
      else if ($map_doc_count === 1)
      {
        $delete_map = true;
      }

      $update['map_id'] = $map_id ?? null;

      $doc_repository->update_doc(
        update_ary: $update,
        doc_id: $id,
        schema: $pp->schema_o(),
      );

      if (isset($delete_map)
        && $delete_map
        && isset($doc_map['name'])
      )
      {
        $alert_success_msg[] = 'Map "' . $doc_map['name'] . '" bevatte geen items meer en werd automatisch gewist.';
        $doc_repository->del_map(
          map_id: $doc['map_id'],
          schema: $pp->schema_o(),
        );
      }

      $alert_success_msg[] = 'Document aangepast.';

      foreach ($alert_success_msg as $success)
      {
        $this->addFlash('success', $success);
      }

      if (!isset($update['map_id']))
      {
          return $this->redirectToRoute('docs', $pp->ary());
      }

      return $this->redirectToRoute('docs_map', [
        ...$pp->ary(),
        'id' => $update['map_id'],
      ]);
    }

    return $this->render('docs/docs_edit.html.twig', [
      'form'      => $form->createView(),
      'doc'       => $doc,
    ]);
  }
}
