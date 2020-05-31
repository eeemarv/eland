<?php declare(strict_types=1);

namespace App\Form\EventSubscriber;

use App\Cnst\AccessCnst;
use App\Form\Input\LblChoiceType;
use App\Service\ConfigService;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class AccessFieldSubscriber implements EventSubscriberInterface
{
    protected ItemAccessService $item_access_service;
    protected PageParamsService $pp;
    protected ConfigService $config_service;
    protected array $access_options = [];

    public function __construct(
        ItemAccessService $item_access_service,
        PageParamsService $pp,
        ConfigService $config_service
    )
    {
        $this->item_access_service = $item_access_service;
        $this->pp = $pp;
        $this->config_service = $config_service;
    }

    public static function getSubscribedEvents()
    {
        return [
            FormEvents::PRE_SET_DATA => 'pre_set_data',
            FormEvents::PRE_SUBMIT => 'pre_submit',
        ];
    }

    public function set_object_access_options(array $access_options):void
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

            $this->access_options[$access] = $access;
        }
    }

    public function pre_set_data(FormEvent $event)
    {
        $form = $event->getForm();
        $data = $event->getData();

        if (count($this->access_options) < 2)
        {
            return;
        }

        $options = [
            'choices'   => $this->access_options,
        ];

        if (isset($data->access))
        {
            if ($data->access === 'guest'
                && !$this->config_service->get_intersystem_en($this->pp->schema())
            )
            {
                $options['data'] = 'user';
            }
        }

        $form->add('access', LblChoiceType::class, $options);
    }

    public function pre_submit(FormEvent $event)
    {
        $data = $event->getData();

        if (isset($data->access))
        {
            if (!in_array($data->access, $this->access_options))
            {
                throw new UnexpectedTypeException('Unexpected type in ' . __CLASS__, 'access');
            }

            return;
        }

        if (count($this->access_options) === 1)
        {
            $data->access = reset($this->access_options);
            $event->setData($data);
            return;
        }

        if (!count($this->access_options))
        {
            throw new InvalidConfigurationException('No access options in ' . __CLASS__);
        }
    }
}