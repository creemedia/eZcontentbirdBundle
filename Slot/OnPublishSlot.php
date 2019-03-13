<?php

namespace creemedia\Bundle\eZcontentbirdBundle\Slot;

use eZ\Publish\Core\SignalSlot\Slot as BaseSlot;
use eZ\Publish\Core\SignalSlot\Signal;
use eZ\Publish\API\Repository\ContentService;
use Symfony\Component\DependencyInjection\ContainerInterface as Container;

class OnPublishSlot extends BaseSlot
{
    /**
     * @var \eZ\Publish\API\Repository\ContentService
     */
    private $contentService;
    private $contentMoverService;

    public function __construct(ContentService $contentService, Container $container)
    {
        $this->container = $container;
        $this->contentService = $contentService;
        $this->contentMoverService = $container->get('cmcontentbirdconnector.service.mover');
    }

    public function receive(Signal $signal)
    {
        if ($signal instanceof Signal\ContentService\PublishVersionSignal) {
            $content = $this->contentService->loadContent($signal->contentId);
            $contentId = $content->versionInfo->contentInfo->contentTypeId;

            if ($contentId !== 2) {
                return;
            }

            try {
                $this->contentBirdService = $this->container->get('cmcontentbirdconnector.service.api');
                $this->contentBirdService->contentStatus($signal->contentId, date("Y-m-d"), 'published');
            } catch (Exception $e) {
                // ERROR
            }

            $this->contentMoverService->checkContentEmbeds($content);

        }
    }
}
