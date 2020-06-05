<?php declare(strict_types=1);

namespace App\Controller\Docs;

use App\Command\Docs\DocsDelCommand;
use App\Form\Post\DelType;
use App\Render\LinkRender;
use App\Repository\DocRepository;
use App\Service\AlertService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\S3Service;
use App\Service\TypeaheadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface;

class DocsDelController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $id,
        DocRepository $doc_repository,
        AlertService $alert_service,
        LinkRender $link_render,
        S3Service $s3_service,
        LoggerInterface $logger,
        TypeaheadService $typeahead_service,
        MenuService $menu_service,
        PageParamsService $pp
    ):Response
    {
        $doc = $doc_repository->get($id, $pp->schema());
        $doc['name'] ??= $doc['original_filename'];

        if (isset($doc['map_id']) && $doc['map_id'])
        {
            $doc_count = $doc_repository->get_count_for_map_id($doc['map_id'], $pp->schema());
        }

        $docs_del_command = new DocsDelCommand();

        $form = $this->createForm(DelType::class,
                $docs_del_command)
            ->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $alert_trans_ary = [
                '%name%'    => $doc['name'],
            ];

            if ($doc_repository->del($id, $pp->schema()))
            {
                $err = $s3_service->del($doc['filename']);

                if ($err)
                {
                    $logger->error('doc delete file fail: ' . $err,
                        ['schema' => $pp->schema()]);
                }

                if (isset($doc_count) && $doc_count < 2)
                {
                    $doc_repository->del_map($doc['map_id'], $pp->schema());
                    $typeahead_service->clear(TypeaheadService::GROUP_DOC_MAP_NAMES);
                    unset($doc['map_id']);
                }

                $alert_service->success('docs_del.success', $alert_trans_ary);

                if (isset($doc['map_id']))
                {
                    $link_render->redirect('docs_map', $pp->ary(), ['id' => $doc['map_id']]);
                }

                $link_render->redirect('docs', $pp->ary(), []);
            }

            $alert_service->error('docs_del.error');
        }

        $menu_service->set('docs');

        return $this->render('docs/docs_del.html.twig', [
            'form'          => $form->createView(),
            'doc'           => $doc,
            'schema'        => $pp->schema(),
        ]);
    }
}
