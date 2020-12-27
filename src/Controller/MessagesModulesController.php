<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\FormTokenService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MessagesModulesController extends AbstractController
{
    const MESSAGES_MODULES = [
        'messages.fields.category.enabled',
        'messages.fields.expires_at.enabled',
        'messages.fields.units.enabled',
    ];

    public function __invoke(
        Request $request,
        AlertService $alert_service,
        HeadingRender $heading_render,
        FormTokenService $form_token_service,
        MenuService $menu_service,
        LinkRender $link_render,
        ConfigService $config_service,
        PageParamsService $pp
    ):Response
    {
        if (!$config_service->get_bool('messages.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Messages (offers/wants) module not enabled.');
        }

        $errors = [];
        $form_data = [];

        foreach (self::MESSAGES_MODULES as $key)
        {
            $name = strtr($key, '.', '_');
            $form_data[$name] = $config_service->get_bool($key, $pp->schema());
        }

        $builder = $this->createFormBuilder($form_data);

        foreach (self::MESSAGES_MODULES as $key)
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

            foreach (self::MESSAGES_MODULES as $key)
            {
                $name = strtr($key, '.', '_');
                $config_service->set_bool($key, $form_data[$name], $pp->schema());
            }

            $alert_service->success('Submodules/velden vraag en aanbod aangepast');
            $link_render->redirect('messages_modules', $pp->ary(), []);
        }

        $heading_render->fa('newspaper-o');
        $heading_render->add('Submodules en velden vraag en aanbod');
        $menu_service->set('messages_modules');

        return $this->render('messages/messages_modules.html.twig', [
            'form'          => $form->createView(),
            'form_token'    => $form_token_service->get(),
            'schema'        => $pp->schema(),
        ]);
    }
}
