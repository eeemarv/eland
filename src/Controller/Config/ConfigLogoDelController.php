<?php declare(strict_types=1);

namespace App\Controller\Config;

use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\FormTokenService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Annotation\Route;

class ConfigLogoDelController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/logo/del',
        name: 'config_logo_del',
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
        ConfigService $config_service,
        FormTokenService $form_token_service,
        AlertService $alert_service,
        LinkRender $link_render,
        MenuService $menu_service,
        PageParamsService $pp
    ):Response
    {
        $errors = [];

        $logo = $config_service->get_str('system.logo', $pp->schema());

        if (!$logo)
        {
            throw new ConflictHttpException('No logo is configured for this system.');
        }

        $builder = $this->createFormBuilder();
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
            $config_service->set_str('system.logo', '', $pp->schema());

            $alert_service->success('Het logo is verwijderd.');
            $link_render->redirect('config_logo', $pp->ary(), []);
        }

        $menu_service->set('config_name');

        return $this->render('config/config_logo_del.html.twig', [
            'form'          => $form->createView(),
            'form_token'    => $form_token_service->get(),
            'schema'        => $pp->schema(),
        ]);
    }
}
