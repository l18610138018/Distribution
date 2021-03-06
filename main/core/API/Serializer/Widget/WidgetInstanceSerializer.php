<?php

namespace Claroline\CoreBundle\API\Serializer\Widget;

use Claroline\AppBundle\API\Serializer\SerializerTrait;
use Claroline\AppBundle\API\SerializerProvider;
use Claroline\AppBundle\Persistence\ObjectManager;
use Claroline\CoreBundle\Entity\DataSource;
use Claroline\CoreBundle\Entity\Widget\Type\AbstractWidget;
use Claroline\CoreBundle\Entity\Widget\Widget;
use Claroline\CoreBundle\Entity\Widget\WidgetInstance;
use Claroline\CoreBundle\Entity\Widget\WidgetInstanceConfig;
use JMS\DiExtraBundle\Annotation as DI;

/**
 * @DI\Service("claroline.serializer.widget_instance")
 * @DI\Tag("claroline.serializer")
 */
class WidgetInstanceSerializer
{
    use SerializerTrait;

    /** @var ObjectManager */
    private $om;

    /** @var SerializerProvider */
    private $serializer;

    /**
     * WidgetInstanceSerializer constructor.
     *
     * @DI\InjectParams({
     *     "om"         = @DI\Inject("claroline.persistence.object_manager"),
     *     "serializer" = @DI\Inject("claroline.api.serializer")
     * })
     *
     * @param ObjectManager      $om
     * @param SerializerProvider $serializer
     */
    public function __construct(
        ObjectManager $om,
        SerializerProvider $serializer)
    {
        $this->om = $om;
        $this->serializer = $serializer;
    }

    public function getClass()
    {
        return WidgetInstance::class;
    }

    public function serialize(WidgetInstance $widgetInstance, array $options = []): array
    {
        $widget = $widgetInstance->getWidget();
        $dataSource = $widgetInstance->getDataSource();

        // retrieves the custom configuration of the widget if any
        $parameters = new \stdClass();

        if ($widget->getClass()) {
            // loads configuration entity for the current instance
            $typeParameters = $this->om
                ->getRepository($widget->getClass())
                ->findOneBy(['widgetInstance' => $widgetInstance]);

            if ($typeParameters) {
                // serializes custom configuration
                $parameters = $this->serializer->serialize($typeParameters, $options);
            }
        }

        return [
            'id' => $this->getUuid($widgetInstance, $options),
            'type' => $widget->getName(),
            'source' => $dataSource ? $dataSource->getName() : null,
            'parameters' => $parameters,
        ];
    }

    public function deserialize($data, WidgetInstance $widgetInstance, array $options = []): WidgetInstance
    {
        $widgetInstanceConfig = $widgetInstance->getWidgetInstanceConfigs()[0];

        if (!$widgetInstanceConfig) {
            $widgetInstanceConfig = new WidgetInstanceConfig();
            $widgetInstanceConfig->setWidgetInstance($widgetInstance);
        }

        $this->sipe('id', 'setUuid', $data, $widgetInstance);
        $this->sipe('type', 'setType', $data, $widgetInstanceConfig);

        $this->om->persist($widgetInstanceConfig);

        /** @var Widget $widget */
        $widget = $this->om
            ->getRepository(Widget::class)
            ->findOneBy(['name' => $data['type']]);

        if ($widget) {
            $widgetInstance->setWidget($widget);

            // process custom configuration of the widget if any
            if ($data['parameters'] && $widget->getClass()) {
                // loads configuration entity for the current instance
                $typeParameters = $this->om
                    ->getRepository($widget->getClass())
                    ->findOneBy(['widgetInstance' => $widgetInstance]);

                $parametersClass = $widget->getClass();

                if (!$typeParameters) {
                    // no existing parameters => initializes one

                    /** @var AbstractWidget $typeParameters */
                    $typeParameters = new $parametersClass();
                }

                // deserializes custom config and link it to the instance
                $typeParameters = $this->serializer
                  ->get($parametersClass)
                  ->deserialize($data['parameters'], $typeParameters, $options);
                $typeParameters->setWidgetInstance($widgetInstance);

                // We either do this or cascade persist ¯\_(ツ)_/¯
                $this->om->persist($typeParameters);
                $this->om->persist($widgetInstance);
            }
        }

        if (!empty($data['source'])) {
            $dataSource = $this->om
                ->getRepository(DataSource::class)
                ->findOneBy(['name' => $data['source']]);

            $widgetInstance->setDataSource($dataSource);
        }

        return $widgetInstance;
    }
}
