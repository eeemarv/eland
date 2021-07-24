<?php declare(strict_types=1);

namespace App\Controller\Users;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;

class UsersFullNameController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/users/full-name',
        name: 'users_full_name',
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
        LinkRender $link_render,
        ConfigService $config_service,
        PageParamsService $pp
    ):Response
    {
        if (!$config_service->get_bool('users.fields.full_name.enabled', $pp->schema()))
        {
            throw new AccessDeniedHttpException('Full name module not enabled.');
        }

        $form_data = [
            'self_edit' => $config_service->get_bool('users.fields.full_name.self_edit', $pp->schema()),
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

            $config_service->set_bool('users.fields.full_name.self_edit', $self_edit, $pp->schema());

            $alert_service->success('Volledige naam configuratie aangepast');
            $link_render->redirect('users_full_name', $pp->ary(), []);
        }

        return $this->render('users/users_full_name.html.twig', [
            'form'          => $form->createView(),
        ]);
    }
}
