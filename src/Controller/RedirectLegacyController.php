<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;

class RedirectLegacyController extends AbstractController
{
    const SUPPORTED_ROUTES = [
        'messages'  => true,
        'news'      => true,
        'users'     => true,
        'docs'      => true,
        'contact'   => true,
        'login'     => true,
        'register'  => true,
        'index'     => true,
        'support'   => true,
    ];

    public function __invoke(
        Request $request,
        RequestContext $request_context,
        UrlGeneratorInterface $url_generator,
        string $subdomain,
        string $env_app_host
    ):Response
    {
        $request_context->setHost($env_app_host);
        $script_name = $request->getScriptName();
        $role_short = $request->get('r') === 'admin' ? 'a' : 'u';
        $id = $request->get('id', '');
        $del = $request->get('del', '');
        $add = $request->get('add', '');
        $edit = $request->get('edit', '');
        $approve = $request->get('approve', '');
        $extend = $request->get('extend', '');

        $params = [
            'role_short'    => $role_short,
            'system'        => $subdomain,
        ];

        $route = strtr($script_name, [
            '/'     => '',
            '.php'  => '',
        ]);

        if (isset(self::SUPPORTED_ROUTES[$route]))
        {
            $route_admin = $route === 'users' & $role_short === 'a' ? '_admin' : '';
            $route .= $add ? '_add' : '';
            $route .= $id ? '_show' : '';
            $route .= $edit ? '_edit' : '';
            $route .= $del ? '_del' : '';
            $route .= $approve ? '_approve' : '';
            $route .= $extend ? '_extend' : '';
            $route .= $route_admin;

            $params = $id ? ['id' => $id] : [];
        }
        else
        {
            $route = 'index';
            $params = [];
        }

        $redirect_url = $url_generator->generate($route, $params, UrlGeneratorInterface::ABSOLUTE_URL);
        $this->redirect($redirect_url);

        return new Response('');
    }
}
