<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Cache\ConfigCache;
use App\Command\Users\UsersBirthdateCommand;
use App\Form\Type\Users\UsersBirthdateType;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class UsersBirthdateEditController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/users/{id}/birthdate/edit',
    name: 'users_birthdate_edit',
    methods: ['GET', 'POST'],
    requirements: [
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.admin%',
      'id'            => '%assert.id%',
    ],
    defaults: [
      'is_self'       => false,
      'module'        => 'users',
    ],
  )]

  #[Route(
    '/{schema}/{role_short}/users/self/birthdate/edit',
    name: 'users_birthdate_edit_self',
    methods: ['GET', 'POST'],
    requirements: [
      'schema'        => '%assert.schema%',
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
    ConfigCache $config_service,
    PageParamsService $pp,
    SessionUserService $su,
  ):Response
  {
    if (!$config_service->get_bool('users.fields.birthdate.enabled', $pp->schema()))
    {
      throw $this->createAccessDeniedException(
        'Users birthdate submodule not enabled.'
      );
    }

    if (!$is_self
      && $su->is_owner($id))
    {
      return $this->redirectToRoute(
        route: 'users_birthdate_edit_self',
        parameters: $pp->ary(),
      );
    }

    if ($is_self)
    {
      $id = $su->id();
    }

    $command = new UsersBirthdateCommand();

    $user = $user_repository->get(
      id: $id,
      schema: $pp->schema_o(),
    );

    if ($user === false)
    {
      throw $this->createNotFoundException(
        'User with id ' . $id . ' not found'
      );
    }

    $is_intersystem = isset($user['remote_schema']) || isset($user['remote_email']);
    $command->birthdate = $user['birthdate'];

    $form = $this->createForm(
      type: UsersBirthdateType::class,
      data: $command,
    );
    $form->handleRequest($request);

    if ($form->isSubmitted()
      && $form->isValid())
    {
      if ($command->birthdate === $user['birthdate'])
      {
        $this->addFlash(
          type: 'warning',
          message: [
            'key' => 'flash.no_change',
          ]
        );
      }
      else
      {
        $user_repository->update([
          'birthdate'    => $command->birthdate,
        ], $id, $pp->schema());

        $this->addFlash(
          type: 'warning',
          message: [
            'key' => 'users_birthdate_edit.flash.success',
            'params'  => [
              'self'  => $is_self ? 'yes' : 'no',
              'user'  => $user['name'],
            ]
          ]
        );
      }

      if ($is_self)
      {
        return $this->redirectToRoute(
          route: 'users_show_self',
          parameters: $pp->ary(),
        );
      }

      return $this->redirectToRoute(
        route: 'users_show',
        parameters: [
          ... $pp->ary(),
          'id' => $id,
        ],
      );
    }

    return $this->render('users/users_birthdate_edit.html.twig', [
      'form'              => $form->createView(),
      'user'              => $user,
      'id'                => $id,
      'is_self'           => $is_self,
      'is_intersystem'    => $is_intersystem,
    ]);
  }
}
