<?php declare(strict_types=1);

namespace App\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\Extension\Core\Type\FormType;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Util\ServerParams;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatorInterface;

use App\Form\Extension\EtokenManagerInterface;
use App\Form\Extension\EtokenValidationSubscriber;

class FormTypeEtokenExtension extends AbstractTypeExtension
{
    private $etokenManager;
    private $translator;

    public function __construct(
        EtokenManagerInterface $etokenManager,
        TranslatorInterface $translator = null
    )
    {
        $this->etokenManager = $etokenManager;
        $this->translator = $translator;
    }

    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    )
    {
        if (!$options['etoken_enabled'])
        {
            return;
        }

        $builder->addEventSubscriber(new EtokenValidationSubscriber(
            $this->etokenManager,
            $this->translator
        ));
    }

    public function finishView(
        FormView $view,
        FormInterface $form,
        array $options
    )
    {
        if ($options['etoken_enabled'] && !$view->parent && $options['compound'])
        {
            $factory = $form->getConfig()->getFormFactory();

            $value = (string) $this->etokenManager->get();

            $etokenForm = $factory->createNamed(
                '_etoken',
                'Symfony\Component\Form\Extension\Core\Type\HiddenType',
                $value,
                [
                    'mapped' => false,
                ]
            );

            $view->children['_etoken'] = $etokenForm->createView($view);
        }
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'etoken_enabled' => true,
        ]);
    }

    public static function getExtendedTypes():iterable
    {
        yield FormType::class;
    }

    public function getDefaultOptions(array $options)
    {
        return [
            'etoken_enabled'        => true,
        ];
    }
}
