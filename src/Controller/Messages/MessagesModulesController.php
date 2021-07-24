<?php declare(strict_types=1);

namespace App\Controller\Messages;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class MessagesModulesController extends AbstractController
{
    const MESSAGES_MODULES = [
        'messages.fields.service_stuff.enabled',
        'messages.fields.category.enabled',
        'messages.fields.expires_at.enabled',
        'messages.fields.units.enabled',
    ];

    #[Route(
        '/{system}/{role_short}/messages/modules',
        name: 'messages_modules',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'module'        => 'messages',
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
        if (!$config_service->get_bool('messages.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Messages (offers/wants) module not enabled.');
        }

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

        if ($form->isSubmitted()
            && $form->isValid())
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

        return $this->render('messages/messages_modules.html.twig', [
            'form'          => $form->createView(),
        ]);
    }
}
