<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Command\Users\UsersPostcodeCommand;
use App\Form\Type\Users\UsersPostcodeType;
use App\Repository\UserRepository;
use App\Routing\AccessLevel;
use App\Routing\SchemaRoute;
use App\Routing\UsersSubModule;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class UsersPostcodeEditController extends AbstractController
{
  #[SchemaRoute(
    access_level: AccessLevel::Admin,
    module: UsersSubModule::Postcode,
    is_self: false,
    mode: 'add',
    path: '/users/{id}/postcode/add',
    name: 'users_postcode_add',
    methods: ['GET', 'POST'],
  )]

  #[SchemaRoute(
    access_level: AccessLevel::User,
    module: UsersSubModule::Postcode,
    is_self: true,
    mode: 'add',
    path: '/users/self/postcode/add',
    name: 'users_postcode_add_self',
    methods: ['GET', 'POST'],
  )]

  #[SchemaRoute(
    access_level: AccessLevel::Admin,
    module: UsersSubModule::Postcode,
    is_self: false,
    mode: 'edit',
    path: '/users/{id}/postcode/edit',
    name: 'users_postcode_edit',
    methods: ['GET', 'POST'],
  )]

  #[SchemaRoute(
    access_level: AccessLevel::User,
    module: UsersSubModule::Postcode,
    is_self: true,
    mode: 'edit',
    path: '/users/self/postcode/edit',
    name: 'users_postcode_edit_self',
    methods: ['GET', 'POST'],
  )]

  public function __invoke(
    Request $request,
    bool $_is_self,
    string $_mode,
    UserRepository $user_repository,
    PageParamsService $pp,
    SessionUserService $su,
    int|null $id = null,
  ):Response
  {

    if (!$_is_self
      && $su->is_owner($id))
    {
      return $this->redirectToRoute(
        route: 'users_postcode_edit_self',
        parameters: $pp->ary(),
      );
    }

    if ($_is_self)
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
      if ($_mode === 'add')
      {
        throw $this->createAccessDeniedException(
          'Wrong route: postcode already exists (use edit route instead)'
        );
      }
    }
    else
    {
      if ($_mode === 'edit')
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
              'self'   => $_is_self ? 'yes' : 'no',
              'user'  => $user['name'],
              'mode'  => $_mode,
              'old_postcode'  => $user['postcode'],
              'new_postcode'  => $command->postcode,
            ]
          ],
        );
      }

      if ($_is_self)
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
      'mode'              => $_mode,
      'id'                => $id,
      'is_self'           => $_is_self,
      'is_intersystem'    => $is_intersystem,
    ]);
  }
}
