<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Form\EventSubscriber\AccessFieldSubscriber;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\FormTokenService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class UsersConfigNewController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/users/config-new',
        name: 'users_config_new',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'module'        => 'users',
        ],
    )]

    public function __invoke(
        Request $request,
        FormTokenService $form_token_service,
        AlertService $alert_service,
        AccessFieldSubscriber $access_field_subscriber,
        ConfigService $config_service,
        LinkRender $link_render,
        PageParamsService $pp,
        MenuService $menu_service
    ):Response
    {
        if (!$config_service->get_bool('users.new.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('New users not enabled.');
        }

        $errors = [];

        $days = $config_service->get_int('users.new.days', $pp->schema());
        $access = $config_service->get_str('users.new.access', $pp->schema());
        $access_list = $config_service->get_str('users.new.access_list', $pp->schema());
        $access_pane = $config_service->get_str('users.new.access_pane', $pp->schema());

        $form_data = [
            'days'              => $days,
            'access'            => $access,
            'access_list'       => $access_list,
            'access_pane'       => $access_pane,
        ];

        $builder = $this->createFormBuilder($form_data);
        $builder->add('days', IntegerType::class);
        $builder->add('submit', SubmitType::class);
        $access_field_subscriber->add('access', ['admin', 'user', 'guest']);
        $access_field_subscriber->add('access_list', ['admin', 'user', 'guest']);
        $access_field_subscriber->add('access_pane', ['admin', 'user', 'guest']);
        $builder->addEventSubscriber($access_field_subscriber);
        $form = $builder->getForm();
        $form->handleRequest($request);

        if ($request->isMethod('POST'))
        {
            if ($token_error = $form_token_service->get_error())
            {
                $errors[] = $token_error;
            }

            if (count($errors))
            {
                $alert_service->error($errors);
            }
        }

        if ($form->isSubmitted()
            && $form->isValid()
            && !count($errors))
        {
            $form_data = $form->getData();

            $config_service->set_int('users.new.days', $form_data['days'], $pp->schema());
            $config_service->set_str('users.new.access', $form_data['access'], $pp->schema());
            $config_service->set_str('users.new.access_list', $form_data['access_list'], $pp->schema());
            $config_service->set_str('users.new.access_pane', $form_data['access_pane'], $pp->schema());

            $alert_service->success('Configuratie instappers aangepast');
            $link_render->redirect('users_config_new', $pp->ary(), []);
        }

        $menu_service->set('users_config_new');

        return $this->render('users/users_config_new.html.twig', [
            'form'          => $form->createView(),
            'form_token'    => $form_token_service->get(),
            'schema'        => $pp->schema(),
        ]);
    }
}
