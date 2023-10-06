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
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class UsersRoleEditController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/users/{id}/account/edit',
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
        ConfigService $config_service,
        PageParamsService $pp,
        SessionUserService $su
    ):Response
    {
        if ($su->is_owner($id))
        {
            throw new AccessDeniedHttpException('You can\'t edit your own role');
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

            return $this->redirectToRoute('users_show', [
                ... $pp->ary(),
                'id' => $id,
            ]);
        }

        return $this->render('users/users_comments_edit.html.twig', [
            'form'              => $form->createView(),
            'user'              => $user,
            'id'                => $id,
            'is_self'           => false,
            'is_intersystem'    => $is_intersystem,
        ]);
    }
}
