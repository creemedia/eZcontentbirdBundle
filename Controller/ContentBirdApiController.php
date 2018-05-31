<?php

namespace creemedia\Bundle\eZcontentBirdBundle\Controller;

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

use Symfony\Component\DependencyInjection\ContainerInterface as Container;

class ContentBirdApiController extends Controller {

	private $apiService;
	private $contentBirdService;

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
	}

	public function activateAction(Request $request) {
		// $host = $request->headers->get('x-forwarded-host') ? preg_replace('/,.*$/', '', $request->headers->get('x-forwarded-host')) : $request->getHost();
		// $proto = $request->isSecure()? 'https://' : 'http://';
		// $this->contentBirdService->pluginStatus($proto . $host);
		//$this->contentBirdService->pluginStatus('http://4d99a9c8.ngrok.io');
		$this->getContentTypes();
		return new Response();
	}

	public function handleAction(Request $request) {

		if (!$request->query->has('token')) {
			echo "Token wurde nicht gesetzt";
			return new Response();
		}

		if (!$request->query->has('lbcm')) {
			echo "lbcm/Action wurde nicht gesetzt";
			return new Response();
		}

		$token = $request->query->get('token');
		$action = $request->query->get('lbcm');

		if ($token !== $this->container->getParameter('cm_content_bird_connector.token')) {
			echo "Token ist nicht korrekt!!";
			return new Response();
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
				return new Response();
		}
	}

	public function statusAction(Request $request) {

		$token = $this->container->getParameter('cm_content_bird_connector.token');

		$inserted = false;
		if (strlen($token) > 0) {
			$inserted = true;
		}

		$res = [
			'message' => 'Plugin Status',
			'code' => '0',
			'version' => '1.0',
			'token_inserted' => $inserted
		];
		$res = json_encode($res);

		return new Response($res);
	}

	public function metaAction(Request $request) {

		$types = $this->getContentTypes();

		$responseData = [
			'meta_data' => [
				'post_types' => $types,
				'post_categories' => [
					'A' => [
						'name' => 'A',
						'label' => 'A',
						'type' => 'a',
					]
				]
			],
			'message' => '',
			'code' => '0'
		];

		$response = new JsonResponse();
		$response->setData($responseData);

		return $response;
    }


	public function createAction(Request $request) {

		$requestData = $request->request->get('content_data');

		/*$check = $this->validateContentCreate($requestData);

		if (is_array($requestData)) {
			return new JsonResponse(json_encode($check));
		}*/

		$this->writeToFile(json_encode($requestData));

		$html = '';
		$title = '';
		$postStatus = '';
		$meta = '';
		$cmsUserId = '';
		$postType = '';

		if ($requestData['post_title']) {
			$title = $requestData['post_title'];
		}

		if ($requestData['post_content']) {
			$html = $requestData['post_content'];
		}

		if ($requestData['post_status']) {
			$postStatus = $requestData['post_status'];
		}

		if ($requestData['post_meta']) {
			if ($requestData['post_meta']['cms_user_id']) {
				$cmsUserId = $requestData['post_meta']['cms_user_id'];
			}

			if ($requestData['post_meta']['cms_post_type']) {
				$postType = $requestData['post_meta']['cms_post_type'];
			}
		}

		$this->writeToFile($cmsUserId . "   " .  $postType);


		$html = '<section xmlns="http://ez.no/namespaces/ezpublish5/xhtml5/edit">' . $html .  '</section>';

        $content = $this->createContent($postType, $title, $html, 'publish');

		$res = array(
			'code' => '0',
				array('message' => ''),
			'cms_content_id' => $content->id
			);

		$response = new JsonResponse();
		$response->setData($res);

		return $response;

	}

	public function getContentAction(Request $request) {

		$token = $request->query->get('token');
		$cm_content_id = $request->query->get('cms_content_id');

		$contentService = $this->repository->getContentService();
		$content = $contentService->loadContent( $cm_content_id );

		$title = $content->getField('title')->value;
		$body = $content->getField('text')->value;

		$body = str_replace('<section xmlns="http://docbook.org/ns/docbook" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:ezxhtml="http://ez.no/xmlns/ezpublish/docbook/xhtml" xmlns:ezcustom="http://ez.no/xmlns/ezpublish/docbook/custom" version="5.0-variant ezpublish-1.0">', '', $body);

		$body = str_replace('</section>', '', $body);
		$body = str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $body);

		// Parser feht...

		$res = [
			'code' => '0',
			'message' => '',
			'content' => [
				'title' => (string)$title,
				'content' => $body
			]
		];

		$response = new JsonResponse();
		$response->setData($res);

		return $response;
	}

	private function writeToFile($data) {
		$myfile = fopen("contentbirdlogger.txt", "w");
		fwrite($myfile, $data);
		fclose($myfile);
	}

	public function contentUpdateAction(Request $request) {

		$requestData = $request->request->get('content_data');

		$contentId;
		$postContent;
		$title;

		if ($request->request->has('cms_content_id')) {
			$contentId = $request->request->get('cms_content_id');
		}

		if ($request->request->has('post_content')) {
			$postContent = $request->request->get('post_content');
		}

		if ($request->request->has('post_title')) {
			$title = $request->request->get('post_title');
        }

		$html = '<section xmlns="http://ez.no/namespaces/ezpublish5/xhtml5/edit">' . $postContent . '</section>';

		$content = $this->updateContent($contentId, $html, $title);

		$res = array(
			'code' => '0',
				array('message' => ''),
			'cms_content_id' => $content->id
        );

		$response = new JsonResponse();
		$response->setData($res);

		return $response;
	}


	private function createContent($contentType, $title, $text, $post_status) {

		$contentService = $this->repository->getContentService();
		$locationService = $this->repository->getLocationService();
		$contentTypeService = $this->repository->getContentTypeService();
		$userService = $this->repository->getUserService();

        $this->setCurrentUserAdmin();

		$contentType = $contentTypeService->loadContentTypeByIdentifier( $contentType );
		$contentCreateStruct = $contentService->newContentCreateStruct( $contentType , 'eng-GB' );

		$contentCreateStruct->setField( 'title', $title );

		$contentCreateStruct->setField( 'text', $text );


		$locationCreateStruct = $locationService->newLocationCreateStruct( 2 );

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

	private function print($data) {
		echo "<pre>";
		print_r($data);
		echo "</pre>";
	}

	private function updateContent($contentId, $newBody, $newTitle) {

		$repository = $this->container->get( 'ezpublish.api.repository' );
		$contentService = $this->repository->getContentService();
		$locationService = $this->repository->getLocationService();
		$contentTypeService = $this->repository->getContentTypeService();
		$userService = $this->repository->getUserService();

		$this->setCurrentUserAdmin();

		$contentInfo = $contentService->loadContentInfo( $contentId );


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

		// to do check if token is correct

		$this->setCurrentUserAdmin();

		$query = new LocationQuery();
		$query->filter = new Criterion\LogicalAnd([
			new Criterion\LocationId(5)
		]);

		$parentUserLocation = array_map(function ($hit) {
			return $hit->valueObject;
		}, $this->repository->getSearchService()->findLocations($query)->searchHits);

		$query = new Query();
		$query->filter = new Criterion\LogicalAnd([
			new Criterion\Subtree([$parentUserLocation[0]->pathString]),
			new Criterion\ContentTypeIdentifier(['user'])
		]);

		$usersContent = array_map(function ($hit) {
			return $hit->valueObject;
		}, $this->repository->getSearchService()->findContent($query)->searchHits);

		$users = [];
		$counter = 1;

		foreach ($usersContent as $value) {

			array_push($users,

				['display_name' => $value->getFieldValue('first_name')->text,
				'email' => $value->getFieldValue('user_account')->email

			]);
		}

		$users = ['users' => $users, 'message' => '', 'code' => '0'];

		$usres = json_encode($users);

		$response = new JsonResponse();
		$response->setData($users);

		return $response;
	}

	private function validateContentCreate($data) {

        if ( empty( $data ) ) {
            return $this->handle_error(
                self::ERROR_OBJECT_INVALID,
                'Invalid content data object',
                422
            );
        }

        if ( empty( $data['post_title'] ) ) {
            return $this->handle_error(
                self::ERROR_NO_TITLE_GIVEN,
                'No title given',
                422
            );
        }

        if ( empty( $data['post_content'] ) ) {
            return $this->handle_error(
                self::ERROR_NO_TITLE_GIVEN,
                'No title given',
                422
            );
        }

		return 0;

        $content_data = array(
            'post_title'   => $data['content_data']['post_title'],
            'post_content' => $data['content_data']['post_content'],
        );
	}

	private function setCurrentUserAdmin() {
		$userService = $this->repository->getUserService();
		$administratorUser = $userService->loadUser( 14 );
		$this->repository->setCurrentUser( $administratorUser );
	}

	private function handle_error( $error_code, $error_message, $http_status_code = null ) {
        $payload = array(
            'code' => $error_code,
            'message' => $error_message,
		);

		return $payload;
    }
}
