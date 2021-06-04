<?php declare(strict_types=1);

namespace App\Controller\Users;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\FormTokenService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Routing\Annotation\Route;

class UsersModulesController extends AbstractController
{
    const USERS_MODULES = [
        'users.fields.full_name.enabled',
        'users.fields.postcode.enabled',
        'users.fields.birthday.enabled',
        'users.fields.hobbies.enabled',
        'users.fields.comments.enabled',
        'users.fields.admin_comments.enabled',
        'users.new.enabled',
        'users.leaving.enabled',
        'intersystem.enabled',
        'periodic_mail.enabled',
        'mollie.enabled',
    ];

    #[Route(
        '/{system}/{role_short}/users/modules',
        name: 'users_modules',
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
        FormTokenService $form_token_service,
        MenuService $menu_service,
        LinkRender $link_render,
        ConfigService $config_service,
        PageParamsService $pp
    ):Response
    {
        $errors = [];
        $form_data = [];

        foreach (self::USERS_MODULES as $key)
        {
            $name = strtr($key, '.', '_');
            $form_data[$name] = $config_service->get_bool($key, $pp->schema());
        }

        $builder = $this->createFormBuilder($form_data);

        foreach (self::USERS_MODULES as $key)
        {
            $name = strtr($key, '.', '_');
            $builder->add($name, CheckboxType::class);
        }

        $builder->add('submit', SubmitType::class);
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

            foreach (self::USERS_MODULES as $key)
            {
                $name = strtr($key, '.', '_');
                $config_service->set_bool($key, $form_data[$name], $pp->schema());
            }

            $alert_service->success('Submodules/velden leden aangepast');
            $link_render->redirect('users_modules', $pp->ary(), []);
        }

        $menu_service->set('users_modules');

        return $this->render('users/users_modules.html.twig', [
            'form'          => $form->createView(),
            'form_token'    => $form_token_service->get(),
            'schema'        => $pp->schema(),
        ]);
    }
}
