<?php declare(strict_types=1);

namespace App\Controller\News;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\MenuService;
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
        MenuService $menu_service,
        LinkRender $link_render,
        ConfigService $config_service,
        PageParamsService $pp
    ):Response
    {
        if (!$config_service->get_bool('news.enabled', $pp->schema()))
        {
            throw new AccessDeniedHttpException('News module not enabled.');
        }

        $form_data = [
            'sort_asc' => $config_service->get_bool('news.sort.asc', $pp->schema()),
        ];

        $builder = $this->createFormBuilder($form_data);
        $builder->add('sort_asc', CheckboxType::class);
        $builder->add('submit', SubmitType::class);
        $form = $builder->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $form_data = $form->getData();
            $self_edit = $form_data['sort_asc'];

            $config_service->set_bool('news.sort.asc', $self_edit, $pp->schema());

            $alert_service->success('Sortering nieuws configuratie aangepast');
            $link_render->redirect('news_sort', $pp->ary(), []);
        }

        $menu_service->set('news_sort');

        return $this->render('news/news_sort.html.twig', [
            'form'          => $form->createView(),
            'schema'        => $pp->schema(),
        ]);
    }
}
