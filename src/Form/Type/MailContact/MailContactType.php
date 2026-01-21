<?php declare(strict_types=1);

namespace App\Form\Type\MailContact;

use App\Repository\UserRepository;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Contracts\Translation\TranslatorInterface;

class MailContactType extends AbstractType
{
  private readonly bool $has_to_adr;
  private readonly bool $has_from_adr;

  public function __construct(
    private readonly TranslatorInterface $translator,
    private readonly ConfigService $config_service,
    private readonly PageParamsService $pp,
    private readonly SessionUserService $su,
    private readonly UserRepository $user_repository,
  )
  {
  }

  private function has_to_adr(
    int $to_user_id,
  ):bool
  {
    if (!isset($this->has_to_adr))
    {
      $to_adr = $this->user_repository->get_email_addresses(
        user_id: $to_user_id,
        schema: $this->pp->schema_o(),
        active_only: false,
      );
      $this->has_to_adr = $to_adr->count() > 0;
    }
    return $this->has_to_adr;
  }

  private function has_from_adr():bool
  {
    if (!isset($this->has_from_adr))
    {
      $from_adr = $this->user_repository->get_email_addresses(
        user_id: $this->su->id(),
        schema: $this->su->schema_o(),
        active_only: false,
      );
      $this->has_from_adr = $from_adr->count() > 0;
    }
    return $this->has_from_adr;
  }

  private function add_error(Form $form, string $message):void
  {
    $form->addError(new FormError($this->translator->trans($message)));
  }

  public function buildForm(
    FormBuilderInterface $builder,
    array $options,
  ):void
  {
    $check_form_enabled = function(FormEvent $event) use ($options)
    {
      $form = $event->getForm();

      if (!$this->config_service->get_bool(
        config_id: 'mail.enabled',
        schema: $this->pp->schema_o(),
      ))
      {
        $this->add_error($form, 'mail_contact.disabled.system');
        return;
      }

      if ($this->su->is_master())
      {
        $this->add_error($form, 'mail_contact.disabled.master');
        return;
      }

      if (!$this->has_to_adr(
        to_user_id: $options['to_user_id']
      ))
      {
        $this->add_error($form, 'mail_contact.disabled.no_to');
        return;
      }

      if (!$this->has_from_adr())
      {
        $this->add_error($form, 'mail_contact.disabled.no_from');
        return;
      }

      if ($this->su->id() === $options['to_user_id']
        && $this->su->schema() === $this->pp->schema())
      {
        $this->add_error($form, 'mail_contact.disabled.self');
        return;
      }
    };

    $options['disabled'] = true;

    $builder->add('message', TextareaType::class);

    $builder->add('cc', CheckboxType::class, [
      'attr'  => [
        'checked'   => true,
      ],
    ]);

    $builder->add('submit', SubmitType::class);

    $builder->addEventListener(FormEvents::POST_SET_DATA, $check_form_enabled);
    $builder->addEventListener(FormEvents::POST_SUBMIT, $check_form_enabled);
  }

  public function configureOptions(OptionsResolver $resolver):void
  {
    $resolver->setDefault('to_user_id', null);
    $resolver->setAllowedTypes('to_user_id', 'int');

    $resolver->setDefault('disabled', function(Options $options){
      if (!$this->config_service->get_bool('mail.enabled', $this->pp->schema_o()))
      {
        return true;
      }
      if ($this->su->is_master())
      {
        return true;
      }
      if (!isset($options['to_user_id']))
      {
        return true;
      }
      if (!$this->has_to_adr(
        to_user_id: $options['to_user_id'],
      ))
      {
        return true;
      }
      if (!$this->has_from_adr())
      {
        return true;
      }
      if ($this->su->id() === $options['to_user_id']
        && $this->su->schema() === $this->pp->schema())
      {
        return true;
      }
      return false;
    });
  }
}