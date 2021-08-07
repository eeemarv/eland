<?php declare(strict_types=1);

namespace App\Validator\Login;

use App\Command\Login\LoginCommand;
use App\Repository\UserRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use App\Service\PageParamsService;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use App\Security\User;
use App\Service\ConfigService;

class LoginValidator extends ConstraintValidator
{
    public function __construct(
        protected EncoderFactoryInterface $encoder_factory,
        protected UserRepository $user_repository,
        protected ConfigService $config_service,
        protected PageParamsService $pp,
        protected string $env_master_password
    )
    {
    }

    public function validate($command, Constraint $constraint)
    {
        if (!$constraint instanceof Login)
        {
            throw new UnexpectedTypeException($constraint, Login::class);
        }

        if (!$command instanceof LoginCommand)
        {
            throw new UnexpectedTypeException($command, LoginCommand::class);
        }

        $login_lowercase = strtolower($command->login);
        $password = $command->password;
        $encoder = $this->encoder_factory->getEncoder(new User());

        if ($login_lowercase === 'master'
            && $this->env_master_password
            && $encoder->isPasswordValid($this->env_master_password, $password, null))
        {
            $command->is_master = true;
            return;
        }

        if (filter_var($login_lowercase, FILTER_VALIDATE_EMAIL))
        {
            $count_by_email = $this->user_repository->count_active_by_email($login_lowercase, $this->pp->schema());

            if ($count_by_email > 1)
            {
                $this->context->buildViolation('login.login.email_not_unique')
                    ->atPath('login')
                    ->addViolation();
                return;
            }

            if ($count_by_email === 1)
            {
                $command->id = $this->user_repository->get_active_id_by_eamil($login_lowercase, $this->pp->schema());
            }
        }

        if (!isset($command->id))
        {
            $count_by_name = $this->user_repository->count_active_by_name($login_lowercase, $this->pp->schema());

            if ($count_by_name > 1)
            {
                $this->context->buildViolation('login.login.name_not_unique')
                    ->atPath('login')
                    ->addViolation();
                return;
            }

            if ($count_by_name === 1)
            {
                $command->id = $this->user_repository->get_active_id_by_name($login_lowercase, $this->pp->schema());
            }
        }

        if (!isset($command->id))
        {
            $count_by_code = $this->user_repository->count_active_by_code($login_lowercase, $this->pp->schema());

            if ($count_by_code > 1)
            {
                $this->context->buildViolation('login.login.code_not_unique')
                    ->atPath('login')
                    ->addViolation();
                return;
            }

            if ($count_by_code === 1)
            {
                $command->id = $this->user_repository->get_active_id_by_code($login_lowercase, $this->pp->schema());
            }
        }

        if (!isset($command->id) || !$command->id)
        {
            $this->context->buildViolation('login.login.not_known')
                ->atPath('login')
                ->addViolation();
            return;
        }

        $user = $this->user_repository->get($command->id, $this->pp->schema());

        if (!$user)
        {
            // should never happen
            $this->context->buildViolation('login.login.unknown')
                ->atPath('login')
                ->addViolation();
            return;
        }

        if (!in_array($user['status'], [1, 2]))
        {
            // should never happen
            $this->context->buildViolation('login.login.not_active')
                ->atPath('login')
                ->addViolation();
            return;
        }

        $maintenance_en = $this->config_service->get_bool('system.maintenance_en', $this->pp->schema());

        if ($maintenance_en && $user['role'] !== 'admin')
        {
            $this->context->buildViolation('login.maintenance')
                ->addViolation();
            return;
        }

        if (!in_array($user['role'], ['admin', 'user']))
        {
            // no guest logins yet
            $this->context->buildViolation('login.login.guest_no_rights')
                ->atPath('login')
                ->addViolation();
            return;
        }

        $intersystem_en = $this->config_service->get_intersystem_en($this->pp->schema());

        if (!$intersystem_en && !in_array($user['role'], ['admin', 'user']))
        {
            // should never happen (see above)
            $this->context->buildViolation('login.login.guest_not_allowed')
                ->atPath('login')
                ->addViolation();
            return;
        }

        if ($user['password'] === hash('sha512', $password))
        {
            $hashed_password = $encoder->encodePassword($password, null);

            $this->user_repository->set_password(
                $command->id,
                $hashed_password,
                $this->pp->schema()
            );

            $command->password_hashing_updated = true;

            return;
        }

        if (!$encoder->isPasswordValid($user['password'], $password, null))
        {
            $this->context->buildViolation('login.password.not_correct')
                ->atPath('password')
                ->addViolation();
            return;
        }
    }
}