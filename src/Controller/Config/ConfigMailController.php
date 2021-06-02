<?php declare(strict_types=1);

namespace App\Controller\Config;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\MenuService;
use App\Service\FormTokenService;
use App\Render\LinkRender;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Routing\Annotation\Route;

class ConfigMailController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/config/mail',
        name: 'config_mail',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'module'        => 'config',
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

        $mail_enabled = $config_service->get_bool('mail.enabled', $pp->schema());
        $mail_tag = $config_service->get_str('mail.tag', $pp->schema());

        $form_data = [
            'mail_enabled'  => $mail_enabled,
            'mail_tag'      => $mail_tag,
        ];

        $builder = $this->createFormBuilder($form_data);
        $builder->add('mail_enabled', CheckboxType::class)
            ->add('mail_tag', TextType::class)
            ->add('submit', SubmitType::class);

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

            $config_service->set_bool('mail.enabled', $form_data['mail_enabled'], $pp->schema());
            $config_service->set_str('mail.tag', $form_data['mail_tag'], $pp->schema());

            $alert_service->success('E-mail instellingen aangepast.');
            $link_render->redirect('config_mail', $pp->ary(), []);
        }

        $menu_service->set('config_name');

        return $this->render('config/config_mail.html.twig', [
            'form'          => $form->createView(),
            'form_token'    => $form_token_service->get(),
            'schema'        => $pp->schema(),
        ]);
    }
}
