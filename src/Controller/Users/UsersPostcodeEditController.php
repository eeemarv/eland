<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Command\Users\UsersPostcodeCommand;
use App\Form\Type\Users\UsersPostcodeType;
use App\Repository\UserRepository;
use App\Service\ConfigService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class UsersPostcodeEditController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/users/{id}/postcode/add',
    name: 'users_postcode_edit',
    methods: ['GET', 'POST'],
    requirements: [
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.admin%',
      'id'            => '%assert.id%',
    ],
    defaults: [
      'mode'          => 'add',
      'is_self'       => false,
      'module'        => 'users',
    ],
  )]

  #[Route(
    '/{schema}/{role_short}/users/self/postcode/add',
    name: 'users_postcode_edit_self',
    methods: ['GET', 'POST'],
    requirements: [
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.user%',
      'id'            => '%assert.id%',
    ],
    defaults: [
      'mode'          => 'add',
      'id'            => 0,
      'is_self'       => true,
      'module'        => 'users',
    ],
  )]

  #[Route(
    '/{schema}/{role_short}/users/{id}/postcode/edit',
    name: 'users_postcode_edit',
    methods: ['GET', 'POST'],
    requirements: [
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.admin%',
      'id'            => '%assert.id%',
    ],
    defaults: [
      'mode'          => 'edit',
      'is_self'       => false,
      'module'        => 'users',
    ],
  )]

  #[Route(
    '/{schema}/{role_short}/users/self/postcode/edit',
    name: 'users_postcode_edit_self',
    methods: ['GET', 'POST'],
    requirements: [
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.user%',
      'id'            => '%assert.id%',
    ],
    defaults: [
      'mode'          => 'edit',
      'id'            => 0,
      'is_self'       => true,
      'module'        => 'users',
    ],
  )]

  public function __invoke(
    Request $request,
    int $id,
    bool $is_self,
    string $mode,
    UserRepository $user_repository,
    ConfigService $config_service,
    PageParamsService $pp,
    SessionUserService $su,
  ):Response
  {
    if (!$config_service->get_bool(
      config_id: 'users.fields.postcode.enabled',
      schema: $pp->schema_o(),
    ))
    {
      throw $this->createAccessDeniedException(
        'Users postcode submodule not enabled.'
      );
    }

    if (!$is_self
      && $su->is_owner($id))
    {
      return $this->redirectToRoute(
        route: 'users_postcode_edit_self',
        parameters: $pp->ary(),
      );
    }

    if ($is_self)
    {
      $id = $su->id();
    }

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

    $postcode_set_previously = false;

    if (isset($user['postcode']) && $user['postcode'] !== '')
    {
      $postcode_set_previously = true;
    }

    if ($postcode_set_previously)
    {
      if ($mode === 'add')
      {
        throw $this->createAccessDeniedException(
          'Wrong route: postcode already exists (use edit route instead)'
        );
      }
    }
    else
    {
      if ($mode === 'edit')
      {
        throw $this->createAccessDeniedException(
          'Wrong route: can not edit non-existing postcode (use add route instead)'
        );
      }
    }


    $command = new UsersPostcodeCommand();

    $is_intersystem = isset($user['remote_schema']) || isset($user['remote_email']);
    $command->postcode = $user['postcode'];

    $form = $this->createForm(
      type: UsersPostcodeType::class,
      data: $command,
    );

    $form->handleRequest($request);

    if ($form->isSubmitted()
      && $form->isValid())
    {
      $command = $form->getData();

      if ($command->postcode === $user['postcode'])
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
        $user_repository->set_postcode(
          id: $id,
          postcode: $command->postcode,
          schema: $pp->schema_o(),
        );

        $this->addFlash(
          type: 'success',
          message: [
            'key' => 'users_postcode_edit.flash.success',
            'params' => [
              'self'   => $is_self ? 'yes' : 'no',
              'user'  => $user['name'],
              'mode'  => $mode,
              'old_postcode'  => $user['postcode'],
              'new_postcode'  => $command->postcode,
            ]
          ],
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

    return $this->render('users/users_postcode_edit.html.twig', [
      'form'              => $form->createView(),
      'user'              => $user,
      'mode'              => $mode,
      'id'                => $id,
      'is_self'           => $is_self,
      'is_intersystem'    => $is_intersystem,
    ]);
  }
}
