<?php

namespace Icap\BadgeBundle\Form\Type\Tool\Workspace;

use Icap\BadgeBundle\Entity\Badge;
use Icap\BadgeBundle\Form\Type\BadgeTranslationType;
use Claroline\CoreBundle\Library\Configuration\PlatformConfigurationHandler;
use Claroline\CoreBundle\Manager\LocaleManager;
use JMS\DiExtraBundle\Annotation as DI;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @DI\FormType(alias="badge_form_workspace")
 */
class BadgeType extends AbstractType
{
    /** @var \Icap\BadgeBundle\Form\Type\Tool\Workspace\BadgeRuleType */
    private $badgeRuleType;

    /** @var \Claroline\CoreBundle\Manager\LocaleManager */
    private $localeManager;

    /** @var \Claroline\CoreBundle\Library\Configuration\PlatformConfigurationHandler */
    private $platformConfigHandler;

    /**
     * @DI\InjectParams({
     *     "badgeRuleType"         = @DI\Inject("icap_badge.form.badge.workspace.rule"),
     *     "localeManager"         = @DI\Inject("claroline.manager.locale_manager"),
     *     "platformConfigHandler" = @DI\Inject("claroline.config.platform_config_handler")
     * })
     */
    public function __construct(BadgeRuleType $badgeRuleType, LocaleManager $localeManager, PlatformConfigurationHandler $platformConfigHandler)
    {
        $this->badgeRuleType = $badgeRuleType;
        $this->localeManager = $localeManager;
        $this->platformConfigHandler = $platformConfigHandler;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $platformLanguage = $this->platformConfigHandler->getParameter('locale_language');
        $languages = array_values($this->localeManager->getAvailableLocales());
        $sortedLanguages = array();

        usort($languages, function ($language1, $language2) use ($platformLanguage) {
            if ($language1 === $platformLanguage) {
                return -1;
            } elseif ($language2 === $platformLanguage) {
                return 1;
            } else {
                return 0;
            }
        });

        $translationBuilder = $builder->create('translations', 'form', array('virtual' => true));

        foreach ($languages as $language) {
            $fieldName = sprintf('%sTranslation', $language);
            $translationBuilder->add($fieldName, new BadgeTranslationType());
        }

        $builder
            ->add($translationBuilder)
            ->add('automatic_award', CheckboxType::class, array('required' => false))
            ->add(FileType::class, FileType::class, array('label' => 'badge_form_image'))
            ->add('is_expiring', CheckboxType::class, array('required' => false))
            ->add('expire_duration', IntegerType::class, array('attr' => array(
                      'class' => 'input-sm',
                      'min' => 1,
                ),
            ))
            ->add('expire_period', ChoiceType::class,
                array(
                    'choices' => Badge::getExpirePeriodLabels(),
                    'attr' => array('class' => 'input-sm'),
                )
            );

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            /** @var \Icap\BadgeBundle\Entity\Badge $badge */
            $badge = $event->getData();

            if ($badge && null !== $badge) {
                $this->badgeRuleType
                    ->setWorkspaceId($badge->getWorkspace()->getId())
                    ->setBadgeId($badge->getId());

                $form = $event->getForm();
                $form
                    ->add(
                        'rules',
                        'collection',
                        array(
                            'type' => $this->badgeRuleType,
                            'by_reference' => false,
                            'attr' => array('class' => 'rule-collections'),
                            'attr' => array('label_width' => 'col-md-3'),
                            'prototype' => true,
                            'allow_add' => true,
                            'allow_delete' => true,
                        )
                    );
            }

        });
    }

    public function getName()
    {
        return 'badge_form_workspace';
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            array(
                'data_class' => 'Icap\BadgeBundle\Entity\Badge',
                'translation_domain' => 'icap_badge',
                'language' => 'en',
                'date_format' => DateType::HTML5_FORMAT,
                'cascade_validation' => true,
            )
        );
    }
}
