<?php declare(strict_types=1);

namespace App\Form\ColumnSelect;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use App\Service\PageParamsService;
use Doctrine\DBAL\Connection as Db;

class UserContactDetailColumnSelectType extends AbstractType
{
    protected Db $db;
    protected PageParamsService $pp;

    public function __construct(
        Db $db,
        PageParamsService $pp
    )
    {
        $this->db = $db;
        $this->pp = $pp;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $ary = $this->db->fetchAll('select abbrev
            from ' . $pp->schema() . '.type_contact
            order by id');

        foreach ($ary as  $item)
        {
            $builder->add($item['abbrev'], CheckboxType::class, [
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