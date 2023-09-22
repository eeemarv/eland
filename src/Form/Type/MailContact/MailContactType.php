<?php declare(strict_types=1);

namespace App\Form\Type\MailContact;

use App\Command\Users\UsersMailContactCommand;
use App\Service\ConfigService;
use App\Service\MailAddrUserService;
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
    public function __construct(
        protected TranslatorInterface $translator,
        protected ConfigService $config_service,
        protected PageParamsService $pp,
        protected SessionUserService $su,
        protected MailAddrUserService $mail_addr_user_service
    )
    {
    }

    private function add_error(Form $form, string $message):void
    {
        $form->addError(new FormError($this->translator->trans($message)));
    }

    public function buildForm(FormBuilderInterface $builder, array $options):void
    {
        $check_form_enabled = function(FormEvent $event) use ($options)
        {
            $form = $event->getForm();

            if (!$this->config_service->get_bool('mail.enabled', $this->pp->schema()))
            {
                $this->add_error($form, 'mail_contact.disabled.system');
                return;
            }
            if ($this->su->is_master())
            {
                $this->add_error($form, 'mail_contact.disabled.master');
                return;
            }
            $to_addr = $this->mail_addr_user_service->get($options['to_user_id'], $this->pp->schema());
            if (!count($to_addr))
            {
                $this->add_error($form, 'mail_contact.disabled.no_to');
                return;
            }
            $from_addr = $this->mail_addr_user_service->get($this->su->id(), $this->su->schema());
            if (!count($from_addr))
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
            if (!$this->config_service->get_bool('mail.enabled', $this->pp->schema()))
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
            $to_addr = $this->mail_addr_user_service->get($options['to_user_id'], $this->pp->schema());
            if (!count($to_addr))
            {
                return true;
            }
            $from_addr = $this->mail_addr_user_service->get($this->su->id(), $this->su->schema());
            if (!count($from_addr))
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