<?php declare(strict_types=1);

namespace App\Controller\News;

use App\Command\News\NewsSortCommand;
use App\Form\Post\News\NewsSortType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;

class NewsSortController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/news/sort',
        name: 'news_sort',
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
        AlertService $alert_service,
        ConfigService $config_service,
        PageParamsService $pp
    ):Response
    {
        if (!$config_service->get_bool('news.enabled', $pp->schema()))
        {
            throw new AccessDeniedHttpException('News module not enabled.');
        }

        $command = new NewsSortCommand();
        $config_service->load_command($command, $pp->schema());

        $form = $this->createForm(NewsSortType::class, $command);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();
            $config_service->store_command($command, $pp->schema());

            $alert_service->success('Sortering nieuws configuratie aangepast');
            return $this->redirectToRoute('news_sort', $pp->ary());
        }

        return $this->render('news/news_sort.html.twig', [
            'form'          => $form->createView(),
        ]);
    }
}
