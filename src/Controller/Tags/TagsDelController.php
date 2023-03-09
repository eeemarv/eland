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
            'tag_type'      => 'users',
            'module'        => 'users',
            'sub_module'    => 'tags',
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
            'tag_type'      => 'messages',
            'module'        => 'messages',
            'sub_module'    => 'tags',
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
            'tag_type'      => 'calendar',
            'module'        => 'calendar',
            'sub_module'    => 'tags',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/tags/news/{id}/del',
        name: 'tags_news_del',
        methods: ['GET', 'POST'],
        requirements: [
            'id'            => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'tag_type'      => 'news',
            'module'        => 'news',
            'sub_module'    => 'tags',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/tags/transactions/{id}/del',
        name: 'tags_transactions_del',
        methods: ['GET', 'POST'],
        requirements: [
            'id'            => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'tag_type'      => 'transactions',
            'module'        => 'transactions',
            'sub_module'    => 'tags',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/tags/docs/{id}/del',
        name: 'tags_docs_del',
        methods: ['GET', 'POST'],
        requirements: [
            'id'            => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'tag_type'      => 'docs',
            'module'        => 'docs',
            'sub_module'    => 'tags',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/tags/forum-topics/{id}/del',
        name: 'tags_forum_topics_del',
        methods: ['GET', 'POST'],
        requirements: [
            'id'            => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'tag_type'      => 'forum_topics',
            'module'        => 'forum',
            'sub_module'    => 'tags',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/tags/blog/{id}/del',
        name: 'tags_blog_del',
        methods: ['GET', 'POST'],
        requirements: [
            'id'            => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'tag_type'      => 'blog',
            'module'        => 'blog',
            'sub_module'    => 'tags',
        ],
    )]

    public function __invoke(
        Request $request,
        int $id,
        string $module,
        string $tag_type,
        TagRepository $tag_repository,
        AlertService $alert_service,
        PageParamsService $pp
    ):Response
    {
        $tag = $tag_repository->get_with_count($id, $tag_type, $pp->schema());

        if ($tag['count'] !== 0)
        {
            throw new BadRequestException('Count not zero, tag can not be deleted.');
        }

        $command = new TagsDefCommand();
        $command->txt = $tag['txt'];
        $command->txt_color = $tag['txt_color'];
        $command->bg_color = $tag['bg_color'];
        $command->description = $tag['description'];
        $command->id = $id;
        $command->tag_type = $tag_type;

        $form = $this->createForm(TagsDefType::class, $command, [
            'del'       => true,
            'tag_type'  => $tag_type,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $tag_repository->del($id, $tag_type, $pp->schema());
            $alert_service->success('Tag "' . $command->txt . '" verwijderd.');

            return $this->redirectToRoute('tags_' . $tag_type, $pp->ary());
        }

        return $this->render('tags/tags_del.html.twig', [
            'form'      => $form->createView(),
            'module'    => $module,
            'tag_type'  => $tag_type,
        ]);
    }
}
