<?php declare(strict_types=1);

namespace App\Controller\Users;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use App\Cnst\StatusCnst;
use App\Command\Users\UsersDelCommand;
use App\Form\Post\DelVerifyType;
use App\Render\AccountRender;
use App\Render\LinkRender;
use App\Repository\MessageRepository;
use App\Repository\TransactionRepository;
use App\Repository\UserRepository;
use App\Service\AlertService;
use App\Service\IntersystemsService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\TypeaheadService;
use App\Service\VarRouteService;

class UsersDelAdminController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $id,
        UserRepository $user_repository,
        TransactionRepository $transaction_repository,
        MessageRepository $message_repository,
        AlertService $alert_service,
        AccountRender $account_render,
        LinkRender $link_render,
        IntersystemsService $intersystems_service,
        TypeaheadService $typeahead_service,
        PageParamsService $pp,
        SessionUserService $su,
        VarRouteService $vr,
        MenuService $menu_service
    ):Response
    {
        if ($su->id() === $id)
        {
            throw new AccessDeniedHttpException('Action not allowed for own user account');
        }

        $user = $user_repository->get($id, $pp->schema());

        $transaction_count = $transaction_repository->get_count_for_user_id($id, $pp->schema());

        if ($transaction_count)
        {
            throw new AccessDeniedHttpException('Action not allowed for user account with transactions.');
        }

        $users_del_command = new UsersDelCommand();

        $form = $this->createForm(DelVerifyType::class,
                $users_del_command)
            ->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $alert_trans_ary = [
                '%user%'    => $account_render->str($id, $pp->schema()),
            ];

            if ($user_repository->del($id, $pp->schema()))
            {
                $message_repository->del_for_user_id($id, $pp->schema());

                $typeahead_service->clear(TypeaheadService::GROUP_ACCOUNTS);
                $typeahead_service->clear(TypeaheadService::GROUP_USERS);
                $intersystems_service->clear_cache();

                $status = StatusCnst::THUMBPRINT_ARY[$user['status']];

                $alert_service->success('users_del.success', $alert_trans_ary);
                $link_render->redirect($vr->get('users'), $pp->ary(), ['status' => $status]);
            }

            $alert_service->error('users_del.error', $alert_trans_ary);
        }

        $menu_service->set('users');

        return $this->render('users/users_del.html.twig', [
            'form'          => $form->createView(),
            'user'          => $user,
            'schema'        => $pp->schema(),
        ]);
    }
}
