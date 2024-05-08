<?php declare(strict_types=1);

namespace App\Form\Type\Users;

use App\Cache\ConfigCache;
use App\Command\Users\UsersAccountCommand;
use App\Form\Type\Field\TypeaheadType;
use App\Service\PageParamsService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UsersAccountType extends AbstractType
{
    public function __construct(
        protected ConfigCache $config_cache,
        protected PageParamsService $pp
    )
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options):void
    {
        $limits_enabled = $this->config_cache->get_bool('accounts.limits.enabled', $this->pp->schema());

        $builder->add('code', TypeaheadType::class, [
            'add'           => 'account_codes',
            'render_omit'   => $options['render_omit'],
        ]);

        if ($limits_enabled)
        {
            $builder->add('min_limit', IntegerType::class);
            $builder->add('max_limit', IntegerType::class);
        }

		$builder->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefault('data_class', UsersAccountCommand::class);
        $resolver->setDefault('render_omit', '');
        $resolver->setAllowedTypes('render_omit', 'string');
    }
}