<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Form\EventSubscriber\AccessFieldSubscriber;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class UsersConfigLeavingController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/users/config-leaving',
        name: 'users_config_leaving',
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
        AlertService $alert_service,
        AccessFieldSubscriber $access_field_subscriber,
        ConfigService $config_service,
        LinkRender $link_render,
        PageParamsService $pp,
        MenuService $menu_service
    ):Response
    {
        if (!$config_service->get_bool('users.leaving.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Leaving users not enabled.');
        }

        $access = $config_service->get_str('users.leaving.access', $pp->schema());
        $access_list = $config_service->get_str('users.leaving.access_list', $pp->schema());
        $access_pane = $config_service->get_str('users.leaving.access_pane', $pp->schema());
        $equilibrium = $config_service->get_int('accounts.equilibrium', $pp->schema());
        $auto_deactivate = $config_service->get_bool('users.leaving.auto_deactivate', $pp->schema());
        $transactions_enabled = $config_service->get_bool('transactions.enabled', $pp->schema());

        $form_data = [
            'access'            => $access,
            'access_list'       => $access_list,
            'access_pane'       => $access_pane,
            'equilibrium'       => $equilibrium,
            'auto_deactivate'   => $auto_deactivate,
        ];

        $builder = $this->createFormBuilder($form_data);

        if ($transactions_enabled)
        {
            $builder->add('equilibrium', IntegerType::class);
            $builder->add('auto_deactivate', CheckboxType::class);
        }

        $builder->add('submit', SubmitType::class);
        $access_field_subscriber->add('access', ['admin', 'user', 'guest']);
        $access_field_subscriber->add('access_list', ['admin', 'user', 'guest']);
        $access_field_subscriber->add('access_pane', ['admin', 'user', 'guest']);
        $builder->addEventSubscriber($access_field_subscriber);
        $form = $builder->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $form_data = $form->getData();

            $config_service->set_str('users.leaving.access', $form_data['access'], $pp->schema());
            $config_service->set_str('users.leaving.access_list', $form_data['access_list'], $pp->schema());
            $config_service->set_str('users.leaving.access_pane', $form_data['access_pane'], $pp->schema());

            if ($transactions_enabled)
            {
                $config_service->set_int('accounts.equilibrium', $form_data['equilibrium'], $pp->schema());
                $config_service->set_bool('users.leaving.auto_deactivate', $form_data['auto_deactivate'], $pp->schema());
            }

            $alert_service->success('Configuratie uitstappende leden aangepast');
            $link_render->redirect('users_config_leaving', $pp->ary(), []);
        }

        $menu_service->set('users_config_leaving');

        return $this->render('users/users_config_leaving.html.twig', [
            'form'          => $form->createView(),
            'schema'        => $pp->schema(),
        ]);
    }
}
