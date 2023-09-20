<?php declare(strict_types=1);

namespace App\Form\Type\Messages;

use App\Command\Messages\MessagesFilterCommand;
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
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class MessagesFilterType extends AbstractType
{
    public function __construct(
        protected ConfigService $config_service,
        protected ItemAccessService $item_access_service,
        protected UrlGeneratorInterface $url_generator,
        protected AccessFieldSubscriber $access_field_subscriber,
        protected PageParamsService $pp,
        protected VarRouteService $vr
    )
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options):void
    {
        $service_stuff_enabled = $this->config_service->get_bool('messages.fields.service_stuff.enabled', $this->pp->schema());
        $category_enabled = $this->config_service->get_bool('messages.fields.category.enabled', $this->pp->schema());
        $expires_at_enabled = $this->config_service->get_bool('messages.fields.expires_at.enabled', $this->pp->schema());

        $new_users_enabled = $this->config_service->get_bool('users.new.enabled', $this->pp->schema());
        $leaving_users_enabled = $this->config_service->get_bool('users.leaving.enabled', $this->pp->schema());

        $show_new_status = $new_users_enabled;

        if ($show_new_status)
        {
            $new_users_access = $this->config_service->get_str('users.new.access', $this->pp->schema());
            $show_new_status = $this->item_access_service->is_visible($new_users_access);
        }

        $show_leaving_status = $leaving_users_enabled;

        if ($show_leaving_status)
        {
            $leaving_users_access = $this->config_service->get_str('users.leaving.access', $this->pp->schema());
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
            $typeahead_add[] = ['accounts', ['status' => 'intersystem']];
        }

        if ($this->pp->is_admin())
        {
            $typeahead_add[] = ['accounts', ['status' => 'pre-active']];
            $typeahead_add[] = ['accounts', ['status' => 'post-active']];
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

        $this->access_field_subscriber->add('access', ['user', 'guest'], [
            'multiple' => true,
            'required' => false,
        ]);

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

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefault('data_class', MessagesFilterCommand::class);
    }
}