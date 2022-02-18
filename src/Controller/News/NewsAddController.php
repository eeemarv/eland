<?php declare(strict_types=1);

namespace App\Controller\News;

use App\Command\News\NewsCommand;
use App\Form\Type\News\NewsType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\NewsRepository;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class NewsAddController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/news/add',
        name: 'news_add',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'module'        => 'news',
        ],
    )]

    public function __invoke(
        Request $request,
        NewsRepository $news_repository,
        ConfigService $config_service,
        AlertService $alert_service,
        PageParamsService $pp,
        SessionUserService $su
    ):Response
    {
        if (!$config_service->get_bool('news.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('News module not enabled.');
        }

        $command = new NewsCommand();
        $form = $this->createForm(NewsType::class, $command);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();
            $id = $news_repository->insert($command, $su->id(), $pp->schema());

            $alert_service->success('Nieuwsbericht opgeslagen.');
            return $this->redirectToRoute('news_show', [
                ...$pp->ary(),
                'id' => $id,
            ]);
        }

        return $this->render('news/news_add.html.twig', [
            'form'      => $form->createView(),
        ]);
    }
}
