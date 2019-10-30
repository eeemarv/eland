<?php

namespace App\Security\Voter;

use App\Cnst\AccessCnst;
use App\Cnst\RoleCnst;
use App\Service\SessionUserService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ElandRoleVoter extends Voter
{
    protected $su;
    protected $request_stack;
    protected $system;

    public function __construct(
        SessionUserService $su,
        RequestStack $request_stack
    )
    {
        $this->su = $su;
        $this->request_stack = $request_stack;

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

        return isset(RoleCnst::SHORT[$attribute]);
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        return AccessCnst::ACCESS[$this->su->role()][$attribute] ?? false;
    }
}
