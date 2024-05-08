<?php declare(strict_types=1);

namespace App\Controller\Tags;

use App\Cache\ConfigCache;
use App\Command\Tags\TagsListCommand;
use App\Form\Type\Tags\TagsListType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\TagRepository;
use App\Service\PageParamsService;
use App\Service\AlertService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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
            'tag_type'      => 'users',
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
            'tag_type'      => 'messages',
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
            'tag_type'      => 'calendar',
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
            'tag_type'      => 'news',
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
            'tag_type'      => 'transactions',
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
            'tag_type'      => 'docs',
            'module'        => 'docs',
            'sub_module'    => 'tags',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/tags/forum-topics',
        name: 'tags_forum_topics',
        methods: ['GET', 'POST'],
        requirements: [
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
        '/{system}/{role_short}/tags/blog',
        name: 'tags_blog',
        methods: ['GET', 'POST'],
        requirements: [
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
        string $module,
        string $tag_type,
        Request $request,
        TagRepository $tag_repository,
        AlertService $alert_service,
        ConfigCache $config_cache,
        PageParamsService $pp
    ):Response
    {
        switch ($tag_type)
        {
            case 'users':
                if (!$config_cache->get_bool('users.tags.enabled', $pp->schema()))
                {
                    throw new NotFoundHttpException('Tags for users not enabled.');
                }
                break;
            default:
                throw new NotFoundHttpException('Tag type not supported.');
                break;
        }

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

            return $this->redirectToRoute('tags_' . $tag_type, $pp->ary());
        }

        return $this->render('tags/tags_list.html.twig', [
            'tags'      => $tags,
            'form'      => $form->createView(),
            'module'    => $module,
            'tag_type'  => $tag_type,
        ]);
    }
}
