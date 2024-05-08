<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Cache\ConfigCache;
use App\Cache\ResponseCache;
use App\Command\Users\UsersAccountCodeCommand;
use App\Form\Type\Users\UsersAccountCodeType;
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
class UsersAccountCodeEditController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/users/{id}/account-code/edit',
        name: 'users_account_code_edit',
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
        '/{system}/{role_short}/users/self/account-code/edit',
        name: 'users_account_code_edit_self',
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
        '/{system}/{role_short}/users/{id}/account-code/add',
        name: 'users_account_code_add',
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
        '/{system}/{role_short}/users/self/account-code/add',
        name: 'users_account_code_add_self',
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

        $code_set_previously = false;

        if (isset($user['code']) && $user['code'] !== '')
        {
            $code_set_previously = true;
        }

        if ($code_set_previously)
        {
            if ($mode === 'add')
            {
                throw new AccessDeniedHttpException('Wrong route: account already exists (use edit route instead)');
            }
        }
        else
        {
            if ($mode === 'edit')
            {
                throw new AccessDeniedHttpException('Wrong route: can not edit non-existing account (use add route instead)');
            }
        }

        $form_options = [];
        $command = new UsersAccountCodeCommand();

        $is_intersystem = isset($user['remote_schema']) || isset($user['remote_email']);

        $command->user_id = $id;
        $command->code = $user['code'];

        if ($code_set_previously)
        {
            $form_options['render_omit'] = $command->code;
        }

        $form = $this->createForm(UsersAccountCodeType::class, $command, $form_options);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();

            if ($command->code !== $user['code'])
            {
                $user_repository->update([
                    'code'    => $command->code,
                ], $id, $pp->schema());

                $response_cache->clear_cache($pp->schema());

                if ($code_set_previously)
                {
                    $alert_service->success('De account code is aangepast van ' . $user['code'] . ' naar ' . $command->code);
                }
                else
                {
                    $alert_service->success('Het transactie-account is aangemaakt met code ' . $command->code);
                }

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

        return $this->render('users/users_account_code_edit.html.twig', [
            'form'              => $form->createView(),
            'user'              => $user,
            'id'                => $id,
            'is_self'           => $is_self,
            'mode'              => $mode,
            'is_intersystem'    => $is_intersystem,
        ]);
    }
}
