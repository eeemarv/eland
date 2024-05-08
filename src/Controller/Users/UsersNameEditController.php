<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Cache\ConfigCache;
use App\Command\Users\UsersNameCommand;
use App\Form\Type\Users\UsersNameType;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\PageParamsService;
use App\Service\ResponseCacheService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class UsersNameEditController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/users/{id}/name/edit',
        name: 'users_name_edit',
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
        '/{system}/{role_short}/users/self/name/edit',
        name: 'users_name_edit_self',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.user%',
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
        '/{system}/{role_short}/users/{id}/name/add',
        name: 'users_name_add',
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
        '/{system}/{role_short}/users/self/name/add',
        name: 'users_name_add_self',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.user%',
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
        ResponseCacheService $response_cache_service,
        UserRepository $user_repository,
        AlertService $alert_service,
        ConfigCache $config_cache,
        PageParamsService $pp,
        SessionUserService $su
    ):Response
    {
        if (!$is_self
            && $su->is_owner($id))
        {
            return $this->redirectToRoute('users_account_edit_self', $pp->ary());
        }

        if ($is_self)
        {
            $id = $su->id();
        }

        if (!$pp->is_admin()
            && !$config_cache->get_bool('users.fields.username.self_edit', $pp->schema()))
        {
            throw new AccessDeniedHttpException('Changing own username not accepted by configuration.');
        }

        $user = $user_repository->get($id, $pp->schema());

        $name_set_previously = false;

        if (isset($user['name']) && $user['name'] !== '')
        {
            $name_set_previously = true;
        }

        if ($name_set_previously)
        {
            if ($mode === 'add')
            {
                throw new AccessDeniedHttpException('Wrong route: user name already exists (use edit route instead)');
            }
        }
        else
        {
            if ($mode === 'edit')
            {
                throw new AccessDeniedHttpException('Wrong route: can not edit non-existing user name (use add route instead)');
            }
        }

        $form_options = [];
        $command = new UsersNameCommand();

        $is_intersystem = isset($user['remote_schema']) || isset($user['remote_email']);

        $command->id = $id;
        $command->name = $user['name'];

        if ($name_set_previously)
        {
            $form_options['render_omit'] = $command->name;
        }

        $form = $this->createForm(UsersNameType::class, $command, $form_options);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();

            if ($command->name !== $user['name'])
            {
                $user_repository->update([
                    'name'    => $command->name,
                ], $id, $pp->schema());

                $response_cache_service->clear_cache($pp->schema());

                if ($name_set_previously)
                {
                    $alert_service->success('De gebruikersnaam is aangepast van ' . $user['name'] . ' naar ' . $command->name);
                }
                else
                {
                    $alert_service->success('De gebruikersnaam "' . $command->name . '" is ingesteld');
                }

            }
            else
            {
                if ($name_set_previously)
                {
                    $alert_service->warning('Gebruikersnaam ' . $user['name'] . ' niet gewijzigd');
                }
                else
                {
                    $alert_service->warning('Gebruikersnaam niet ingesteld');
                }
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

        return $this->render('users/users_name_edit.html.twig', [
            'form'              => $form->createView(),
            'user'              => $user,
            'id'                => $id,
            'is_self'           => $is_self,
            'is_intersystem'    => $is_intersystem,
            'mode'              => $mode,
        ]);
    }
}
