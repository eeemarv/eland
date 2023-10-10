<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Command\Users\UsersAccountLimitsCommand;
use App\Form\Type\Users\UsersAccountLimitsType;
use App\Repository\AccountRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class UsersAccountLimitsEditController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/users/{id}/account-limits/edit',
        name: 'users_account_limits_edit',
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
        '/{system}/{role_short}/users/self/account-limits/edit',
        name: 'users_account_limits_edit_self',
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
        AlertService $alert_service,
        ConfigService $config_service,
        PageParamsService $pp,
        SessionUserService $su
    ):Response
    {
        if (!$config_service->get_bool('transactions.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Users account edit not possible: transactions module not enabled.');
        }

        if (!$config_service->get_bool('accounts.limits.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Limits on transaction accounts are not enabled in the configuration.');
        }

        if (!$is_self
            && $su->is_owner($id))
        {
            return $this->redirectToRoute('users_account_limits_edit_self', $pp->ary());
        }

        if ($is_self)
        {
            $id = $su->id();
        }

        $user = $user_repository->get($id, $pp->schema());

        if (!isset($user['code']) || $user['code'] === '')
        {
            throw new AccessDeniedHttpException('No account code set for this user, limits can not be edited.');
        }

        $currency = $config_service->get_str('transactions.currency.name', $pp->schema());

        $command = new UsersAccountLimitsCommand();

        $is_intersystem = isset($user['remote_schema']) || isset($user['remote_email']);

        $min_limit = $account_repository->get_min_limit($id, $pp->schema());
        $max_limit = $account_repository->get_max_limit($id, $pp->schema());

        $command->min_limit = $min_limit;
        $command->max_limit = $max_limit;

        $form = $this->createForm(UsersAccountLimitsType::class, $command);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();

            $alert_success_ary = [];

            if ($command->min_limit !== $min_limit
            )
            {
                $account_repository->update_min_limit(
                    account_id: $id,
                    min_limit: $command->min_limit,
                    created_by: $su->id(),
                    schema: $pp->schema()
                );

                if ($command->min_limit === null)
                {
                    $alert_success_ary[] = 'De minimum limiet van het account is gewist';
                }
                else if ($min_limit === null)
                {
                    $alert_success_ary[] = 'De minimum limiet is ingesteld op ' . $command->min_limit . ' ' . $currency;
                }
                else
                {
                    $alert_success_ary[] = 'De minimum limiet is aangepast van ' . $min_limit . ' ' . $currency . ' naar ' . $command->min_limit . ' ' . $currency;
                }
            }

            if ($command->max_limit !== $max_limit
            )
            {
                $account_repository->update_max_limit(
                    account_id: $id,
                    max_limit: $command->max_limit,
                    created_by: $su->id(),
                    schema: $pp->schema()
                );

                if ($command->max_limit === null)
                {
                    $alert_success_ary[] = 'De maximum limiet van het account is gewist';
                }
                else if ($max_limit === null)
                {
                    $alert_success_ary[] = 'De maximum limiet is ingesteld op ' . $command->max_limit . ' ' . $currency;
                }
                else
                {
                    $alert_success_ary[] = 'De maximum limiet is aangepast van ' . $max_limit . ' ' . $currency . ' naar ' . $command->max_limit . ' ' . $currency;
                }
            }

            if (count($alert_success_ary))
            {
                $alert_service->success($alert_success_ary);
            }
            else
            {
                $alert_service->warning('Account limieten van ' . $user['code'] . ' niet gewijzigd');
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

        return $this->render('users/users_account_limits_edit.html.twig', [
            'form'              => $form->createView(),
            'user'              => $user,
            'id'                => $id,
            'is_self'           => $is_self,
            'is_intersystem'    => $is_intersystem,
        ]);
    }
}
