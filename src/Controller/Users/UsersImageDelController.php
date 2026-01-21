<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Render\LinkRender;
use App\Repository\UserRepository;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\UserCacheService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class UsersImageDelController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/users/{id}/image/del',
    name: 'users_image_del',
    methods: ['GET', 'POST'],
    requirements: [
      'id'            => '%assert.id%',
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.admin%',
    ],
    defaults: [
      'is_self'       => false,
      'module'        => 'users',
    ],
  )]

  #[Route(
    '/{schema}/{role_short}/users/self/image/del',
    name: 'users_image_del_self',
    methods: ['GET', 'POST'],
    requirements: [
      'schema'        => '%assert.schema%',
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
    LinkRender $link_render,
    UserRepository $user_repository,
    PageParamsService $pp,
    SessionUserService $su,
    string $env_s3_url,
  ):Response
  {
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

    $file = $user['image_file'];

    if ($file == '' || !$file)
    {
      throw $this->createNotFoundException('No image file found for user with id ' . $id);
    }

    if ($request->isMethod('POST'))
    {
      $user_repository->del_image_file(
        id: $id,
        schema: $pp->schema_o(),
      );

      $this->addFlash('success', 'Profielfoto/afbeelding verwijderd.');

      return $this->redirectToRoute(
        route: 'users_show',
        parameters: [
          ...$pp->ary(),
          'id' => $id,
        ],
      );
    }

    $out = '<div class="row">';
    $out .= '<div class="col-xs-6">';
    $out .= '<div class="thumbnail">';
    $out .= '<img src="';
    $out .= $env_s3_url . $file;
    $out .= '" class="img-rounded">';
    $out .= '</div>';
    $out .= '</div>';

    $out .= '</div>';

    $out .= '<form method="post">';

    $out .= '<div class="panel panel-info">';
    $out .= '<div class="panel-heading">';

    $out .= $link_render->btn_cancel('users_show', $pp->ary(), ['id' => $id]);

    $out .= '&nbsp;';
    $out .= '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger btn-lg">';

    $out .= '</form>';

    $out .= '</div>';
    $out .= '</div>';

    return $this->render('users/users_image_del.html.twig', [
      'content'   => $out,
      'id'        => $id,
      'is_self'   => $is_self,
      'user'      => $user,
    ]);
  }
}
