<?php

namespace Icap\BadgeBundle\Form\Type\Widget;

use JMS\DiExtraBundle\Annotation as DI;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @DI\FormType(alias="badge_usage_widget_config")
 */
class BadgeUsageConfigType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('number_last_awarded_badge', IntegerType::class, array(
                'attr' => array(
                    'label_width' => 'col-md-7',
                    'control_width' => 'col-md-2',
                ),
            ))
            ->add('number_most_awarded_badge', IntegerType::class, array(
                'attr' => array(
                    'label_width' => 'col-md-7',
                    'control_width' => 'col-md-2',
                ),
            ))
            ->add('simple_view', CheckboxType::class, array(
                'required' => false,
                'attr' => array(
                    'label_width' => 'col-md-7',
                    'control_width' => 'col-md-2',
                ),
            ));
    }

    public function getName()
    {
        return 'badge_usage_widget_config';
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            array(
                'data_class' => 'Icap\BadgeBundle\Entity\Widget\BadgeUsageConfig',
                'translation_domain' => 'icap_badge',
                'language' => 'en',
            )
        );
    }
}
