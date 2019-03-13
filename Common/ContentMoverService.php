<?php

namespace creemedia\Bundle\eZcontentbirdBundle\Common;

use Symfony\Component\DependencyInjection\ContainerInterface as Container;
use eZ\Publish\API\Repository\Repository;

class ContentMoverService
{
	private $container;
	private $repository;
	private $contentService;
	private $locationService;

	public function __construct(Container $container, Repository $repository)
	{
		$this->container = $container;
		$this->repository = $repository;
		$this->contentService = $repository->getContentService();
		$this->locationService = $repository->getLocationService();
	}

	/**
	 * @param $content
	 */
	public function checkContentEmbeds($content)
	{
		$document = new \DOMDocument();
		$contentLocationId = $content->versionInfo->contentInfo->mainLocationId;

		$document->loadXML($content->getField('text')->value);
		$embeds = $document->getElementsByTagName('ezembed');

		/** @var \DOMElement $div */
		foreach ($embeds as $embed) {
			$attr = $embed->getAttribute('xlink:href');
			$contentId = substr(strrchr($attr, "//"), 1);
			$content = $this->contentService->loadContent($contentId);
			$location = $this->locationService->loadLocation($content->versionInfo->contentInfo->mainLocationId);

			if ($location->parentLocationId !== $contentLocationId) {
				$this->move($location, $this->locationService->loadLocation($contentLocationId));
			}
		}
	}

	private function move($srcLocation, $destinationParentLocation)
	{
		$this->locationService->moveSubtree( $srcLocation, $destinationParentLocation );
	}
}
