<?php declare(strict_types=1);

namespace App\Controller\Config;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\MenuService;
use App\Render\LinkRender;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Routing\Annotation\Route;

class ConfigMailAddrController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/config/mail-addr',
        name: 'config_mail_addr',
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
        MenuService $menu_service,
        LinkRender $link_render,
        ConfigService $config_service,
        PageParamsService $pp
    ):Response
    {
        $admin = $config_service->get_ary('mail.addresses.admin', $pp->schema());
        $support = $config_service->get_ary('mail.addresses.support', $pp->schema());

        $form_data = [
            'admin'         => $admin,
            'support'       => $support,
        ];

        $builder = $this->createFormBuilder($form_data);
        $builder->add('admin', CollectionType::class, [
                'entry_type'        => EmailType::class,
                'allow_add'         => true,
                'allow_delete'      => true,
                'delete_empty'      => true,
                'prototype'         => true,
            ])
            ->add('support', CollectionType::class, [
                'entry_type'        => EmailType::class,
                'allow_add'         => true,
                'allow_delete'      => true,
                'delete_empty'      => true,
                'prototype'         => true,
            ])
            ->add('submit', SubmitType::class);

        $form = $builder->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $form_data = $form->getData();

            $admin = array_values($form_data['admin']);
            $support = array_values($form_data['support']);

            $config_service->set_ary('mail.addresses.admin', $admin, $pp->schema());
            $config_service->set_ary('mail.addresses.support', $support, $pp->schema());

            $alert_service->success('E-mail adressen aangepast.');
            $link_render->redirect('config_mail_addr', $pp->ary(), []);
        }

        $menu_service->set('config_name');

        return $this->render('config/config_mail_addr.html.twig', [
            'form'          => $form->createView(),
            'schema'        => $pp->schema(),
        ]);
    }
}
