<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Cache\ConfigCache;
use App\Cache\ResponseCache;
use App\Command\Users\UsersAccountLeavingCommand;
use App\Form\Type\Users\UsersAccountLeavingType;
use App\Repository\AccountRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class UsersAccountLeavingEditController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/users/{id}/account-leaving/edit',
        name: 'users_account_leaving_edit',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
            'id'            => '%assert.id%',
        ],
        defaults: [
            'is_self'       => false,
            'module'        => 'users',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/users/self/account-leaving/edit',
        name: 'users_account_leaving_edit_self',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
            'id'            => '%assert.id%',
        ],
        defaults: [
            'id'            => 0,
            'is_self'       => true,
            'module'        => 'users',
        ],
    )]

    public function __invoke(
        Request $request,
        int $id,
        bool $is_self,
        UserRepository $user_repository,
        AccountRepository $account_repository,
        ResponseCache $response_cache,
        AlertService $alert_service,
        ConfigCache $config_cache,
        PageParamsService $pp,
        SessionUserService $su
    ):Response
    {
        if (!$config_cache->get_bool('transactions.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Users account edit not possible: transactions module not enabled.');
        }

        if (!$config_cache->get_bool('users.leaving.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('"Leaving" functionality not enabled in the configuration.');
        }

        if (!$is_self
            && $su->is_owner($id))
        {
            return $this->redirectToRoute('users_account_leaving_edit_self', $pp->ary());
        }

        if ($is_self)
        {
            $id = $su->id();
        }

        $user = $user_repository->get($id, $pp->schema());

        if (!isset($user['code']) || $user['code'] === '')
        {
            throw new AccessDeniedHttpException('No account code set for this user, leaving status can not be edited.');
        }

        $code = $user['code'];
        $is_leaving = $user['is_leaving'];

        $balance = $account_repository->get_balance($id, $pp->schema());

        $command = new UsersAccountLeavingCommand();

        $is_intersystem = isset($user['remote_schema']) || isset($user['remote_email']);

        $command->is_leaving = $is_leaving;

        $form = $this->createForm(UsersAccountLeavingType::class, $command);

        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();

            if ($command->is_leaving === $is_leaving)
            {
                if ($command->is_leaving)
                {
                    $alert_service->warning('Account ' . $code . ' behoudt "uitstapper" status, geen wijziging');
                }
                else
                {
                    $alert_service->warning('Account ' . $code . ' krijgt niet de "uitstapper" status, geen wijziging');
                }
            }
            else
            {
                if ($command->is_leaving)
                {
                    $alert_service->success('Account ' . $code . ' heeft nu de "uitstapper" status');
                }
                else
                {
                    $alert_service->success('De "uitstapper" status is verwijderd van account ' . $code);
                }

                $pg_is_leaving = $command->is_leaving ? 't' : 'f';

                $user_repository->update([
                    'is_leaving'    => $pg_is_leaving,
                ], $id, $pp->schema());

                $response_cache->clear_cache($pp->schema());
            }

            if ($is_self)
            {
                return $this->redirectToRoute('users_show_self', $pp->ary());
            }

            return $this->redirectToRoute('users_show', [
                ... $pp->ary(),
                'id' => $id,
            ]);
        }

        return $this->render('users/users_account_leaving_edit.html.twig', [
            'form'              => $form->createView(),
            'user'              => $user,
            'id'                => $id,
            'is_self'           => $is_self,
            'is_intersystem'    => $is_intersystem,
            'balance'           => $balance,
        ]);
    }
}
