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
            'letscode',
            'name',
            'fullname',
            'postcode',
            'accountrole',
            'saldo',
            'minlimit',
            'maxlimit',
            'comments',
            'admincomment',
            'hobbies',
            'cdate',
            'mdate',            
            'adate',
            'lastlogin',
            'saldomail',
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
