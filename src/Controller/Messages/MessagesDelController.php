<?php declare(strict_types=1);

namespace App\Controller\Messages;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use App\Form\Post\DelType;
use App\Render\AccountRender;
use App\Render\LinkRender;
use App\Repository\MessageRepository;
use App\Service\AlertService;
use App\Service\ItemAccessService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\VarRouteService;

class MessagesDelController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $id,
        MessageRepository $message_repository,
        AlertService $alert_service,
        LinkRender $link_render,
        AccountRender $account_render,
        ItemAccessService $item_access_service,
        PageParamsService $pp,
        SessionUserService $su,
        VarRouteService $vr,
        MenuService $menu_service
    ):Response
    {
        $message = $message_repository->get($id, $pp->schema());

        if (!$item_access_service->is_visible($message['access']))
        {
            throw new AccessDeniedHttpException('Access denied for message ' . $id);
        }

        if (!($su->is_owner($message['user_id']) || $pp->is_admin()))
        {
            throw new AccessDeniedHttpException('No Rights for this action.');
        }

        $form = $this->createForm(DelType::class)
            ->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            if ($message_repository->del($id, $pp->schema()))
            {
                $alert_trans_ary = [
                    '%message_subject%'   => $message['subject'],
                    '%user%'              => $account_render->str($message['user_id'], $pp->schema()),
                ];

                $alert_trans_key = 'messages_del.success.';
                $alert_trans_key .= $message['is_offer'] ? 'offer.' : 'want.';
                $alert_trans_key .= $su->is_owner($message['user_id']) ? 'personal' : 'admin';

                $alert_service->success($alert_trans_key, $alert_trans_ary);
                $link_render->redirect($vr->get('messages'), $pp->ary(), []);
            }

            $alert_service->error('messages_del.error');
        }

        $menu_service->set('messages');

        return $this->render('messages/messages_del.html.twig', [
            'form'      => $form->createView(),
            'message'   => $message,
            'schema'    => $pp->schema(),
        ]);
    }
}