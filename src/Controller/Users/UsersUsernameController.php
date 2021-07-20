<?php declare(strict_types=1);

namespace App\Controller\Users;

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
use Symfony\Component\Routing\Annotation\Route;

class UsersUsernameController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/users/username',
        name: 'users_username',
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
        MenuService $menu_service,
        LinkRender $link_render,
        ConfigService $config_service,
        PageParamsService $pp
    ):Response
    {
        $form_data = [
            'self_edit' => $config_service->get_bool('users.fields.username.self_edit', $pp->schema()),
        ];

        $builder = $this->createFormBuilder($form_data);
        $builder->add('self_edit', CheckboxType::class);
        $builder->add('submit', SubmitType::class);
        $form = $builder->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $form_data = $form->getData();
            $self_edit = $form_data['self_edit'];

            $config_service->set_bool('users.fields.username.self_edit', $self_edit, $pp->schema());

            $alert_service->success('Gebruikersnaam configuratie aangepast');
            $link_render->redirect('users_username', $pp->ary(), []);
        }

        $menu_service->set('users_username');

        return $this->render('users/users_username.html.twig', [
            'form'          => $form->createView(),
        ]);
    }
}
