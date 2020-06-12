<?php declare(strict_types=1);

namespace App\Form\Input\Typeahead;

use App\Form\DataTransformer\TypeaheadAccountTransformer;
use App\Form\Input\TextAddonType;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\TypeaheadService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;

class TypeaheadAccountType extends AbstractType
{
    protected PageParamsService $pp;
    protected TypeaheadService $typeahead_service;
    protected ConfigService $config_service;
    protected TypeaheadAccountTransformer $typeahead_account_transformer;

    public function __construct(
        PageParamsService $pp,
        TypeaheadService $typeahead_service,
        ConfigService $config_service,
        TypeaheadAccountTransformer $typeahead_account_transformer
    )
    {
        $this->pp = $pp;
        $this->typeahead_service = $typeahead_service;
        $this->config_service = $config_service;
        $this->typeahead_account_transformer = $typeahead_account_transformer;
    }

    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    )
    {
        $builder->addModelTransformer($this->typeahead_account_transformer);
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $data_typeahead = $this->typeahead_service->ini()
            ->add('accounts', ['status' => 'active'])
            ->str([
                    'filter'        => 'accounts',
                    'newuserdays'   => (int) $this->config_service->get('newuserdays', $this->pp->schema()),
                ]);

        parent::buildView($view, $form, $options);

        $view->vars['attr'] = array_merge($options['attr'], [
            'data-typeahead' => $data_typeahead,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'attr'              => [
                'data-typeahead'    => '',
                'autocomplete'      => 'off',
            ],
        ]);
    }

    public function getParent()
    {
        return TextAddonType::class;
    }

    /*

    public function getBlockPrefix()
    {
        return 'addon';
    }

    */
}