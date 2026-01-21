<?php declare(strict_types=1);

namespace App\Form\Type\Messages;

use App\Form\EventSubscriber\AccessFieldSubscriber;
use App\Form\Type\Field\BtnChoiceType;
use App\Form\Type\Field\CategorySelectType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use App\Form\Type\Filter\FilterType;
use App\Form\Type\Field\TypeaheadType;
use App\Service\ConfigService;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use App\Service\VarRouteService;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class MessagesFilterType extends AbstractType
{
  public function __construct(
    private readonly ConfigService $config_service,
    private readonly ItemAccessService $item_access_service,
    private readonly UrlGeneratorInterface $url_generator,
    private readonly AccessFieldSubscriber $access_field_subscriber,
    private readonly PageParamsService $pp,
    private readonly VarRouteService $vr,
  )
  {
  }

  public function buildForm(
    FormBuilderInterface $builder,
    array $options,
  ):void
  {
    $service_stuff_enabled = $this->config_service->get_bool(
      config_id: 'messages.fields.service_stuff.enabled',
      schema: $this->pp->schema_o(),
    );
    $category_enabled = $this->config_service->get_bool(
      config_id: 'messages.fields.category.enabled',
      schema: $this->pp->schema_o(),
    );
    $expires_at_enabled = $this->config_service->get_bool(
      config_id: 'messages.fields.expires_at.enabled',
      schema: $this->pp->schema_o(),
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

    $user_status_choices = [];
    $user_status_choices['active'] = 'active';

    if ($show_new_status)
    {
      $user_status_choices['new'] = 'new';
    }

    if ($show_leaving_status)
    {
      $user_status_choices['leaving'] = 'leaving';
    }

    $typeahead_add = [];

    $typeahead_add[] = ['accounts', ['status' => 'active']];

    if ($this->pp->is_user() || $this->pp->is_admin())
    {
      $typeahead_add[] = ['accounts', ['status' => 'extern']];
    }

    if ($this->pp->is_admin())
    {
      $typeahead_add[] = ['accounts', ['status' => 'inactive']];
      $typeahead_add[] = ['accounts', ['status' => 'im']];
      $typeahead_add[] = ['accounts', ['status' => 'ip']];
    }

    $builder->add('q', TextType::class, [
      'required' => false,
    ]);

    if ($category_enabled)
    {
      $builder->add('cat', CategorySelectType::class, [
        'parent_selectable' => true,
        'null_selectable'   => true,
        'all_choice'        => true,
        'required'          => false,
      ]);
    }

    $builder->add('open_panel', ButtonType::class, [
    ]);

    $builder->add('ow', BtnChoiceType::class, [
      'choices'       => [
        'offer' => 'offer',
        'want'  => 'want',
      ],
      'multiple'      => true,
      'required'      => false,
    ]);

    if ($service_stuff_enabled)
    {
      $builder->add('srvc', BtnChoiceType::class, [
          'choices'       => [
            'service'               => 'srvc',
            'stuff'                 => 'stff',
            'null_service_stuff'    => 'null',
          ],
          'multiple'      => true,
          'required'      => false,
      ]);
    }

    if ($expires_at_enabled)
    {
      $builder->add('ve', BtnChoiceType::class, [
        'choices'       => [
          'valid' => 'valid',
          'expired'  => 'expired',
        ],
        'multiple'      => true,
        'required'      => false,
      ]);
    }

    if (count($user_status_choices) > 1)
    {
      $builder->add('us', BtnChoiceType::class, [
        'choices'       => $user_status_choices,
        'multiple'      => true,
        'required'      => false,
      ]);
    }

    $builder->add('user', TypeaheadType::class, [
      'add'       => $typeahead_add,
      'filter'    => 'accounts',
      'required'  => false,
    ]);

    $builder->add('uid', HiddenType::class);

    $this->access_field_subscriber->add(
      access_options: ['user', 'guest'],
      type_options: [
      'multiple' => true,
      'required' => false,
      ],
    );

    $builder->addEventSubscriber($this->access_field_subscriber);

    $action = $this->url_generator->generate($this->vr->get('messages'), $this->pp->ary(), UrlGeneratorInterface::ABSOLUTE_PATH);
    $builder->setAction($action);
  }

  public function getBlockPrefix():string
  {
    return 'f';
  }

  public function getParent():string
  {
    return FilterType::class;
  }
}