<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Command\Users\UsersPostcodeCommand;
use App\Form\Type\Users\UsersPostcodeType;
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
class UsersPostcodeEditController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/users/{id}/postcode/edit',
        name: 'users_postcode_edit',
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
        '/{system}/{role_short}/users/self/postcode/edit',
        name: 'users_postcode_edit_self',
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
        if (!$config_service->get_bool('users.fields.postcode.enabled', $pp->schema()))
        {
            throw new AccessDeniedHttpException('Users postcode submodule not enabled.');
        }

        if (!$is_self
            && $su->is_owner($id))
        {
            return $this->redirectToRoute('users_postcode_edit_self', $pp->ary());
        }

        if ($is_self)
        {
            $id = $su->id();
        }

        $command = new UsersPostcodeCommand();

        $user = $user_repository->get($id, $pp->schema());
        $is_intersystem = isset($user['remote_schema']) || isset($user['remote_email']);
        $command->postcode = $user['postcode'];

        $form = $this->createForm(UsersPostcodeType::class, $command);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();

            if ($command->postcode === $user['postcode'])
            {
                $alert_service->warning('Postcode niet gewijzigd');
            }
            else
            {
                $user_repository->update([
                    'postcode'    => $command->postcode,
                ], $id, $pp->schema());

                $alert_service->success('Postcode aangepast');
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

        return $this->render('users/users_postcode_edit.html.twig', [
            'form'              => $form->createView(),
            'user'              => $user,
            'id'                => $id,
            'is_self'           => $is_self,
            'is_intersystem'    => $is_intersystem,
        ]);
    }
}
