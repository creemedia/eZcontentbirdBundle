<?php

namespace creemedia\Bundle\eZcontentbirdBundle\Common;

use Symfony\Component\DependencyInjection\ContainerInterface as Container;
use eZ\Publish\API\Repository\Repository;
use CM\ExtendedImageBundle\eZ\Publish\FieldType\ExtendedImage\Value;
use eZ\Publish\API\Repository\Values\Content\Query;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion;

class CmsService
{
	private $container;
	private $repository;
	private $contentService;
	private $locationService;
	private $contentTypeService;
	private $userService;
	private $searchService;

	protected $allowedAdditionFields = ['summary', 'image'];

	const IMAGE_LOCATION_TO_UPLOAD = '274569';
	const RATGEBER_LOCATION_ID = 257441;

	public function __construct(Container $container, Repository $repository)
	{
		$this->container = $container;
		$this->repository = $repository;
		$this->contentService = $this->repository->getContentService();
		$this->locationService = $this->repository->getLocationService();
		$this->contentTypeService = $this->repository->getContentTypeService();
		$this->userService = $this->repository->getUserService();
		$this->searchService = $this->repository->getSearchService();
	}

	public function createContent($parentLocation, $contentType, $title, $text, $post_status, $userId, $keywords, $additionalFields)
	{
		$user = $this->userService->loadUser($userId);
		$this->repository->setCurrentUser($user);

		$contentType = $this->contentTypeService->loadContentTypeByIdentifier($contentType);
		$contentCreateStruct = $this->contentService->newContentCreateStruct($contentType, 'eng-GB');

		if (!empty($keywords)) {
			$contentCreateStruct->setField('cb_keywords', implode(';', $keywords));
		}

		$contentCreateStruct->setField('title', $title);
		$contentCreateStruct->setField('text', '<section xmlns="http://ez.no/namespaces/ezpublish5/xhtml5/edit">' . $text . '</section>');

		$contentCreateStruct = $this->handleAdditionalFields($additionalFields, $contentCreateStruct);

		$locationCreateStruct = $this->locationService->newLocationCreateStruct($parentLocation[0]);

		$draft = $this->contentService->createContent($contentCreateStruct, array($locationCreateStruct));

		return $draft;
	}

	private function handleAdditionalFields($fields, $contentCreateStruct)
	{
		foreach ($fields as $field) {
			if (in_array($field['name'], $this->allowedAdditionFields)) {
				if ($field['name'] === 'summary') { // BEI SUMMARY MUSS es in p liegen
					$contentCreateStruct->setField($field['name'], '<section xmlns="http://ez.no/namespaces/ezpublish5/xhtml5/edit"><p>' . $field['content'] . '</p></section>');
				} else if (isset($field['content'])) {
					$contentCreateStruct->setField($field['name'], $field['content']);
				}
			}
		}
		return $contentCreateStruct;
	}

	public function updateContent($contentId, $newBody, $newTitle, $keywords, $additionalFields)
	{
		$contentInfo = $this->contentService->loadContentInfo($contentId);
		$userId = $this->userService->loadUser($contentInfo->ownerId);
		$this->repository->setCurrentUser($userId);

		if ($contentInfo->isPublished()) {
			$contentDraft = $this->contentService->createContentDraft($contentInfo);
		} else if ($contentInfo->isDraft()) {
			$versionInfo = $this->contentService->loadVersionInfoById($contentId);
		}

		$contentUpdateStruct = $this->contentService->newContentUpdateStruct();
		$contentUpdateStruct->initialLanguageCode = 'eng-GB';

		if ($newTitle) {
			$contentUpdateStruct->setField('title', $newTitle);
		}

		if (!empty($keywords)) {
			$contentUpdateStruct->setField('cb_keywords', implode(';', $keywords));
		} else {
			$contentUpdateStruct->setField('cb_keywords', '');
		}

		$contentUpdateStruct->setField('text', '<section xmlns="http://ez.no/namespaces/ezpublish5/xhtml5/edit">' . $newBody . '</section>');
		$contentUpdateStruct = $this->handleAdditionalFields($additionalFields, $contentUpdateStruct);

		if ($contentInfo->isPublished()) {
			$contentDraft = $this->contentService->updateContent($contentDraft->versionInfo, $contentUpdateStruct);
		} else if ($contentInfo->isDraft()) {
			$contentDraft = $this->contentService->updateContent($versionInfo, $contentUpdateStruct);
		}
		return $contentDraft;
	}

	public function uploadImage($imageName, $fileName, $alternativeText)
	{
		$this->repository->setCurrentUser($this->userService->loadUser(14));
		$contentType = $this->contentTypeService->loadContentTypeByIdentifier("image");
		$contentCreateStruct = $this->contentService->newContentCreateStruct($contentType, 'eng-GB');

		$contentCreateStruct->setField('name', $fileName);

		$value = new Value([
			'path' => $imageName,
			'fileSize' => filesize($imageName),
			'fileName' => $fileName,
			'alternativeText' => $alternativeText,
		]);

		$contentCreateStruct->setField('image', $value);

		$draft = $this->contentService->createContent($contentCreateStruct,
			[$this->locationService->newLocationCreateStruct(self::IMAGE_LOCATION_TO_UPLOAD)]
		);

		return $this->contentService->publishVersion($draft->versionInfo)->id ?? -1;
	}

	public function getAllCategories()
	{
		$ratgeberMainLocation = $this->locationService->loadLocation(self::RATGEBER_LOCATION_ID);

		$query = new Query();
		$query->query = new Criterion\LogicalOr([
			new Criterion\LogicalAnd([
				new Criterion\Subtree($ratgeberMainLocation->pathString),
				new Criterion\Visibility(Criterion\Visibility::VISIBLE),
				new Criterion\ContentTypeIdentifier(['overview', 'dossier'])
			])
		]);

		$query->limit = 2000; // :)

		$data = array_map(function ($hit) {
			return $hit->valueObject;
		}, $this->searchService->findContent($query)->searchHits);
		return $data;
	}

	public function getContentTypes()
	{
		$contentTypeService = $this->repository->getContentTypeService();
		$contentGroup = $contentTypeService->loadContentTypeGroup(1);
		$contentTypes = $contentTypeService->loadContentTypes($contentGroup);

		$types = [];

		for ($i = 0; $i < count($contentTypes); $i++) {
			$typ = $contentTypes[$i]->identifier;
			$typ = ['name' => $typ, 'label' => $typ];

			array_push($types, $typ);
		}

		return $types;
	}

	public function getUsers()
	{
		$query = new Query();
		$query->filter = new Criterion\LogicalAnd([
			new Criterion\ContentId([172776, 172749, 202610])
		]);

		$usersContent = array_map(function ($hit) {
			return $hit->valueObject;
		}, $this->repository->getSearchService()->findContent($query)->searchHits);
		return $usersContent;
	}
}
