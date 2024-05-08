<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Cache\ConfigCache;
use App\Cache\ResponseCache;
use App\Command\Users\UsersAccountCommand;
use App\Form\Type\Users\UsersAccountType;
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
class UsersAccountEditController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/users/{id}/account/edit',
        name: 'users_account_edit',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
            'id'            => '%assert.id%',
        ],
        defaults: [
            'is_self'       => false,
            'mode'          => 'edit',
            'module'        => 'users',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/users/self/account/edit',
        name: 'users_account_edit_self',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
            'id'            => '%assert.id%',
        ],
        defaults: [
            'id'            => 0,
            'is_self'       => true,
            'mode'          => 'edit',
            'module'        => 'users',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/users/{id}/account/add',
        name: 'users_account_add',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
            'id'            => '%assert.id%',
        ],
        defaults: [
            'is_self'       => false,
            'mode'          => 'add',
            'module'        => 'users',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/users/self/account/add',
        name: 'users_account_add_self',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
            'id'            => '%assert.id%',
        ],
        defaults: [
            'id'            => 0,
            'is_self'       => true,
            'mode'          => 'add',
            'module'        => 'users',
        ],
    )]

    public function __invoke(
        Request $request,
        int $id,
        bool $is_self,
        string $mode,
        ResponseCache $response_cache,
        UserRepository $user_repository,
        AccountRepository $account_repository,
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

        if (!$is_self
            && $su->is_owner($id))
        {
            return $this->redirectToRoute('users_account_edit_self', $pp->ary());
        }

        if ($is_self)
        {
            $id = $su->id();
        }

        $user = $user_repository->get($id, $pp->schema());

        $code_not_set_previously = !isset($user['code']) || $user['code'] === '';

        if ($code_not_set_previously)
        {
            if ($mode === 'edit')
            {
                throw new AccessDeniedHttpException('Wrong route: can not edit non-existing account (use add route instead)');
            }
        }
        else
        {
            if ($mode === 'add')
            {
                throw new AccessDeniedHttpException('Wrong route: account already exists (use edit route instead)');
            }
        }

        $currency = $config_cache->get_str('transactions.currency.name', $pp->schema());
        $limits_enabled = $config_cache->get_bool('accounts.limits.enabled', $pp->schema());

        $form_options = [];
        $command = new UsersAccountCommand();

        $is_intersystem = isset($user['remote_schema']) || isset($user['remote_email']);

        $command->user_id = $id;
        $command->code = $user['code'];

        if ($limits_enabled)
        {
            $min_limit = $account_repository->get_min_limit($id, $pp->schema());
            $max_limit = $account_repository->get_max_limit($id, $pp->schema());

            $command->min_limit = $min_limit;
            $command->max_limit = $max_limit;
        }

        if (!empty($command->code))
        {
            $form_options['render_omit'] = $command->code;
        }

        $form = $this->createForm(UsersAccountType::class, $command, $form_options);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();

            $alert_success_ary = [];

            if ($command->code !== $user['code'])
            {
                $user_repository->update([
                    'code'    => $command->code,
                ], $id, $pp->schema());

                if ($code_not_set_previously)
                {
                    $alert_success_ary[] = 'Het transactie-account is aangemaakt met code ' . $command->code;
                }
                else
                {
                    $alert_success_ary[] = 'De account code is aangepast van ' . $user['code'] . ' naar ' . $command->code;
                }
            }

            if ($limits_enabled
                && $command->min_limit !== $min_limit
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

            if ($limits_enabled
                && $command->max_limit !== $max_limit
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
                $response_cache->clear_cache($pp->schema());
                $alert_service->success($alert_success_ary);
            }
            else
            {
                $alert_service->warning('Account ' . $user['code'] . ' niet gewijzigd');
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

        return $this->render('users/users_account_edit.html.twig', [
            'form'              => $form->createView(),
            'user'              => $user,
            'id'                => $id,
            'is_self'           => $is_self,
            'mode'              => $mode,
            'is_intersystem'    => $is_intersystem,
        ]);
    }
}
