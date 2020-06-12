<?php declare(strict_types=1);

namespace App\Controller\Messages;

use App\Command\Messages\MessagesAddCommand;
use App\Form\Post\Messages\MessagesType;
use App\Render\AccountRender;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Render\LinkRender;
use App\Repository\MessageRepository;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;

class MessagesAddController extends AbstractController
{
    public function __invoke(
        Request $request,
        MessageRepository $message_repository,
        AlertService $alert_service,
        LinkRender $link_render,
        AccountRender $account_render,
        MenuService $menu_service,
        ConfigService $config_service,
        PageParamsService $pp,
        SessionUserService $su
    ):Response
    {
        $messages_add_command = new MessagesAddCommand();

        if ($pp->is_admin())
        {
            $messages_add_command->user_id = $su->id();
        }

        $validity_days = (int) $config_service->get('msgs_days_default', $pp->schema());

        if ($validity_days)
        {
            $expires_at_unix = time() + ((int) $validity_days * 86400);
            $expires_at =  gmdate('Y-m-d H:i:s', $expires_at_unix);
            $messages_add_command->expires_at = $expires_at;
        }

        $form = $this->createForm(MessagesType::class,
                $messages_add_command)
            ->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $messages_add_command = $form->getData();

            $user_id = $pp->is_admin() ? $messages_add_command->user_id : $su->id();

            $is_offer = $messages_add_command->offer_want === 'offer';
            $subject = $messages_add_command->subject;

            $message = [
                'is_offer'      => $is_offer ? 't' : 'f',
                'is_want'       => $is_offer ? 'f' : 't',
                'subject'       => $subject,
                'content'       => $messages_add_command->content,
                'category_id'   => $messages_add_command->category_id,
                'expires_at'    => $messages_add_command->expires_at,
                'amount'        => $messages_add_command->amount,
                'units'         => $messages_add_command->units,
                'image_files'   => $messages_add_command->image_files,
                'access'        => $messages_add_command->access,
                'user_id'       => $user_id,
                'created_by'    => $su->id(),
            ];

            $id = $message_repository->insert($message, $pp->schema());

            $alert_trans_key = 'messages_add.success.';
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

        return $this->render('messages/messages_add.html.twig', [
            'form'      => $form->createView(),
            'schema'    => $pp->schema(),
        ]);
    }
}
