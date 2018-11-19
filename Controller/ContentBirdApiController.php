<?php

namespace creemedia\Bundle\eZcontentbirdBundle\Controller;

use eZ\Publish\API\Repository\Values\Content\LocationQuery;
use eZ\Publish\API\Repository\Values\Content\Query;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion;
use eZ\Publish\API\Repository\Values\Content\Query\SortClause;
use eZ\Publish\Core\MVC\Symfony\View\ContentView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use eZ\Publish\API\Repository\Repository;
use Symfony\Component\DependencyInjection\Definition;
use eZ\Bundle\EzPublishCoreBundle\Controller;
use CM\ExtendedImageBundle\eZ\Publish\FieldType\ExtendedImage\Value;
use DOMDocument;
use eZ\Publish\API\Repository\Values\Content;

use Symfony\Component\DependencyInjection\ContainerInterface as Container;

class ContentBirdApiController extends Controller {

	private $apiService;
	private $contentBirdService;
	private $parser;
	private $url;

	const token = 'contentbird.token';
	const PLUGIN_VERSION = '0.4';

	/** Status and error codes */
	const STATUS_OKAY                  = 0;
	const ERROR_NO_TITLE_GIVEN         = 1;
	const ERROR_NO_CONTENT_GIVEN       = 2;
	const ERROR_NO_AUTHOR_GIVEN        = 3;
	const ERROR_NO_STATUS_GIVEN        = 4;
	const ERROR_UNKNOWN_POST_TYPE      = 5;
	const ERROR_UNKNOWN_POST_CATEGORY  = 6;
	const ERROR_CREATE_USER            = 7;
	const ERROR_USER_PERMISSION_DENIED = 8;
	const ERROR_USER_NOT_FOUND         = 9;
	const ERROR_PUBLISH_POST           = 10;
	const ERROR_OBJECT_INVALID         = 11;
	const ERROR_UNKNOWN_METHOD         = 12;
	const ERROR_NOT_FOUND              = 13;
	const ERROR_GENERAL                = 14;
	const ERROR_NO_BODY                = 15;

	public function __construct(Container $container, Repository $repository) {
		$this->repository = $repository;
		$this->container = $container;
		$this->contentBirdService = $this->container->get('cmcontentbirdconnector.service.api');
		$this->parser = $this->container->get('cmcontentbirdconnector.service.parser');
	}

	public function activateAction(Request $request) {
		$host = $request->headers->get('x-forwarded-host') ? preg_replace('/,.*$/', '', $request->headers->get('x-forwarded-host')) : $request->getHost();
		$proto = $request->isSecure()? 'https://' : 'http://';
		$this->contentBirdService->pluginStatus($proto . $host);
		$this->url = $proto . $host;

		return new Response();
	}

	private function handleResponse($payload, $statusCode = null) {
		$response = new JsonResponse();

		if ($payload) {
			$response->setData($payload);
		}
		if ($statusCode) {
			$response->setStatusCode($statusCode);
		}
		return $response;
	}

	private function validateContentCreate($data) {

        if ( empty( $data ) ) {
			return $this->handleResponse(['message' => 'Invalid content data object', 'code' => self::ERROR_OBJECT_INVALID], 422);
        }

        if ( empty( $data['post_title'] ) ) {
			return $this->handleResponse(['message' => 'No title given', 'code' => self::ERROR_NO_TITLE_GIVEN], 422);
        }

        if ( empty( $data['post_content'] ) ) {
			return $this->handleResponse(['message' => 'Invalid content data object', 'code' => self::ERROR_NO_CONTENT_GIVEN], 422);
		}

		return 0;
	}

	public function handleAction(Request $request) {

		$errorResponse = null;

		if (!$request->query->has('token')) {
			return $this->handleResponse(['message' => 'Token wurde nicht gesetzt','code' => self::ERROR_GENERAL], 422);
		}

		if (!$request->query->has('lbcm')) {
			return $this->handleResponse(['message' => 'lbcm/action wurde niht gesetzt','code' => self::ERROR_GENERAL], 405);
		}

		$token = $request->query->get('token');
		$action = $request->query->get('lbcm');

		if ($token !== $this->container->getParameter(self::token)) {
			return $this->handleResponse(['message' => 'Token ist nicht korrekt', 'code' => self::ERROR_GENERAL], 403);
		}

		switch($action) {

			case 'users':
				return $this->usersAction($request);

			case 'meta':
				return $this->metaAction($request);

			case 'content/create':
				return $this->createAction($request);

			case 'content/update':
				return $this->contentUpdateAction($request);

			case 'content/get':
				return $this->getContentAction($request);

			default:
				return $this->handleResponse(['message' => 'Methode nicht gefunden', 'code' => self::ERROR_GENERAL], 404);
		}
	}

	public function statusAction(Request $request) {

		$token = $this->container->getParameter(self::token);

		$inserted = false;
		if (strlen($token) > 0) {
			$inserted = true;
		}

		$res = [

			'message' => $inserted ? 'Plugin richtig installiert' : 'Plugin wurde nicht korrekt installiert',
			'code' => $inserted ? self::STATUS_OKAY : self::ERROR_GENERAL,
			'version' => self::PLUGIN_VERSION,
			'token_inserted' => $inserted
		];

		return $this->handleResponse($res, 200);
	}

	public function metaAction(Request $request) {

		$types = $this->getContentTypes();

		$responseData = [
			'meta_data' => [
				'post_types' => $types
			],
			'message' => '',
			'code' => self::STATUS_OKAY
		];

		return $this->handleResponse($responseData, 200);
    }


	public function createAction(Request $request) {

		$token = $request->query->get('token');
		if ($token !== $this->container->getParameter(self::token)) {
			return $this->handleResponse(['message' => 'Token ist nicht korrekt', 'code' => self::ERROR_GENERAL], 422);
		}

		$requestData = $request->request->get('content_data');

		$check = $this->validateContentCreate($requestData);

		if (!is_int($check)) {
			return $check;
		}

		$postContent;
		$title;
		$postStatus;
		$meta;
		$cmsUserId;
		$postType;
		$publishDate;
        $keywords = [];

		if ( !empty($requestData['post_title'])) {
			$title = $requestData['post_title'];
		}

		if ( !empty($requestData['post_content'])) {
			$postContent = $requestData['post_content'];
		}

		if ( !empty($requestData['post_status'])) {
			$postStatus = $requestData['post_status'];
		}

		if ( !empty($requestData['post_meta'])) {
			if ( !empty($requestData['post_meta']['cms_user_id'])) {
				$cmsUserId = $requestData['post_meta']['cms_user_id'];
			}

			if ( !empty($requestData['post_meta']['cms_post_type'])) {
				$postType = $requestData['post_meta']['cms_post_type'];
			}
		}

        if ( !empty($requestData['keywords'])) {
            $keywords = array_values($requestData['keywords']);
        }


        if (empty($title) || empty($postContent) || empty($cmsUserId) || empty($postType)) {
			return $this->handleResponse(['message' => '', 'code' => self::ERROR_NO_CONTENT_GIVEN], 422);
		}

		$this->handleImages($postContent);

		$postContent = '<section xmlns="http://ez.no/namespaces/ezpublish5/xhtml5/edit">' . $postContent .  '</section>';

        $content = $this->createContent($postType, $title, $postContent, 'draft', $cmsUserId, $keywords);

		$res = [
			'code' => self::STATUS_OKAY,
				array('message' => ''),
			'cms_content_id' => $content->id
		];

		return $this->handleResponse($res, 200);
	}

	public function getContentAction(Request $request) {

		$token = $request->query->get('token');

		if ($token !== $this->container->getParameter(self::token)) {
			return $this->handleResponse(['message' => 'Token ist nicht korrekt', 'code' => self::ERROR_GENERAL], 422);
		}

		$contentService = $this->repository->getContentService();
		$userService = $this->repository->getUserService();

		if (!$request->query->has('cms_content_id')) {
			return $this->handleResponse(['message' => 'Content wurde nicht angegeben!!', 'code' => self::ERROR_NO_CONTENT_GIVEN], 422);
		}

		$contentId = $request->query->get('cms_content_id');

		$contentInfo = $contentService->loadContentInfo( $contentId );
		$userId = $userService->loadUser($contentInfo->ownerId);
		$this->repository->setCurrentUser($userId);
		$content = $contentService->loadContent( $contentId );

		$title = $content->getField('title')->value;
		$body = $content->getField('text')->value;

		$parsed = $this->parser->parse($body);

		$res = [
			'code' => self::STATUS_OKAY,
			'message' => '',
			'content' => [
				'title' => (string)$title,
				'content' => $parsed
			]
		];

		return $this->handleResponse($res, 200);
	}

	public function contentUpdateAction(Request $request) {

		$token = $request->query->get('token');

		if ($token !== $this->container->getParameter(self::token)) {
			return $this->handleResponse(['message' => 'Token ist nicht korrekt', 'code' => self::ERROR_GENERAL], 422);
		}

		$contentId;
		$postContent;
		$title;
		$keywords = [];

		if ($request->request->has('cms_content_id')) {
			$contentId = $request->request->get('cms_content_id');
		}

		if ($request->request->has('post_content')) {
			$postContent = $request->request->get('post_content');
		}

		if ($request->request->has('post_title')) {
			$title = $request->request->get('post_title');
		}

        if ($request->request->has('keywords')) {
            $keywords = $request->request->get('keywords');
        }

		if (empty ($contentId) || empty ($title) || empty ($postContent)) {
			return $this->handleResponse(['message' => 'No Content given', 'code' => self::ERROR_NO_CONTENT_GIVEN], 422);
		}

		$this->handleImages($postContent);

		$html = '<section xmlns="http://ez.no/namespaces/ezpublish5/xhtml5/edit">' . $postContent . '</section>';

		$content = $this->updateContent($contentId, $html, $title, $keywords);

		$res = array(
			'code' => self::STATUS_OKAY,
				['message' => ''],
			'cms_content_id' => $content->id
        );

		return $this->handleResponse($res, 200);
	}


	private function createContent($contentType, $title, $text, $post_status, $userId, $keywords) {

		$contentService = $this->repository->getContentService();
		$locationService = $this->repository->getLocationService();
		$contentTypeService = $this->repository->getContentTypeService();
		$userService = $this->repository->getUserService();

		$user = $userService->loadUser( $userId );
		$this->repository->setCurrentUser( $user );

		$contentType = $contentTypeService->loadContentTypeByIdentifier( $contentType );
		$contentCreateStruct = $contentService->newContentCreateStruct( $contentType, 'eng-GB' );

        if (!empty($keywords)) {
            $contentCreateStruct->setField('cb_keywords', implode(';', $keywords));
        }

		$contentCreateStruct->setField( 'title', $title );
		$contentCreateStruct->setField( 'text', $text );

		$locationCreateStruct = $locationService->newLocationCreateStruct( 2 ); // later dynamic...

		$draft = $contentService->createContent( $contentCreateStruct, array( $locationCreateStruct ) );

		return $draft;
	}

	private function getContentTypes() {

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

	private function updateContent($contentId, $newBody, $newTitle) {

		$repository = $this->container->get( 'ezpublish.api.repository' );
		$contentService = $this->repository->getContentService();
		$userService = $this->repository->getUserService();

		$contentInfo = $contentService->loadContentInfo( $contentId );
		$userId = $userService->loadUser($contentInfo->ownerId);
		$this->repository->setCurrentUser($userId);

		if ($contentInfo->isPublished()) {
			$contentDraft = $contentService->createContentDraft( $contentInfo );
		}
		else if ($contentInfo->isDraft()) {
			$versionInfo = $contentService->loadVersionInfoById($contentId);
		}

		$contentUpdateStruct = $contentService->newContentUpdateStruct();
		$contentUpdateStruct->initialLanguageCode = 'eng-GB';

		if ($newTitle) {
			$contentUpdateStruct->setField( 'title', $newTitle );
		}

        if (!empty($keywords)) {
            $contentUpdateStruct->setField('cb_keywords', implode(';', $keywords));
        }
        else {
            $contentUpdateStruct->setField('cb_keywords', '');
        }

		$contentUpdateStruct->setField( 'text', $newBody );

		if ($contentInfo->isPublished()) {
			$contentDraft = $contentService->updateContent( $contentDraft->versionInfo, $contentUpdateStruct );
		}
		else if ($contentInfo->isDraft()) {
			$contentDraft = $contentService->updateContent( $versionInfo, $contentUpdateStruct );
		}
		return $contentDraft;
	}

	public function usersAction(Request $request) {

		$token = $request->query->get('token');

		if ($token !== $this->container->getParameter(self::token)) {
			return $this->handleResponse(['message' => 'Token ist nicht korrekt', 'code' => self::ERROR_GENERAL], 422);
		}

		$this->setCurrentUserAdmin();

		$query = new Query();
		$query->filter = new Criterion\LogicalAnd([
			new Criterion\ContentId([172776, 172749])
		]);

		$usersContent = array_map(function ($hit) {
			return $hit->valueObject;
		}, $this->repository->getSearchService()->findContent($query)->searchHits);

		$users = [];

		foreach ($usersContent as $value) {

			$users[$value->getFieldValue('user_account')->contentId] =
			[
				'display_name' => $value->getFieldValue('first_name')->text,
				'email' => $value->getFieldValue('user_account')->email
			];
		}

		$users = ['users' => $users, 'message' => '', 'code' => '0'];

		return $this->handleResponse($users, 200);
	}

	private function setCurrentUserAdmin() {

		$userService = $this->repository->getUserService();
		$administratorUser = $userService->loadUser( 14 );
		$this->repository->setCurrentUser( $administratorUser );
	}

	private function uploadImage($image, $imageName) {

		$repository = $this->container->get( 'ezpublish.api.repository' );
        $contentService = $repository->getContentService();
        $locationService = $repository->getLocationService();
        $contentTypeService = $repository->getContentTypeService();
        $repository->setCurrentUser( $repository->getUserService()->loadUser( 14 ) );
		$contentType = $contentTypeService->loadContentTypeByIdentifier( "image" );
		$contentCreateStruct = $contentService->newContentCreateStruct( $contentType, 'eng-GB' );

		$contentCreateStruct->setField( 'name', $imageName );

		$value = new Value(
			[
				'path' => $imageName,
				'fileSize' => filesize( $imageName ),
				'fileName' => basename( $imageName ),
				'alternativeText' => $imageName
			]
		);
		$contentCreateStruct->setField( 'image', $value );
		$draft = $contentService->createContent(
			$contentCreateStruct,
			[$locationService->newLocationCreateStruct( '43' )]

		);
		$content = $contentService->publishVersion( $draft->versionInfo );

		$urlToImage = $content->getField('image')->value->uri;
		$id = $content->id;

		return $id;
	}

	private function handleImages(&$html) {
		preg_match_all('/<img[^>]+>/i', $html, $result);
		if (count($result[0]) > 0) {
            $html = preg_replace('/<img[^>]+>/i','', $html);
		}
	}

	private function findImageInCMS($imageName) {

		$contentService = $this->repository->getContentService();
		$locationService = $this->repository->getLocationService();
		$contentTypeService = $this->repository->getContentTypeService();
		$searchService = $this->repository->getSearchService();
		$userService = $this->repository->getUserService();

		$query = new Query();
		$query->filter = new Criterion\LogicalAnd(
			[
				new Criterion\ParentLocationId('43'),
				new Criterion\ContentTypeIdentifier(['image']),
				new Criterion\Field('name', Criterion\Operator::EQ, $imageName)
			]
		);

		$result = $searchService->findContent($query)->searchHits;
		if(!empty($result[0])) {
			return $result->valueObject->versionInfo->contentInfo->id;
		}
		return -1;
	}
}
