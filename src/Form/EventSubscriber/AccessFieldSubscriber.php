<?php declare(strict_types=1);

namespace App\Form\EventSubscriber;

use App\Cnst\AccessCnst;
use App\Form\Type\Simple\BtnChoiceType;
use App\Service\ConfigService;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class AccessFieldSubscriber implements EventSubscriberInterface
{
    protected array $access_options = [];
    protected string $name = 'access';
    protected array $fields_options = [];

    public function __construct(
        protected ItemAccessService $item_access_service,
        protected PageParamsService $pp,
        protected ConfigService $config_service
    )
    {
    }

    public static function getSubscribedEvents()
    {
        return [
            FormEvents::PRE_SET_DATA => 'pre_set_data',
            FormEvents::PRE_SUBMIT => 'pre_submit',
        ];
    }

    public function add(string $name, array $access_options):void
    {
        $access_options = array_combine($access_options, $access_options);

		if (!$this->config_service->get_intersystem_en($this->pp->schema()))
		{
            unset($access_options['guest']);
        }

        if (!$this->pp->is_admin())
        {
            unset($access_options['admin']);
        }

        foreach(AccessCnst::ACCESS as $access => $access_ary)
        {
            if (!isset($access_options[$access]))
            {
                continue;
            }

            if (!isset($this->fields_options[$name]))
            {
                $this->fields_options[$name] = [
                    $access     => $access,
                ];
                continue;
            }

            $this->fields_options[$name][$access] = $access;
        }
    }

    public function pre_set_data(FormEvent $event)
    {
        if (count($this->fields_options) === 0)
        {
            return;
        }

        $form = $event->getForm();
        $data = $event->getData();

        foreach ($this->fields_options as $name => $access_options)
        {
            if (count($access_options) < 2)
            {
                continue;
            }

            $options = [
                'choices'   => $access_options,
            ];

            if (isset($data->$name))
            {
                if ($data->$name === 'guest'
                    && !$this->config_service->get_intersystem_en($this->pp->schema())
                )
                {
                    $options['data'] = 'user';
                }
            }

            $form->add($name, BtnChoiceType::class, $options);
        }
    }

    public function pre_submit(FormEvent $event)
    {
        $data = $event->getData();

        $update = false;

        foreach ($this->fields_options as $name => $access_options)
        {
            if (isset($data->$name))
            {
                if (!in_array($data->$name, $access_options))
                {
                    throw new UnexpectedTypeException('Unexpected type in ' . __CLASS__, 'access');
                }

                continue;
            }

            if (count($access_options) === 1)
            {
                $data->$name = reset($access_options);
                $update = true;
            }
        }

        if ($update)
        {
            $event->setData($data);
        }
    }
}