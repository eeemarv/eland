<?php declare(strict_types=1);

namespace App\Form\Post;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\HttpFoundation\RequestStack;

class CategoryType extends AbstractType
{	
    private $db;
    private $translator;
    private $schema;

    public function __construct(Db $db,
         TranslatorInterface $translator, 
         RequestStack $requestStack
    )
    {
        $this->db = $db;
        $this->translator = $translator;
        $request = $requestStack->getCurrentRequest();
        $this->schema = $request->attributes->get('schema');
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if ($options['root_selectable'])
        {
            $main_cat_string = '-- ';
            $main_cat_string .= $this->translator->trans('category.select_option.main_category');
            $main_cat_string .= ' --';

            $parent_categories = [
                $main_cat_string  => 0,
            ];
        }
        else
        {
            $parent_categories = [];
        }

        if ($options['sub_selectable'])
        {
            $rs = $this->db->prepare('select id, name 
                from ' . $this->schema . '.categories 
                where leafnote = 0 
                order by name asc');

            $rs->execute();

            while ($row = $rs->fetch())
            {
                $parent_categories[$row['name']] = $row['id'];
            }
        }

        $builder->add('name', TextType::class, [			
            'constraints' 	=> [
                new Assert\NotBlank(),
                new Assert\Length(['max' => 40, 'min' => 1]),
            ],
                'attr'	=> [
                    'maxlength'	=> 40,
                ],
            ])

            ->add('id_parent', ChoiceType::class, [
                'choices'  					=> $parent_categories,
                'choice_translation_domain' => false,
            ])

            ->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'root_selectable'   => true,
            'sub_selectable'    => true,
        ]);
    }
}