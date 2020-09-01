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
use App\Service\ConfigService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;

class MessagesEditController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $id,
        MessageRepository $message_repository,
        ConfigService $config_service,
        AlertService $alert_service,
        LinkRender $link_render,
        AccountRender $account_render,
        MenuService $menu_service,
        PageParamsService $pp,
        SessionUserService $su
    ):Response
    {
        $category_enabled = $config_service->get_bool('messages.fields.category.enabled', $pp->schema());
        $expires_at_enabled = $config_service->get_bool('messages.fields.expires_at.enabled', $pp->schema());
        $units_enabled = $config_service->get_bool('messages.fields.units.enabled', $pp->schema());

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

        $validation_groups = ['common'];

        if ($pp->is_admin())
        {
            $validation_groups[] = 'user_id';
        }

        if ($category_enabled)
        {
            $validation_groups[] = 'category_id';
        }

        if ($expires_at_enabled)
        {
            $validation_groups[] = 'expires_at';
        }

        if ($units_enabled)
        {
            $validation_groups[] = 'units';
        }

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
                'image_files'   => $messages_command->image_files,
                'access'        => $messages_command->access,
                'user_id'       => $user_id,
            ];

            if ($category_enabled)
            {
                $message['category_id'] = $messages_command->category_id;
            }

            if ($expires_at_enabled)
            {
                $message['expires_at'] = $messages_command->expires_at;
            }

            if ($units_enabled)
            {
                $message['amount'] = $messages_command->amount;
                $message['units'] = $messages_command->units;
            }

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
