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
        LinkRender $link_render
    )
    {
        $this->link_render = $link_render;
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): ?Response
    {
        $system = $request->attributes->get('system', '');

        if ($system)
        {
            $this->link_render->redirect('login', [
                'system' => $system,
            ], [
                'location'  => $request->getRequestUri(),
            ]);
        }

        $this->link_render->redirect('index', [], []);
        return new Response('Access Denied', 403);
    }
}