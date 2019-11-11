<?php

namespace App\Security\Voter;

use App\Cnst\AccessCnst;
use App\Cnst\RoleCnst;
use App\Service\ConfigService;
use App\Service\SessionUserService;
use App\Service\SystemsService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ElandRoleVoter extends Voter
{
    protected $su;
    protected $request_stack;
    protected $system;
    protected $config_service;
    protected $systems_service;

    public function __construct(
        SessionUserService $su,
        RequestStack $request_stack,
        ConfigService $config_service,
        SystemsService $systems_service
    )
    {
        $this->su = $su;
        $this->request_stack = $request_stack;
        $this->config_service = $config_service;
        $this->systems_service = $systems_service;

        $this->init();
    }

    protected function init():void
    {
        $request = $this->request_stack->getCurrentRequest();
        $this->system = $request->attributes->get('system', '');
    }

    protected function supports($attribute, $subject)
    {
        if ($this->system === '')
        {
            return false;
        }

        if ($attribute === 'guest')
        {
            $schema = $this->systems_service->get_schema($this->system);

            if ($schema === '')
            {
                return false;
            }

            if (!$this->config_service->get_intersystem_en($schema))
            {
                return false;
            }
        }

        return isset(RoleCnst::SHORT[$attribute]);
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        return AccessCnst::ACCESS[$this->su->role()][$attribute] ?? false;
    }
}
