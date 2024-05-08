<?php declare(strict_types=1);

namespace App\Controller\Messages;

use App\Cache\ConfigCache;
use App\Command\Messages\MessagesContentCommand;
use App\Form\Type\Messages\MessagesContentEditType;
use App\Repository\MessageRepository;
use App\Service\AlertService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class MessagesContentEditController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/messages/{id}/content/edit',
        name: 'messages_content_edit',
        methods: ['GET', 'POST'],
        requirements: [
            'id'            => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.user%',
        ],
        defaults: [
            'module'        => 'messages',
        ],
    )]

    public function __invoke(
        Request $request,
        int $id,
        AlertService $alert_service,
        MessageRepository $message_repository,
        ConfigCache $config_cache,
        #[Autowire(service: 'html_sanitizer.sanitizer.user_post_sanitizer')] HtmlSanitizerInterface $html_sanitizer,
        PageParamsService $pp,
        SessionUserService $su
    ):Response
    {
        if (!$config_cache->get_bool('messages.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Messages module not enabled in configuration');
        }

        $message = $message_repository->get($id, $pp->schema());

        $user_id = $message['user_id'];

        if (!$pp->is_admin() && !$su->is_owner($user_id))
        {
            throw new AccessDeniedHttpException('You are not allowed to modify this message');
        }

        $command = new MessagesContentCommand();

        $command->content = $message['content'];
        $content = $message['content'];

        $form = $this->createForm(MessagesContentEditType::class, $command);

        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();

            $content_sanitized = $html_sanitizer->sanitize($command->content);

            if ($content === $content_sanitized)
            {
                $alert_service->warning('Omschrijvng niet gewijzigd');
            }
            else
            {
                $update_ary = [
                    'content'   => $content_sanitized,
                ];

                $message_repository->update($update_ary, $id, $pp->schema());

                $alert_service->success('Omschrijvng aangepast');
            }

            return $this->redirectToRoute('messages_show', [
                ...$pp->ary(),
                'id'    => $id,
            ]);

        }

        return $this->render('messages/messages_content_edit.html.twig', [
            'form'      => $form->createView(),
            'message'   => $message,
            'user_id'   => $user_id,
        ]);
    }
}
