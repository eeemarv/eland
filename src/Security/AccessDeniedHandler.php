<?php

namespace App\Security;

use App\Render\LinkRender;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;

/**
 * This is never called. See bug report https://github.com/symfony/symfony/issues/28229
 * App\EventSubscriber\AccessDeniedExceptionSubscriber is used instead
 */

class AccessDeniedHandler implements AccessDeniedHandlerInterface
{
  public function __construct(
    private readonly LinkRender $link_render
  )
  {
  }

  public function handle(Request $request, AccessDeniedException $accessDeniedException): ?Response
  {
    $schema = $request->attributes->get('schema');

    if ($schema)
    {
      $this->link_render->redirect('login', [
        'schema' => $schema,
      ], [
        'location'  => $request->getRequestUri(),
      ]);
    }

    $this->link_render->redirect('index', [], []);
    return new Response('Access Denied', 403);
  }
}