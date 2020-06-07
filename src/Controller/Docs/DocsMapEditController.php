<?php declare(strict_types=1);

namespace App\Controller\Docs;

use App\Command\Docs\DocsMapEditCommand;
use App\Form\Post\Docs\DocsMapType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\MenuService;
use App\Render\LinkRender;
use App\Repository\DocRepository;
use App\Service\PageParamsService;
use App\Service\TypeaheadService;

class DocsMapEditController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $id,
        DocRepository $doc_repository,
        AlertService $alert_service,
        LinkRender $link_render,
        TypeaheadService $typeahead_service,
        MenuService $menu_service,
        PageParamsService $pp
    ):Response
    {
        $name = trim($request->request->get('name', ''));

        $doc_map = $doc_repository->get_map($id, $pp->schema());

        $docs_map_edit_command = new DocsMapEditCommand();
        $docs_map_edit_command->name = $doc_map['name'];
        $docs_map_edit_command->id = $id;

        $form = $this->createForm(DocsMapType::class,
                $docs_map_edit_command, [
                    'initial_value' => $doc_map['name'],
                ])
            ->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $docs_map_edit_command = $form->getData();
            $name = $docs_map_edit_command->name;

            $doc_repository->update_map_name($name, $id, $pp->schema());
            $typeahead_service->clear(TypeaheadService::GROUP_DOC_MAP_NAMES);

            $alert_service->success('docs_map_edit.success');

            $link_render->redirect('docs_map', $pp->ary(),
                ['id' => $id]);
        }

        $menu_service->set('docs');

        return $this->render('docs/docs_map_edit.html.twig', [
            'form'      => $form->createView(),
            'doc_map'   => $doc_map,
            'schema'    => $pp->schema(),
        ]);
    }
}
