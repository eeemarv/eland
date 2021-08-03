<?php declare(strict_types=1);

namespace App\Controller\Messages;

use App\Command\Messages\MessagesModulesCommand;
use App\Form\Post\Messages\MessagesModulesType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class MessagesModulesController extends AbstractController
{
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
        ConfigService $config_service,
        PageParamsService $pp
    ):Response
    {
        if (!$config_service->get_bool('messages.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Messages (offers/wants) module not enabled.');
        }

        $command = new MessagesModulesCommand();
        $config_service->load_command($command, $pp->schema());

        $form = $this->createForm(MessagesModulesType::class, $command);

        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();
            $config_service->store_command($command, $pp->schema());

            $alert_service->success('Submodules/velden vraag en aanbod aangepast');

            return $this->redirectToRoute('messages_modules', $pp->ary());
        }

        return $this->render('messages/messages_modules.html.twig', [
            'form'          => $form->createView(),
        ]);
    }
}
