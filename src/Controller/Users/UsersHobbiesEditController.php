<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Command\Users\UsersHobbiesCommand;
use App\Form\Type\Users\UsersHobbiesType;
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
class UsersHobbiesEditController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/users/{id}/hobbies/edit',
        name: 'users_hobbies_edit',
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
        '/{system}/{role_short}/users/self/hobbies/edit',
        name: 'users_hobbies_edit_self',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.user%',
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
        if (!$config_service->get_bool('users.fields.hobbies.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Users hobbies submodule not enabled.');
        }

        if (!$is_self
            && $su->is_owner($id))
        {
            return $this->redirectToRoute('users_hobbies_edit_self', $pp->ary());
        }

        if ($is_self)
        {
            $id = $su->id();
        }

        $command = new UsersHobbiesCommand();

        $user = $user_repository->get($id, $pp->schema());
        $is_intersystem = isset($user['remote_schema']) || isset($user['remote_email']);
        $command->hobbies = $user['hobbies'];

        $form = $this->createForm(UsersHobbiesType::class, $command);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();

            if ($command->hobbies === $user['hobbies'])
            {
                $alert_service->warning('Hobbies/interesses niet gewijzigd');
            }
            else
            {
                $user_repository->update([
                    'hobbies'    => $command->hobbies,
                ], $id, $pp->schema());

                $alert_service->success('Hobbies/interesses aangepast');
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

        return $this->render('users/users_hobbies_edit.html.twig', [
            'form'              => $form->createView(),
            'user'              => $user,
            'id'                => $id,
            'is_self'           => $is_self,
            'is_intersystem'    => $is_intersystem,
        ]);
    }
}