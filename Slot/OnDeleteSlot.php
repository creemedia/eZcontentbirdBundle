<?php
namespace creemedia\Bundle\eZcontentBirdBundle\Slot;

 use eZ\Publish\Core\SignalSlot\Slot as BaseSlot;
 use eZ\Publish\Core\SignalSlot\Signal;
 use eZ\Publish\API\Repository\ContentService;
 use Symfony\Component\DependencyInjection\ContainerInterface as Container;

 class OnDeleteSlot extends BaseSlot
 {
     /**
      * @var \eZ\Publish\API\Repository\ContentService
      */
     private $contentService;

     public function __construct( ContentService $contentService, Container $container )
     {
         $this->container = $container;
         $this->contentService = $contentService;
     }

     public function receive( Signal $signal )
     {
		 if ($signal instanceof Signal\ContentService\DeleteContentSignal) {
			$this->contentBirdService = $this->container->get('cmcontentbirdconnector.service.api');
			$this->contentBirdService->contentStatus($signal->contentId, date("Y-m-d") ,'deleted' );
		 }

	 }
 }
