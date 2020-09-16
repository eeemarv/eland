<?php

namespace App\Security\Voter;

use App\Cnst\AccessCnst;
use App\Cnst\RoleCnst;
use App\Service\ConfigService;
use App\Service\IntersystemsService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\SystemsService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ElandRoleVoter extends Voter
{
    protected SessionUserService $su;
    protected PageParamsService $pp;
    protected ConfigService $config_service;
    protected SystemsService $systems_service;
    protected IntersystemsService $intersystems_service;

    public function __construct(
        SessionUserService $su,
        PageParamsService $pp,
        ConfigService $config_service,
        SystemsService $systems_service,
        IntersystemsService $intersystems_service
    )
    {
        $this->su = $su;
        $this->pp = $pp;
        $this->config_service = $config_service;
        $this->systems_service = $systems_service;
        $this->intersystems_service = $intersystems_service;
    }

    protected function supports($attribute, $subject):bool
    {
        if ($this->pp->schema() === '')
        {
            return false;
        }

        return isset(RoleCnst::SHORT[$attribute]);
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        if ($attribute === 'guest' || $attribute === 'user')
        {
            $schema = $this->pp->schema();

            if ($schema === '')
            {
                return false;
            }

            if ($this->config_service->get('maintenance', $schema))
            {
                return false;
            }

            if ($attribute === 'guest')
            {
                if (!$this->config_service->get_intersystem_en($schema))
                {
                    return false;
                }

                $org_schema = $this->pp->org_schema();

                if ($org_schema !== '')
                {
                    $eland_ary = $this->intersystems_service->get_eland($org_schema);

                    if (!isset($eland_ary[$schema]))
                    {
                        return false;
                    }
                }
            }
        }

        return AccessCnst::ACCESS[$this->su->role()][$attribute] ?? false;
    }
}
