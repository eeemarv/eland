<?php declare(strict_types=1);

namespace App\Controller\Messages;

use App\Command\Messages\MessagesCleanupCommand;
use App\Form\Post\Messages\MessagesCleanupType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class MessagesCleanupController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/messages/cleanup',
        name: 'messages_cleanup',
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
        ConfigService $config_service,
        PageParamsService $pp
    ):Response
    {
        if (!$config_service->get_bool('messages.fields.expires_at.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Messages cleanup submodule not enabled.');
        }

        if (!$config_service->get_bool('messages.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Messages (offers/wants) module not enabled.');
        }

        $command = new MessagesCleanupCommand();
        $config_service->load_command($command, $pp->schema());

        $form = $this->createForm(MessagesCleanupType::class, $command);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();
            $config_service->store_command($command, $pp->schema());

            $alert_service->success('Geldigheid en opruiming instellingen van vraag en aanbod aangepast');
            return $this->redirectToRoute('messages_cleanup', $pp->ary());
        }

        return $this->render('messages/messages_cleanup.html.twig', [
            'form'      => $form->createView(),
        ]);
    }
}
