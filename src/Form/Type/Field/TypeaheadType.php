<?php declare(strict_types=1);

namespace App\Form\Type\Field;

use App\Cache\ConfigCache;
use App\Form\DataTransformer\TypeaheadUserTransformer;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use App\Service\TypeaheadService;
use Symfony\Component\Form\Exception\InvalidConfigurationException;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\Options;

class TypeaheadType extends AbstractType
{
    public function __construct(
        protected TypeaheadService $typeahead_service,
        protected PageParamsService $pp,
        protected ConfigCache $config_cache,
        protected ItemAccessService $item_access_service,
        protected TypeaheadUserTransformer $typeahead_user_transformer
    )
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options):void
    {
        parent::buildForm($builder, $options);

        if (isset($options['filter']) && $options['filter'] === 'accounts')
        {
            $builder->addModelTransformer($this->typeahead_user_transformer);
        }
    }

    public function buildView(FormView $view, FormInterface $form, array $options):void
    {
        $add = $options['add'];

        $this->typeahead_service->ini($this->pp);

        if (is_string($add))
        {
            $this->typeahead_service->add($add, []);
        }
        else if (is_array($add))
        {
            foreach ($add as $add_item)
            {
                if (is_string($add_item))
                {
                    $this->typeahead_service->add($add_item, []);
                }
                else if (is_array($add_item))
                {
                    [$typeahead_route, $params] = $add_item;

                    if (!is_string($typeahead_route))
                    {
                        throw new UnexpectedTypeException($typeahead_route, 'string');
                    }

                    if (!is_array($params))
                    {
                        throw new UnexpectedTypeException($params, 'array');
                    }

                    $this->typeahead_service->add($typeahead_route, $params);

                    if (isset($params['remote_schema']))
                    {
                        $remote_schema = $params['remote_schema'];
                    }
                }
                else
                {
                    throw new UnexpectedTypeException($add_item, 'string|array');
                }
            }
        }
        else
        {
            throw new UnexpectedTypeException($add, 'string|array');
        }

        $process_ary = [];

        if (isset($options['render_omit']))
        {
            $process_ary = [
                'render'    => [
                    'check'     => 10,
                    'omit'      => $options['render_omit'],
                ],
            ];
        }

        if (isset($options['filter']) && $options['filter'] === 'accounts')
        {
            if (count($process_ary))
            {
                throw new InvalidConfigurationException('Either filter or render_omit can be configured, not both options.');
            }

            $schema = $remote_schema ?? $this->pp->schema();

            $new_users_days = $this->config_cache->get_int('users.new.days', $schema);
            $new_users_enabled = $this->config_cache->get_bool('users.new.enabled', $schema);
            $leaving_users_enabled = $this->config_cache->get_bool('users.leaving.enabled', $schema);

            $show_new_status = $new_users_enabled;

            if ($show_new_status)
            {
                $new_users_access = $this->config_cache->get_str('users.new.access', $schema);
                $show_new_status = $this->item_access_service->is_visible($new_users_access);
            }

            $show_leaving_status = $leaving_users_enabled;

            if ($show_leaving_status)
            {
                $leaving_users_access = $this->config_cache->get_str('users.leaving.access', $schema);
                $show_leaving_status = $this->item_access_service->is_visible($leaving_users_access);
            }

            $process_ary['filter'] = 'accounts';
            $process_ary['new_users_days'] = $new_users_days;
            $process_ary['show_new_status'] = $show_new_status;
            $process_ary['show_leaving_status'] = $show_leaving_status;
        }

        $data_typeahead = $this->typeahead_service->str_raw($process_ary);

        parent::buildView($view, $form, $options);

        $view->vars['attr'] = [
            ...$options['attr'],
            'data-typeahead'    => $data_typeahead,
            'autocomplete'      => 'off',
        ];

        if (isset($process_ary['render']))
        {
            $view->vars['render_omit'] = true;
        }
    }

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefault('add', null);
        $resolver->setDefault('filter', null);
        $resolver->setDefault('render_omit', null);
        $resolver->setRequired('add');
        $resolver->setAllowedTypes('add', ['string', 'array']);
        $resolver->setAllowedTypes('filter', ['null', 'string']);
        $resolver->setAllowedTypes('render_omit', ['null', 'string']);
        $resolver->setAllowedValues('filter', [null, 'accounts']);

        $resolver->setDefault('invalid_message', function(Options $options){
            if (isset($options['filter']) && $options['filter'] === 'accounts')
            {
                return 'user.code_not_exists';
            }

            return 'This value is not valid.';
        });
    }

    public function getParent():string
    {
        return TextType::class;
    }

    public function getBlockPrefix():string
    {
        return 'typeahead';
    }
}