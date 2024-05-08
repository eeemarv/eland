<?php declare(strict_types=1);

namespace App\Controller\News;

use App\Cache\ConfigCache;
use App\Command\News\NewsCommand;
use App\Form\Type\News\NewsType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\NewsRepository;
use App\Service\AlertService;
use App\Service\PageParamsService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class NewsEditController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/news/{id}/edit',
        name: 'news_edit',
        methods: ['GET', 'POST'],
        requirements: [
            'id'            => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'module'        => 'news',
        ],
    )]

    public function __invoke(
        Request $request,
        int $id,
        NewsRepository $news_repository,
        ConfigCache $config_cache,
        AlertService $alert_service,
        PageParamsService $pp
    ):Response
    {
        if (!$config_cache->get_bool('news.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('News module not enabled.');
        }

        $news_item = $news_repository->get($id, $pp->schema());
        $command = new NewsCommand();
        $command->id = $id;
        $command->subject = $news_item['subject'];
        $command->event_at = $news_item['event_at'];
        $command->location = $news_item['location'];
        $command->content = $news_item['content'];
        $command->access = $news_item['access'];

        $form_options = [
            'validation_groups' => ['edit'],
        ];

        $form = $this->createForm(NewsType::class, $command, $form_options);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();
            $news_repository->update($command, $pp->schema());

            $alert_service->success('Nieuwsbericht aangepast.');
            return $this->redirectToRoute('news_show', [
                ...$pp->ary(),
                'id' => $id,
            ]);
        }

        return $this->render('news/news_edit.html.twig', [
            'form'      => $form->createView(),
            'news_item' => $news_item,
        ]);
    }
}
