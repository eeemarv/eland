<?php declare(strict_types=1);

namespace App\Form\ColumnSelect;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\HttpFoundation\RequestStack;
use App\Repository\TypeContactRepository;

class UserContactDetailColumnSelectType extends AbstractType
{
    private $typeContactRepository;
    private $schema;

    public function __construct(TypeContactRepository $typeContactRepository, RequestStack $requestStack)
    {
        $this->typeContactRepository = $typeContactRepository;
        $request = $requestStack->getCurrentRequest();
        $this->schema = $request->attributes->get('schema');
    }
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $typeContactAry = $this->typeContactRepository->getAllAbbrev($this->schema);

        foreach ($typeContactAry as $id => $abbrev)
        {
            $builder->add($abbrev, CheckboxType::class, [
                'required'      => false,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
        ]);
    }
}