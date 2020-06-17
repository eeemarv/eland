<?php declare(strict_types=1);

namespace App\Controller\Messages;

use App\Command\Messages\MessagesCommand;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use App\Form\Post\Messages\MessagesType;
use App\Render\AccountRender;
use App\Render\LinkRender;
use App\Repository\MessageRepository;
use App\Service\AlertService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;

class MessagesEditController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $id,
        MessageRepository $message_repository,
        AlertService $alert_service,
        LinkRender $link_render,
        AccountRender $account_render,
        MenuService $menu_service,
        PageParamsService $pp,
        SessionUserService $su
    ):Response
    {
        $message = $message_repository->get($id, $pp->schema());

        if (!($pp->is_admin() || $su->is_owner($message['user_id'])))
        {
            throw new AccessDeniedHttpException('Access Denied for edit message with id ' . $id);
        }

        $messages_command = new MessagesCommand();

        if ($pp->is_admin())
        {
            $messages_command->user_id = $message['user_id'];
        }

        $messages_command->offer_want = $message['is_offer'] ? 'offer' : 'want';
        $messages_command->subject = $message['subject'];
        $messages_command->content = $message['content'];
        $messages_command->category_id = $message['category_id'];
        $messages_command->expires_at = $message['expires_at'];
        $messages_command->amount = $message['amount'];
        $messages_command->units = $message['units'];
        $messages_command->image_files = $message['image_files'];
        $messages_command->access = $message['access'];

        $validation_groups = $pp->is_admin() ? ['user', 'admin'] : ['user'];

        $form = $this->createForm(MessagesType::class,
                $messages_command, ['validation_groups' => $validation_groups])
            ->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $messages_command = $form->getData();

            $user_id = $pp->is_admin() ? $messages_command->user_id : $su->id();

            $is_offer = $messages_command->offer_want === 'offer';
            $subject = $messages_command->subject;

            $message = [
                'is_offer'      => $is_offer ? 't' : 'f',
                'is_want'       => $is_offer ? 'f' : 't',
                'subject'       => $subject,
                'content'       => $messages_command->content,
                'category_id'   => $messages_command->category_id,
                'expires_at'    => $messages_command->expires_at,
                'amount'        => $messages_command->amount,
                'units'         => $messages_command->units,
                'image_files'   => $messages_command->image_files,
                'access'        => $messages_command->access,
                'user_id'       => $user_id,
            ];

            $message_repository->update($message, $id, $pp->schema());

            $alert_trans_key = 'messages_edit.success.';
            $alert_trans_key .= $is_offer ? 'offer.' : 'want.';
            $alert_trans_key .= $su->is_owner($user_id) ? 'personal' : 'admin';

            $alert_service->success($alert_trans_key, [
                '%message_subject%' => $subject,
                '%user%'            => $account_render->get_str($user_id, $pp->schema()),
            ]);

            $link_render->redirect('messages_show', $pp->ary(),
                ['id' => $id]);
        }

        $menu_service->set('messages');

        return $this->render('messages/messages_edit.html.twig', [
            'form'      => $form->createView(),
            'message'   => $message,
            'schema'    => $pp->schema(),
        ]);
    }
}
