<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Form\DataTransformer\IntegerTransformer;
use Symfony\Component\Form\Exception\InvalidConfigurationException;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormBuilderInterface;

class HiddenIntegerType extends AbstractType
{
    public function __construct(
        protected IntegerTransformer $integer_transformer
    )
    {
    }

    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ):void
    {
        parent::buildForm($builder, $options);
        $builder->addModelTransformer($this->integer_transformer);
    }

    /*
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

            $new_users_days = $this->config_service->get_int('users.new.days', $schema);
            $new_users_enabled = $this->config_service->get_bool('users.new.enabled', $schema);
            $leaving_users_enabled = $this->config_service->get_bool('users.leaving.enabled', $schema);

            $show_new_status = $new_users_enabled;

            if ($show_new_status)
            {
                $new_users_access = $this->config_service->get_str('users.new.access', $schema);
                $show_new_status = $this->item_access_service->is_visible($new_users_access);
            }

            $show_leaving_status = $leaving_users_enabled;

            if ($show_leaving_status)
            {
                $leaving_users_access = $this->config_service->get_str('users.leaving.access', $schema);
                $show_leaving_status = $this->item_access_service->is_visible($leaving_users_access);
            }

            $process_ary['filter'] = 'accounts';
            $process_ary['new_users_days'] = $new_users_days;
            $process_ary['show_new_status'] = $show_new_status;
            $process_ary['show_leaving_status'] = $show_leaving_status;
        }

        $data_typeahead = $this->typeahead_service->str_raw($process_ary);

        parent::buildView($view, $form, $options);

        $view->vars['attr'] = array_merge($options['attr'], [
            'data-typeahead'    => $data_typeahead,
            'autocomplete'      => 'off',
        ]);

        if (isset($process_ary['render']))
        {
            $view->vars['render_omit'] = true;
        }
    }
    */

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefault('invalid_message', 'type.not_an_integer');
    }

    public function getParent():string
    {
        return HiddenType::class;
    }
}