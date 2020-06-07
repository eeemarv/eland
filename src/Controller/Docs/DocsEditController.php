<?php declare(strict_types=1);

namespace App\Controller\Docs;

use App\Command\Docs\DocsEditCommand;
use App\Form\Post\Docs\DocsEditType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Render\LinkRender;
use App\Repository\DocRepository;
use App\Service\AlertService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\TypeaheadService;

class DocsEditController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $id,
        DocRepository $doc_repository,
        AlertService $alert_service,
        LinkRender $link_render,
        TypeaheadService $typeahead_service,
        MenuService $menu_service,
        PageParamsService $pp,
        SessionUserService $su,
        string $env_s3_url
    ):Response
    {
        $docs_edit_command = new DocsEditCommand();

        $doc = $doc_repository->get($id, $pp->schema());

        $docs_edit_command->location = $env_s3_url . $doc['filename'];
        $docs_edit_command->original_filename = $doc['original_filename'];
        $docs_edit_command->name = $doc['name'];
        $docs_edit_command->access = $doc['access'];

        if (isset($doc['map_id']))
        {
            $doc_map = $doc_repository->get_map($doc['map_id'], $pp->schema());
            $docs_edit_command->map_name = $doc_map['name'];
        }

        $form = $this->createForm(DocsEditType::class,
                $docs_edit_command)
            ->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $docs_edit_command = $form->getData();
            $name = $docs_edit_command->name;
            $map_name = $docs_edit_command->map_name;
            $access = $docs_edit_command->access;

            $update = [
                'access'    => $access,
                'name'      => $name,
            ];

            if (isset($doc['map_id']))
            {
                $map_doc_count = $doc_repository->get_count_for_map_id($doc['map_id'], $pp->schema());
            }
            else
            {
                $map_doc_count = 0;
            }

            if (isset($map_name) && strlen($map_name))
            {
                $map_id = $doc_repository->get_map_id_by_name($map_name, $pp->schema());

                if (!$map_id)
                {
                    $map_id = $doc_repository->insert_map($map_name, $su->id(), $pp->schema());
                    $delete_thumbprint = true;
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

            if (isset($delete_map) && $delete_map)
            {
                $doc_repository->del_map($doc['map_id'], $pp->schema());
                $delete_thumbprint = true;
            }

            if (isset($delete_thumbprint) && $delete_thumbprint)
            {
                $typeahead_service->clear(TypeaheadService::GROUP_DOC_MAP_NAMES);
            }

            $doc_repository->update_doc($update, $id, $pp->schema());

            $alert_service->success('docs_edit.success');

            if (!isset($update['map_id']))
            {
                $link_render->redirect('docs', $pp->ary(), []);
            }

            $link_render->redirect('docs_map', $pp->ary(),
                ['id' => $update['map_id']]);
        }

        $menu_service->set('docs');

        return $this->render('docs/docs_edit.html.twig', [
            'form'      => $form->createView(),
            'doc'       => $doc,
            'schema'    => $pp->schema(),
        ]);
    }
}
