<?php declare(strict_types=1);

namespace App\Form\Extension;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use App\Form\Extension\FormTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class FormTokenValidationSubscriber implements EventSubscriberInterface
{
    protected FormTokenManagerInterface $form_token_manager;
    protected TranslatorInterface $translator;

    public function __construct(
        FormTokenManagerInterface $form_token_manager,
        TranslatorInterface $translator
    )
    {
        $this->form_token_manager = $form_token_manager;
        $this->translator = $translator;
    }

    public static function getSubscribedEvents()
    {
        return [
            FormEvents::PRE_SUBMIT => 'pre_submit',
        ];
    }

    public function pre_submit(FormEvent $event)
    {
        $form = $event->getForm();

        if ($form->isRoot() && $form->getConfig()->getOption('compound'))
        {
            $data = $event->getData();

            $error_message = $this->form_token_manager->get_error_message($data[FormTokenManagerInterface::NAME]);

            if ($error_message)
            {
                if (null !== $this->translator)
                {
                    $error_message = $this->translator->trans($error_message);
                }

                $form->addError(new FormError($error_message));
            }

            if (is_array($data))
            {
                unset($data[FormTokenManagerInterface::NAME]);
                $event->setData($data);
            }
        }
    }
}
