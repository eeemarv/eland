<?php declare(strict_types=1);

namespace App\Controller\Docs;

use App\Cache\ConfigCache;
use App\Command\Docs\DocsMapCommand;
use App\Form\Type\Docs\DocsMapType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Repository\DocRepository;
use App\Service\PageParamsService;
use App\Service\ResponseCacheService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class DocsMapEditController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/docs/map/{id}/edit',
        name: 'docs_map_edit',
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
        ConfigCache $config_cache,
        AlertService $alert_service,
        ResponseCacheService $response_cache_service,
        PageParamsService $pp
    ):Response
    {
        if (!$config_cache->get_bool('docs.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Documents module not enabled.');
        }

        $doc_map = $doc_repository->get_map($id, $pp->schema());

        $command = new DocsMapCommand();
        $command->id = $id;
        $command->name = $doc_map['name'];

        $form_options = [
            'render_omit' => $doc_map['name'],
            'validation_groups' => ['edit'],
        ];

        $form = $this->createForm(DocsMapType::class,
            $command, $form_options);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();

            if ($command->name === $doc_map['name'])
            {
                $alert_service->warning('Map naam niet gewijzigd');
            }
            else
            {
                $doc_repository->update_map_name($command->name, $id, $pp->schema());

                $response_cache_service->clear_cache($pp->schema());

                $alert_service->success('Map naam aangepast');
            }

            return $this->redirectToRoute('docs_map', [
                ...$pp->ary(),
                'id' => $id,
            ]);
        }

        return $this->render('docs/docs_map_edit.html.twig', [
            'form'      => $form->createView(),
            'doc_map'   => $doc_map,
        ]);
    }
}
