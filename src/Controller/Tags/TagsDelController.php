<?php declare(strict_types=1);

namespace App\Controller\Tags;

use App\Command\Tags\TagsDefCommand;
use App\Form\Type\Tags\TagsDefType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Repository\TagRepository;
use App\Service\PageParamsService;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class TagsDelController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/tags/users/{id}/del',
        name: 'tags_users_del',
        methods: ['GET', 'POST'],
        requirements: [
            'id'            => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'module'                => 'users',
            'sub_module'            => 'tags',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/tags/messages/{id}/del',
        name: 'tags_messages_del',
        methods: ['GET', 'POST'],
        requirements: [
            'id'            => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'module'                => 'messages',
            'sub_module'            => 'tags',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/tags/calendar/{id}/del',
        name: 'tags_calendar_del',
        methods: ['GET', 'POST'],
        requirements: [
            'id'            => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'module'                => 'calendar',
            'sub_module'            => 'tags',
        ],
    )]

    public function __invoke(
        Request $request,
        int $id,
        string $module,
        TagRepository $tag_repository,
        AlertService $alert_service,
        PageParamsService $pp
    ):Response
    {
        $tag_type = $module;

        $tag = $tag_repository->get_with_count($id, $tag_type, $pp->schema());

        if ($tag['count'])
        {
            throw new BadRequestException('Count not zero, tag can not be deleted.');
        }

        $command = new TagsDefCommand();
        $command->txt = $tag['txt'];
        $command->txt_color = $tag['txt_color'];
        $command->bg_color = $tag['bg_color'];
        $command->description = $tag['description'];

        $form_options = [
            'validation_groups'     => ['del'],
        ];

        $form = $this->createForm(TagsDefType::class, $command, $form_options);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();
            $command->id = $id;
            $command->tag_type = $tag_type;
            $tag_repository->del($command, $pp->schema());
            $alert_service->success('Tag "' . $command->txt . '" verwijderd.');

            return $this->redirectToRoute('tags_' . $tag_type, $pp->ary());
        }

        return $this->render('tags/tags_' . $tag_type . '_del.html.twig', [
            'form'  => $form->createView(),
        ]);
    }
}
