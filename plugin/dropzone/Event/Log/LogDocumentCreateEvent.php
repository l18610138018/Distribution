<?php

namespace Icap\DropzoneBundle\Event\Log;

use Claroline\CoreBundle\Event\Log\AbstractLogResourceEvent;
use Claroline\CoreBundle\Event\Log\LogGenericEvent;
use Icap\DropzoneBundle\Entity\Document;
use Icap\DropzoneBundle\Entity\Drop;
use Icap\DropzoneBundle\Entity\Dropzone;

class LogDocumentCreateEvent extends AbstractLogResourceEvent
{
    const ACTION = 'resource-icap_dropzone-document_create';

    /**
     * @param Dropzone $dropzone
     * @param Drop     $drop
     * @param Document $document
     */
    public function __construct(Dropzone $dropzone, Drop $drop, Document $document)
    {
        $documentsDetails = [];
        foreach ($drop->getDocuments() as $document) {
            $documentsDetails[] = $document->toArray();
        }

        $details = [
            'dropzone' => [
                'id' => $dropzone->getId(),
            ],
            'drop' => [
                'id' => $drop->getId(),
                'documents' => $documentsDetails,
            ],
            'document' => $document->toArray(),
        ];

        parent::__construct($dropzone->getResourceNode(), $details);
    }

    /**
     * @return array
     */
    public static function getRestriction()
    {
        return [LogGenericEvent::DISPLAYED_WORKSPACE];
    }
}
