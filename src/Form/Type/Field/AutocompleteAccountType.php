<?php declare(strict_types=1);

namespace App\Form\Type\Field;

use App\Form\DataTransformer\AccountCodeTransformer;
use App\Service\ConfigService;
use App\Service\ItemAccessService;
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
    private readonly ItemAccessService $item_access_service,
    private readonly ConfigService $config_service,
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
      'autocomplete_accounts', [
        'schema' => $this->pp->schema(),
        'role_short' => $this->pp->role_short(),
        'group' => $options['account_group'],
      ]
    );

    $new_users_enabled = $this->config_service->get_bool(
      config_id: 'users.new.enabled',
      schema: $this->pp->schema_o(),
    );
    $leaving_users_enabled = $this->config_service->get_bool(
      config_id: 'users.leaving.enabled',
      schema: $this->pp->schema_o(),
    );

    $show_new_status = $new_users_enabled;

    if ($show_new_status)
    {
      $new_users_access = $this->config_service->get_str(
        config_id: 'users.new.access',
        schema: $this->pp->schema_o(),
      );
      $show_new_status = $this->item_access_service->is_visible($new_users_access);
    }

    $show_leaving_status = $leaving_users_enabled;

    if ($show_leaving_status)
    {
      $leaving_users_access = $this->config_service->get_str(
        config_id: 'users.leaving.access',
        schema: $this->pp->schema_o(),
      );
      $show_leaving_status = $this->item_access_service->is_visible($leaving_users_access);
    }

    if (!$show_new_status) {
      $view->vars['attr']['data-autocomplete-accounts-show-new-status-value'] = 'false';
    }
    if (!$show_leaving_status) {
      $view->vars['attr']['data-autocomplete-accounts-show-leaving-status-value'] = 'false';
    }
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
      'data-autocomplete-accounts-new-threshold-value' =>
        $this->config_service->get_new_user_treshold(
          schema: $this->pp->schema_o(),
        )->format('Y-m-d H:i:s'),
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