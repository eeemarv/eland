<?php declare(strict_types=1);

namespace App\Form\Filter;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use App\Form\Type\TypeaheadType;

class LogsFilterType extends AbstractType
{
    public function __construct()
    {
    }

    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ):void
    {
		$builder->add('q', TextType::class, [
            'required' => false,
        ]);

        $builder->add('user', TypeaheadType::class, [
            'add'   => [
                ['accounts', ['status' => 'active']],
                ['accounts', ['status' => 'inactive']],
                ['accounts', ['status' => 'ip']],
                ['accounts', ['status' => 'im']],
                ['accounts', ['status' => 'extern']],
            ],
            'filter'    => 'accounts',
            'required'  => false,
        ]);

        $builder->add('type', TypeaheadType::class, [
            'add'       => 'log_types',
            'required'  => false,
        ]);
    }

    public function getParent():string
    {
        return FilterType::class;
    }

    public function getBlockPrefix():string
    {
        return 'f';
    }
}