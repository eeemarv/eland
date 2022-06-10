<?php declare(strict_types=1);

namespace App\Controller\News;

use App\Command\News\NewsViewsCommand;
use App\Form\Type\Users\UsersConfigLeavingType;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class NewsViewsController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/news/views',
        name: 'news_views',
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
            throw new NotFoundHttpException('news module not enabled.');
        }

        $command = new NewsViewsCommand();

        $config_service->load_command($command, $pp->schema());

        $form = $this->createForm(UsersConfigLeavingType::class, $command);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();
            $config_service->store_command($command, $pp->schema());

            $alert_service->success('Configuratie uitstappende leden aangepast');
            return $this->redirectToRoute('users_config_leaving', $pp->ary());
        }

        return $this->render('users/users_config_leaving.html.twig', [
            'form'          => $form->createView(),
        ]);
    }
}
