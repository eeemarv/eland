<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Cache\ConfigCache;
use App\Command\Users\UsersFullNameCommand;
use App\Form\Type\Users\UsersFullNameType;
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
class UsersFullNameEditController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/users/{id}/full-name/edit',
        name: 'users_full_name_edit',
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
        '/{system}/{role_short}/users/self/full-name/edit',
        name: 'users_full_name_edit_self',
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
        ConfigCache $config_cache,
        PageParamsService $pp,
        SessionUserService $su
    ):Response
    {
        if (!$config_cache->get_bool('users.fields.full_name.enabled', $pp->schema()))
        {
            throw new AccessDeniedHttpException('Users full name submodule not enabled.');
        }

        if (!$is_self
            && $su->is_owner($id))
        {
            return $this->redirectToRoute('users_full_name_edit_self', $pp->ary());
        }

        if ($is_self)
        {
            $id = $su->id();
        }

        $self_edit_en = $config_cache->get_bool('users.fields.full_name.self_edit', $pp->schema());

        $form_options = [];
        $full_name_edit_en = true;

        if ($is_self && !$pp->is_admin() && !$self_edit_en)
        {
            $full_name_edit_en = false;
            $form_options['full_name_edit_en'] = false;
        }

        $command = new UsersFullNameCommand();

        $user = $user_repository->get($id, $pp->schema());
        $is_intersystem = isset($user['remote_schema']) || isset($user['remote_email']);
        $command->full_name = $user['full_name'];
        $command->full_name_access = $user['full_name_access'];

        $form = $this->createForm(UsersFullNameType::class, $command, $form_options);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();

            $post_ary = [];

            if ($full_name_edit_en)
            {
                if ($command->full_name !== $user['full_name'])
                {
                    $post_ary['full_name'] = $command->full_name;
                }
            }

            if ($command->full_name_access !== $user['full_name_access'])
            {
                $post_ary['full_name_access'] = $command->full_name_access;
            }

            if (count($post_ary))
            {
                $user_repository->update($post_ary, $id, $pp->schema());

                if ($full_name_edit_en)
                {
                    $alert_service->success('Volledige naam data aangepast');
                }
                else
                {
                    $alert_service->success('Zichtbaarheid volledige naam aangepast');
                }
            }
            else
            {
                if ($full_name_edit_en)
                {
                    $alert_service->warning('Volledige naam data niet gewijzigd');
                }
                else
                {
                    $alert_service->warning('Zichtbaarheid volledige naam niet gewijzigd');
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

        return $this->render('users/users_full_name_edit.html.twig', [
            'form'              => $form->createView(),
            'user'              => $user,
            'id'                => $id,
            'full_name_edit_en' => $full_name_edit_en,
            'is_self'           => $is_self,
            'is_intersystem'    => $is_intersystem,
        ]);
    }
}
