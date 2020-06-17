<?php declare(strict_types=1);

namespace App\Controller\Docs;

use App\Command\Docs\DocsCommand;
use App\Form\Post\Docs\DocsAddType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\MenuService;
use App\Render\LinkRender;
use App\Repository\DocRepository;
use App\Service\PageParamsService;
use App\Service\S3Service;
use App\Service\SessionUserService;
use App\Service\TypeaheadService;
use Psr\Log\LoggerInterface;

class DocsAddController extends AbstractController
{
    public function __invoke(
        Request $request,
        DocRepository $doc_repository,
        LoggerInterface $logger,
        AlertService $alert_service,
        LinkRender $link_render,
        S3Service $s3_service,
        TypeaheadService $typeahead_service,
        PageParamsService $pp,
        SessionUserService $su,
        MenuService $menu_service
    ):Response
    {
        $docs_command = new DocsCommand();

        if ($request->query->has('map_id'))
        {
            $map_id = (int) $request->query->get('map_id');
            $map_name = $doc_repository->get_map($map_id, $pp->schema())['name'];
            $docs_command->map_name = $map_name;
        }

        $form = $this->createForm(DocsAddType::class,
                $docs_command, ['validation_groups' => ['add']])
            ->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $docs_command = $form->getData();
            $file = $docs_command->file;
            $name = $docs_command->name;
            $map_name = $docs_command->map_name;
            $access = $docs_command->access;

            $ext = $file->getClientOriginalExtension();
            $original_filename = $file->getClientOriginalName();
            $tmpfile = $file->getRealPath();

            $doc_id = substr(sha1(random_bytes(16)), 0, 24);
            $filename = $pp->schema() . '_d_' . $doc_id . '.' . $ext;
            $error = $s3_service->doc_upload($filename, $tmpfile);

            if ($error)
            {
                $errors[] = '';
                $logger->error('doc upload fail: ' . $error,
                    ['schema' => $pp->schema()]);

                $alert_service->error('docs_add.error.upload_fail');
                $link_render->redirect('docs', $pp->ary(), []);
            }

            $doc = [
                'filename'		    => $filename,
                'original_filename' => $original_filename,
                'access'		    => $access,
                'user_id'		    => $su->id(),
            ];

            if ($name)
            {
                $doc['name'] = $name;
            }

            if (isset($map_name) && strlen($map_name))
            {
                $map_id = $doc_repository->get_map_id_by_name($map_name, $pp->schema());

                if (!$map_id)
                {
                    $map_id = $doc_repository->insert_map($map_name, $su->id(), $pp->schema());
                    $typeahead_service->clear(TypeaheadService::GROUP_DOC_MAP_NAMES);
                }

                $doc['map_id'] = $map_id;
            }

            $doc_repository->insert_doc($doc, $pp->schema());



            if (isset($doc['map_id']))
            {
                $alert_service->success('docs_add.success.map', [
                    '%map_name%'    => $map_name,
                ]);
                $link_render->redirect('docs_map', $pp->ary(), ['id' => $map_id]);
            }

            $alert_service->success('docs_add.success.no_map');
            $link_render->redirect('docs', $pp->ary(), []);
        }

        $menu_service->set('docs');

        return $this->render('docs/docs_add.html.twig', [
            'form'      => $form->createView(),
            'schema'    => $pp->schema(),
        ]);
    }
}
