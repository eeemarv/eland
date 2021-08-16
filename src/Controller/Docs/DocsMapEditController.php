<?php declare(strict_types=1);

namespace App\Controller\Docs;

use App\Command\Docs\DocsMapCommand;
use App\Form\Post\Docs\DocsMapType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\FormTokenService;
use App\Render\LinkRender;
use App\Repository\DocRepository;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\TypeaheadService;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

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
        Db $db,
        DocRepository $doc_repository,
        ConfigService $config_service,
        AlertService $alert_service,
        LinkRender $link_render,
        TypeaheadService $typeahead_service,
        FormTokenService $form_token_service,
        PageParamsService $pp
    ):Response
    {
        if (!$config_service->get_bool('docs.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Documents module not enabled.');
        }

        $doc_map = $doc_repository->get_map($id, $pp->schema());

        $command = new DocsMapCommand();
        $command->id = $id;
        $command->name = $doc_map['name'];

        $form = $this->createForm(DocsMapType::class,
            $command, ['render_omit' => $doc_map['name']]);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();

            $doc_repository->update_map_name($command->name, $id, $pp->schema());

            $typeahead_service->clear_cache($pp->schema());

            $alert_service->success('Map naam aangepast.');
            return $this->redirectToRoute('docs_map', array_merge($pp->ary(),
                ['id' => $id]));
        }

        return $this->render('docs/docs_map_edit.html.twig', [
            'form'      => $form->createView(),
            'doc_map'   => $doc_map,
        ]);
    }
}
