<?php declare(strict_types=1);

namespace App\Controller\Docs;

use App\Command\Docs\DocsCommand;
use App\Form\Post\Docs\DocsAddType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Repository\DocRepository;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\S3Service;
use App\Service\SessionUserService;
use App\Service\TypeaheadService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class DocsAddController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/docs/add',
        name: 'docs_add',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'module'        => 'docs',
        ],
    )]

    public function __invoke(
        Request $request,
        DocRepository $doc_repository,
        ConfigService $config_service,
        LoggerInterface $logger,
        AlertService $alert_service,
        S3Service $s3_service,
        TypeaheadService $typeahead_service,
        PageParamsService $pp,
        SessionUserService $su
    ):Response
    {
        if (!$config_service->get_bool('docs.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Documents module not enabled.');
        }

        $command = new DocsCommand();

        if ($request->query->has('map_id'))
        {
            $map_id = (int) $request->query->get('map_id');
            $map_name = $doc_repository->get_map($map_id, $pp->schema())['name'];
            $command->map_name = $map_name;
        }

        $form = $this->createForm(DocsAddType::class,
                $command, ['validation_groups' => ['add']]);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();
            $file = $command->file;
            $name = $command->name;
            $map_name = $command->map_name;
            $access = $command->access;

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

                $alert_service->error('Fout bij het opladen van het document');
                $this->redirectToRoute('docs_add', $pp->ary());
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
                    $typeahead_service->clear_cache($pp->schema);
                    $new_map_created = true;
                }

                $doc['map_id'] = $map_id;
            }

            $doc_repository->insert_doc($doc, $pp->schema());

            if (isset($doc['map_id']))
            {
                if (isset($new_map_created) && $new_map_created)
                {
                    $alert_service->success('Document opgeladen in nieuwe map "' . $map_name . '"');
                }
                else
                {
                    $alert_service->success('Document opgeladen in map "' . $map_name . '"');
                }

                return $this->redirectToRoute('docs_map', array_merge($pp->ary(), ['id' => $map_id]));
            }

            $alert_service->success('Document opgeladen.');
            return $this->redirectToRoute('docs', $pp->ary());
        }

        return $this->render('docs/docs_add.html.twig', [
            'form'  => $form->createView(),
        ]);
    }
}
