<?php declare(strict_types=1);

namespace App\Form\Type\Field;

use App\Form\DataTransformer\AccountCodeTransformer;
use App\Service\PageParamsService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class AutocompleteAccountType extends AbstractType
{
  public function __construct(
    private readonly PageParamsService $pp,
    private readonly AccountCodeTransformer $account_code_transformer,
    private readonly UrlGeneratorInterface $url_generator,
  )
  {
  }

  public function buildForm(
    FormBuilderInterface $builder,
    array $options
  ):void
  {
    parent::buildForm($builder, $options);

    $builder->addModelTransformer($this->account_code_transformer);
  }

  public function buildView(
    FormView $view,
    FormInterface $form,
    array $options
  ):void
  {
    $view->vars['attr']['data-autocomplete-accounts-url-value'] = $this->url_generator->generate(
      name: 'autocomplete_accounts', 
      parameters: [
        'schema' => $this->pp->schema(),
        'role_short' => $this->pp->role_short(),
        'group' => $options['account_group'],
      ],
    );
  }

  public function configureOptions(OptionsResolver $resolver):void
  {
    $resolver->setDefault('account_group', 'active');
    $resolver->setAllowedTypes('account_group', 'string');
    $resolver->setAllowedValues('account_group', [
      'all', 'active', 'active-users', 'users',
      'intersystems', 'email-intersystems', 'inactive',
    ]);
    $resolver->setRequired('account_group');

    $resolver->setDefault('attr', [
      'data-controller' => 'autocomplete-accounts',
    ]);
  }

  public function getParent():string
  {
    return TextType::class;
  }

  public function getBlockPrefix():string
  {
    return 'autocomplete';
  }
}