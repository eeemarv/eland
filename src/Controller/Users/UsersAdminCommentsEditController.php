<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Command\Users\UsersAdminCommentsCommand;
use App\Form\Type\Users\UsersAdminCommentsType;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class UsersAdminCommentsEditController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/users/{id}/admin-comments/edit',
        name: 'users_admin_comments_edit',
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
        '/{system}/{role_short}/users/self/admin-comments/edit',
        name: 'users_admin_comments_edit_self',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'is_self'       => true,
            'id'            => 0,
            'module'        => 'users',
        ],
    )]

    public function __invoke(
        Request $request,
        int $id,
        bool $is_self,
        UserRepository $user_repository,
        AlertService $alert_service,
        ConfigService $config_service,
        PageParamsService $pp,
        SessionUserService $su
    ):Response
    {
        if (!$config_service->get_bool('users.fields.admin_comments.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Admin comments submodule not enabled.');
        }

        if (!$is_self
            && $su->is_owner($id))
        {
            return $this->redirectToRoute('users_admin_comments_edit_self', $pp->ary());
        }

        if ($is_self)
        {
            $id = $su->id();
        }

        $command = new UsersAdminCommentsCommand();

        $user = $user_repository->get($id, $pp->schema());
        $is_intersystem = isset($user['remote_schema']) || isset($user['remote_email']);
        $command->admin_comments = $user['admin_comments'];

        $form = $this->createForm(UsersAdminCommentsType::class, $command);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();

            if ($command->admin_comments === $user['admin_comments'])
            {
                $alert_service->warning('Admin commentaar niet gewijzigd');
            }
            else
            {
                $user_repository->update([
                    'admin_comments'    => $command->admin_comments,
                ], $id, $pp->schema());

                $alert_service->success('Admin commentaar aangepast');
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

        return $this->render('users/users_admin_comments_edit.html.twig', [
            'form'              => $form->createView(),
            'is_self'           => $is_self,
            'user'              => $user,
            'id'                => $id,
            'is_intersystem'    => $is_intersystem,
        ]);
    }
}
