<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;

class SystemUserAuthenticator extends AbstractGuardAuthenticator
{
    private $urlGenerator;
    private $csrfTokenManager;
    private $passwordEncoder;

    public function __construct(
        UrlGeneratorInterface $urlGenerator,
        CsrfTokenManagerInterface $csrfTokenManager,
        UserPasswordEncoderInterface $passwordEncoder
    )
    {
        $this->urlGenerator = $urlGenerator;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->passwordEncoder = $passwordEncoder;
    }

    public function supports(Request $request)
    {
        return 'login' === $request->attributes->get('_route')
            && $request->isMethod('POST');
    }

    public function getCredentials(Request $request)
    {
        $session = $request->getSession();

        $credentials = [
            'login'     => $request->request->get('login'),
            'password'  => $request->request->get('password'),
            'system'    => $request->attributes->get('system'),
            'logins'    => $session->get('logins') ?? [],
//            'csrf_token' => $request->request->get('_csrf_token'),
        ];
    }

    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        $user = $userProvider->loadUserByCredentials($credentials);

        if (!$user)
        {
            throw new CustomUserMessageAuthenticationException('Login could not be found.');
        }

        return $user;
    }

    public function checkCredentials($credentials, UserInterface $user)
    {
        return $this->passwordEncoder->isPasswordValid($user, $credentials['password']);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        // todo
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {
/*
        $location =

        if ($targetPath = $this->getTargetPath($request->getSession(), $providerKey))
        {
            return new RedirectResponse($targetPath);
        }
*/
    }

    public function start(Request $request, AuthenticationException $authException = null)
    {
        $params = [
            'system'    => $request->attributes->get('system'),
        ];

		$location = $request->getRequestUri();

        if ($location)
        {
            $params['location'] = $location;
        }

        $url = $this->urlGenerator->generate('login', $params);

        return new RedirectResponse($url);
    }

    public function supportsRememberMe()
    {
        return false;
    }
}
