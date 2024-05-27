<?php declare(strict_types=1);

namespace App\Form\Type\Config;

use App\Cnst\ConfigCnst;
use App\Command\Config\ConfigLandingPageCommand;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ConfigLandingPageType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ):void
    {
        $choices = [];
        $choice_translation_parameters = [];

        foreach(ConfigCnst::LANDING_PAGE_OPTIONS as $opt => $lang)
        {
            $key = $opt . '.title';
            $choices[$key] = $opt;
            $choice_translation_parameters[$key] = [
                'self'      =>  'all',
                'with_without_category' => 'all',
            ];
        }

        $builder
            ->add('landing_page', ChoiceType::class, [
                'choices'   => $choices,
                'choice_translation_parameters' => $choice_translation_parameters,
            ])
            ->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefault('data_class', ConfigLandingPageCommand::class);
    }
}