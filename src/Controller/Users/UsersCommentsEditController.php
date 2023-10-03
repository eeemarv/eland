<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Command\Users\UsersCommentsCommand;
use App\Form\Type\Users\UsersCommentsType;
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
class UsersCommentsEditController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/users/{id}/comments/edit',
        name: 'users_comments_edit',
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
        '/{system}/{role_short}/users/self/comments/edit',
        name: 'users_comments_edit_self',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.user%',
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
        AlertService $alert_service,
        ConfigService $config_service,
        PageParamsService $pp,
        SessionUserService $su
    ):Response
    {
        if (!$config_service->get_bool('users.fields.comments.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Users comments submodule not enabled.');
        }

        if (!$is_self
            && $su->is_owner($id))
        {
            return $this->redirectToRoute('users_comments_edit_self', $pp->ary());
        }

        if ($is_self)
        {
            $id = $su->id();
        }

        $command = new UsersCommentsCommand();

        $user = $user_repository->get($id, $pp->schema());
        $is_intersystem = isset($user['remote_schema']) || isset($user['remote_email']);
        $command->comments = $user['comments'];

        $form = $this->createForm(UsersCommentsType::class, $command);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();

            if ($command->comments === $user['comments'])
            {
                $alert_service->warning('Commentaar niet gewijzigd');
            }
            else
            {
                $user_repository->update([
                    'comments'    => $command->comments,
                ], $id, $pp->schema());

                $alert_service->success('Commentaar aangepast');
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

        return $this->render('users/users_comments_edit.html.twig', [
            'form'              => $form->createView(),
            'user'              => $user,
            'id'                => $id,
            'is_self'           => $is_self,
            'is_intersystem'    => $is_intersystem,
        ]);
    }
}
