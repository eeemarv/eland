<?php declare(strict_types=1);

namespace App\Form\ColumnSelect;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BaseUserColumnSelectType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $ary = [
            'code',
            'name',
            'fullname',
            'postcode',
            'role',
            'saldo',
            'minlimit',
            'maxlimit',
            'comments',
            'admincomment',
            'hobbies',
            'created_at',
            'last_edit_at',
            'adate',
            'lastlogin',
            'periodic_overview_en',
        ];

        foreach ($ary as $field)
        {
            $builder->add($field, CheckboxType::class, [
                'required'  => false,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
        ]);
    }
}
