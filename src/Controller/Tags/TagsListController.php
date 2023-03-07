<?php declare(strict_types=1);

namespace App\Controller\Tags;

use App\Command\Tags\TagsListCommand;
use App\Form\Type\Tags\TagsListType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\TagRepository;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\AlertService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class TagsListController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/tags/users',
        name: 'tags_users',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'module'        => 'users',
            'sub_module'    => 'tags',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/tags/messages',
        name: 'tags_messages',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'module'        => 'messages',
            'sub_module'    => 'tags',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/tags/calendar',
        name: 'tags_calendar',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'module'        => 'calendar',
            'sub_module'    => 'tags',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/tags/news',
        name: 'tags_news',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'module'        => 'news',
            'sub_module'    => 'tags',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/tags/transactions',
        name: 'tags_transactions',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'module'        => 'transactions',
            'sub_module'    => 'tags',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/tags/docs',
        name: 'tags_docs',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'module'        => 'docs',
            'sub_module'    => 'tags',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/tags/forum',
        name: 'tags_forum',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'module'        => 'forum',
            'sub_module'    => 'tags',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/tags/blog',
        name: 'tags_blog',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'module'        => 'blog',
            'sub_module'    => 'tags',
        ],
    )]

    public function __invoke(
        string $module,
        Request $request,
        TagRepository $tag_repository,
        AlertService $alert_service,
        ConfigService $config_service,
        PageParamsService $pp
    ):Response
    {
        $tag_type = $module;

        $command = new TagsListCommand();

        $tags = $tag_repository->get_all_with_count($tag_type, $pp->schema());
        $tag_ary = [];

        foreach ($tags as $tag)
        {
            $tag_ary[] = $tag['id'];
        }

        $command->tags = json_encode($tag_ary);

        $form = $this->createForm(TagsListType::class, $command)
            ->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();
            $tags_json = $command->tags;
            $posted_tags = json_decode($tags_json, true);

            $update_count = $tag_repository->update_list($posted_tags, $tag_type, $pp->schema());

            if ($update_count)
            {
                $alert_service->success('Plaatsing tags aangepast.');
            }
            else
            {
                $alert_service->warning('Geen aangepaste plaatsing van tags');
            }

            return $this->redirectToRoute('tags_' . $module, $pp->ary());
        }

        return $this->render('tags/tags_' . $tag_type . '_list.html.twig', [
            'tags'      => $tags,
            'form'      => $form->createView(),
            'module'    => $module,
        ]);
    }
}
