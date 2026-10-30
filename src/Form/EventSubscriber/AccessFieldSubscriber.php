<?php declare(strict_types=1);

namespace App\Form\EventSubscriber;

use App\Cnst\AccessCnst;
use App\Form\Type\Field\BtnChoiceType;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class AccessFieldSubscriber implements EventSubscriberInterface
{
  private array $fields_options = [];
  private array $fields_disabled = [];
  private array $fields_multiple = [];
  private array $fields_required = [];

  public function __construct(
    private readonly PageParamsService $pp,
    private readonly ConfigService $config_service
  )
  {
  }

  public static function getSubscribedEvents(): array
  {
    return [
      FormEvents::PRE_SET_DATA => 'pre_set_data',
      FormEvents::PRE_SUBMIT => 'pre_submit',
    ];
  }

  public function add(
    string $name = 'access',
    array $access_options = ['admin', 'user', 'guest'],
    bool $required = true,
    bool $multiple = false,
    bool $disabled = false,
  ):void
  {
    $access_options = array_combine($access_options, $access_options);

    if (!$this->config_service->get_intersystem_en(
      schema: $this->pp->schema_o(),
    ))
    {
      unset($access_options['guest']);
    }

    if ($this->pp->is_guest())
    {
      unset($access_options['admin']);
      unset($access_options['user']);
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

    $this->fields_disabled[$name] = $disabled;
    $this->fields_required[$name] = $required;
    $this->fields_multiple[$name] = $multiple;
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
      if (!count($access_options))
      {
        continue;
      }

      if (count($access_options) === 1)
      {
        continue;
      }

      $options = [
        'choices'   => $access_options,
        'multiple'  => $this->fields_multiple[$name],
        'required'  => $this->fields_required[$name],
        'disabled'  => $this->fields_disabled[$name],
      ];

      if (isset($data->$name))
      {
        if ($data->$name === 'guest'
          && !$this->config_service->get_intersystem_en(
            schema: $this->pp->schema_o(),
          )
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
      if ($this->fields_multiple[$name])
      {
        if (!isset($data[$name]))
        {
          continue;
        }

        if (is_string($data[$name]))
        {
          if (!in_array($data[$name], $access_options))
          {
            throw new UnexpectedTypeException($data[$name], 'access');
          }

          $update = true;

          continue;
        }

        foreach ($data[$name] as $access_option)
        {
          if (!isset($access_option))
          {
            continue;
          }

          if (!in_array($access_option, $access_options))
          {
            throw new UnexpectedTypeException($access_option, 'access');
          }
        }
      }
      else
      {
        if (isset($data[$name]))
        {
          if (!is_string($data[$name]))
          {
            continue;
          }

          if (!in_array($data[$name], $access_options))
          {
            throw new UnexpectedTypeException($data[$name], 'access');
          }

          continue;
        }

        if (count($access_options) === 1)
        {
          $data[$name] = reset($access_options);
          $update = true;
        }
      }
    }

    if ($update)
    {
      $event->setData($data);
    }
  }
}