<?php declare(strict_types=1);

namespace App\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Form\Extension\FormTokenManagerInterface;
use App\Form\Extension\FormTokenValidationSubscriber;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Contracts\Translation\TranslatorInterface;

class FormTypeFormTokenExtension extends AbstractTypeExtension
{
    protected $formTokenManager;
    protected $translator;

    public function __construct(
        FormTokenManagerInterface $form_token_manager,
        TranslatorInterface $translator
    )
    {
        $this->form_token_manager = $form_token_manager;
        $this->translator = $translator;
    }

    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    )
    {
        if (!$options[FormTokenManagerInterface::FORM_OPTION])
        {
            return;
        }

        $builder->addEventSubscriber(new FormTokenValidationSubscriber(
            $this->form_token_manager,
            $this->translator
        ));
    }

    public function finishView(
        FormView $view,
        FormInterface $form,
        array $options
    )
    {
        if ($options[FormTokenManagerInterface::FORM_OPTION] && !$view->parent && $options['compound'])
        {
            $factory = $form->getConfig()->getFormFactory();

            $value = (string) $this->form_token_manager->get();

            $form_token_form = $factory->createNamed(
                FormTokenManagerInterface::NAME,
                HiddenType::class,
                $value, [
                    'mapped' => false,
                ]
            );

            $view->children[FormTokenManagerInterface::NAME] = $form_token_form->createView($view);
        }
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            FormTokenManagerInterface::FORM_OPTION => true,
        ]);
    }

    public static function getExtendedTypes():iterable
    {
        yield FormType::class;
    }

    public function getDefaultOptions(array $options)
    {
        return [
            FormTokenManagerInterface::FORM_OPTION => true,
        ];
    }
}
