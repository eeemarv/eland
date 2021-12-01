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
    public function __construct(
        protected SessionUserService $su,
        protected PageParamsService $pp,
        protected ConfigService $config_service,
        protected SystemsService $systems_service,
        protected IntersystemsService $intersystems_service
    )
    {
    }

    protected function supports($attribute, $subject):bool
    {
        if ($this->pp->schema() === '')
        {
            return false;
        }

        return isset(RoleCnst::SHORT[$attribute]);
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token): bool
    {
        if ($attribute === 'guest' || $attribute === 'user')
        {
            $schema = $this->pp->schema();

            if ($schema === '')
            {
                return false;
            }

            if ($this->config_service->get_bool('system.maintenance_en', $schema))
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
