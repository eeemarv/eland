<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Cache\ConfigCache;
use App\Command\Users\UsersRoleCommand;
use App\Form\Type\Users\UsersRoleType;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class UsersRoleEditController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/users/{id}/role/edit',
        name: 'users_role_edit',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
            'id'            => '%assert.id%',
        ],
        defaults: [
            'module'        => 'users',
        ],
    )]

    public function __invoke(
        Request $request,
        int $id,
        UserRepository $user_repository,
        AlertService $alert_service,
        ConfigCache $config_cache,
        PageParamsService $pp,
        SessionUserService $su
    ):Response
    {
        if ($su->is_owner($id))
        {
            throw new AccessDeniedHttpException('You can\'t edit your own role');
        }

        $command = new UsersRoleCommand();

        $user = $user_repository->get($id, $pp->schema());
        $is_intersystem = isset($user['remote_schema']) || isset($user['remote_email']);
        $command->role = $user['role'];

        $form = $this->createForm(UsersRoleType::class, $command);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();

            if ($command->role === $user['role'])
            {
                $alert_service->warning('Rol niet gewijzigd');
            }
            else
            {
                $user_repository->update([
                    'role'    => $command->role,
                ], $id, $pp->schema());

                $alert_service->success('Rol aangepast');
            }

            return $this->redirectToRoute('users_show', [
                ... $pp->ary(),
                'id' => $id,
            ]);
        }

        return $this->render('users/users_role_edit.html.twig', [
            'form'              => $form->createView(),
            'user'              => $user,
            'id'                => $id,
            'is_self'           => false,
            'is_intersystem'    => $is_intersystem,
        ]);
    }
}
