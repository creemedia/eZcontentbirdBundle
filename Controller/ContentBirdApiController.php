<?php

namespace creemedia\Bundle\eZcontentbirdBundle\Controller;

use DOMDocument;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use eZ\Publish\API\Repository\Repository;
use eZ\Bundle\EzPublishCoreBundle\Controller;
use creemedia\Bundle\eZcontentbirdBundle\Helper\TagParser;
use creemedia\Bundle\eZcontentbirdBundle\Helper\ImageHelper as ImageHelper;
use Symfony\Component\DependencyInjection\ContainerInterface as Container;

class ContentBirdApiController extends Controller
{
	private $contentBirdService;
	private $parser;
	private $url;
	private $tagParser;
	private $cmsService;
	private $imagePath;

	const TOKEN = 'contentbird.token';
	const PLUGIN_VERSION = '0.9.1';

	/** Status and error codes */
	const STATUS_OKAY = 0;
	const ERROR_NO_TITLE_GIVEN = 1;
	const ERROR_NO_CONTENT_GIVEN = 2;
	const ERROR_NO_AUTHOR_GIVEN = 3;
	const ERROR_NO_STATUS_GIVEN = 4;
	const ERROR_UNKNOWN_POST_TYPE = 5;
	const ERROR_UNKNOWN_POST_CATEGORY = 6;
	const ERROR_CREATE_USER = 7;
	const ERROR_USER_PERMISSION_DENIED = 8;
	const ERROR_USER_NOT_FOUND = 9;
	const ERROR_PUBLISH_POST = 10;
	const ERROR_OBJECT_INVALID = 11;
	const ERROR_UNKNOWN_METHOD = 12;
	const ERROR_NOT_FOUND = 13;
	const ERROR_GENERAL = 14;
	const ERROR_NO_BODY = 15;

	private $imageHelper;
	private $repository;
	private $locationService;
	private $searchService;
	private $userService;
	private $imagePathCover;
	private $databaseService;

	public function __construct(Container $container, Repository $repository)
	{
		$this->repository = $repository;
		$this->container = $container;
		$this->locationService = $this->repository->getLocationService();
		$this->searchService = $this->repository->getSearchService();
		$this->userService = $this->repository->getUserService();
		$this->contentBirdService = $this->container->get('cmcontentbirdconnector.service.api');
		$this->parser = $this->container->get('cmcontentbirdconnector.service.parser');
		$this->cmsService = $this->container->get('cmcontentbirdconnector.service.cms');
		$this->databaseService = $this->container->get('cmcontentbirdconnector.service.database');
		$this->tagParser = new TagParser();
		$this->imageHelper = new ImageHelper();

		$this->imagePath = $this->container->get('kernel')->getRootDir() . '/../web/var/storage/image.jpg';
		$this->imagePathCover = $this->container->get('kernel')->getRootDir() . '/../web/var/storage/cover.jpg';
	}

	/**
	 * @param Request $request
	 * @return Response
	 */
	public function activateAction(Request $request)
	{
		$host = $request->headers->get('x-forwarded-host') ? preg_replace('/,.*$/', '', $request->headers->get('x-forwarded-host')) : $request->getHost();
		$proto = $request->isSecure() ? 'https://' : 'http://';
		$this->contentBirdService->pluginStatus($proto . $host);
		$this->url = $proto . $host;

		return new Response();
	}

	/**
	 * @param $payload
	 * @param null $statusCode
	 * @return JsonResponse
	 */
	private function handleResponse($payload, $statusCode = null)
	{
		$response = new JsonResponse();

		if ($payload) {
			$response->setData($payload);
		}
		if ($statusCode) {
			$response->setStatusCode($statusCode);
		}
		return $response;
	}

	/**
	 * @param Request $reques
	 * @return JsonResponse|null
	 */
	public function handleAction(Request $request)
	{
		$errorResponse = null;

		if (!$request->query->has('token')) {
			return $this->handleResponse(['message' => 'Token wurde nicht gesetzt', 'code' => self::ERROR_GENERAL], 422);
		}

		if (!$request->query->has('lbcm')) {
			return $this->handleResponse(['message' => 'lbcm/action wurde niht gesetzt', 'code' => self::ERROR_GENERAL], 405);
		}

		$token = $request->query->get('token');
		$action = $request->query->get('lbcm');

		if ($token !== $this->container->getParameter(self::TOKEN)) {
			return $this->handleResponse(['message' => 'Token ist nicht korrekt', 'code' => self::ERROR_GENERAL], 403);
		}

		switch ($action) {

			case 'users':
				return $this->usersAction($request);

			case 'meta':
				return $this->metaAction();

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

	/**
	 * @return JsonResponse
	 */
	public function statusAction()
	{
		$token = $this->container->getParameter(self::TOKEN);

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

	/**
	 * @return JsonResponse
	 */
	public function metaAction()
	{
		$types = $this->cmsService->getContentTypes();
		$categoriesList = $this->cmsService->getAllCategories();

		$categories = [];

		foreach ($categoriesList as $item) {
			$categories[] = [
				'term_id' => $item->versionInfo->contentInfo->mainLocationId,
				'cat_name' => ($item->versionInfo->contentInfo->contentTypeId === 14 ? 'Serie: ' : 'Ratgeber: ') . $item->getField('title')->value->text
			];
		}

		$responseData = [
			'meta_data' => [
				'post_types' => $types,
				'post_categories' => $categories
			],
			'message' => '',
			'code' => self::STATUS_OKAY
		];
		return $this->handleResponse($responseData, 200);
	}

	/**
	 * @param $requestData
	 * @return array
	 */
	private function getFieldsFromRequest($requestData)
	{
		$fields = [];

		$fields['post_title'] = $requestData['post_title'] ?? null;
		$fields['post_content'] = $requestData['post_content'] ?? null;
		$fields['post_status'] = $requestData['post_status'] ?? null;
		$fields['cms_content_id'] = $requestData['cms_content_id'] ?? null;
		$fields['cms_user_id'] = $requestData['post_meta']['cms_user_id'] ?? null;
		$fields['cms_post_type'] = $requestData['post_meta']['cms_post_type'] ?? null;
		$fields['parent_location'] = $requestData['post_meta']['cms_post_categories'] ?? [2]; // fallback to home location
		$fields['keywords'] = $requestData['keywords'] ?? [];
		return $fields;
	}

	/**
	 * @param Request $request
	 * @return JsonResponse|null
	 */
	public function createAction(Request $request)
	{
		//$check = $this->checkToken($request);
		//if ($check) return $check;

		$requestData = $request->request->get('content_data');
		$fields = $this->getFieldsFromRequest($requestData);

		if (!$fields['post_title'] || !$fields['post_content'] || !$fields['cms_user_id'] || !$fields['cms_post_type']) {
			return $this->handleResponse(['message' => '', 'code' => self::ERROR_NO_CONTENT_GIVEN], 422);
		}

		$fields['post_content'] = str_replace('&copy;', '', $fields['post_content']);
		$shortCodes = $this->tagParser->parseShortCodes($fields['post_content']);
		$this->imageHelper->handleCoverImageFromShortCodes($this->imagePathCover, $shortCodes);

		$fields['post_content'] = $this->clearHtml($fields['post_content']);
		$fields['post_content'] = $this->handleShortCodes($fields['parent_location'], $fields['post_content'], $shortCodes);
		$fields['post_content'] = $this->tagParser->clearShortCodes($fields['post_content']);
		$fields['post_content'] = $this->parseImages($fields['post_content']);

		$fields['post_content'] = str_replace('<br>', '<br />', $fields['post_content']);
		$fields['post_content'] = html_entity_decode($fields['post_content']);
		$fields['post_content'] = str_replace('&', '&amp;', $fields['post_content']);

		$content = $this->cmsService->createContent($fields['parent_location'], $fields['cms_post_type'], $fields['post_title'], $fields['post_content'], 'draft', $fields['cms_user_id'], $fields['keywords'], $shortCodes);

		try {
			$summary = '';
			$image = '';

			foreach ($shortCodes as $field) {
				if (in_array($field['name'], ['summary', 'image'])) {
					if ($field['name'] === 'summary' && isset($field['content'])) {
						$summary = $field['content'];
					} else if (($field['name'] === 'image') && isset($field['content'])) {
						$image = $field['src_alt'];
					}
				}
			}

			$this->databaseService->insertRow($content->id, $fields['post_title'], $fields['post_content'], $summary, $fields['parent_location'][0], implode(';', $fields['keywords']), $fields['cms_user_id'], $image);
		} catch (\Exception $e) {
			// todo log
		}

		$res = [
			'code' => self::STATUS_OKAY, ['message' => ''], 'cms_content_id' => $content->id
		];

		return $this->handleResponse($res, 200);
	}

	/**
	 * @param Request $request
	 * @return JsonResponse|null
	 */
	public function getContentAction(Request $request)
	{
		$check = $this->checkToken($request);
		if ($check) return $check;

		$contentService = $this->repository->getContentService();
		$userService = $this->repository->getUserService();

		if (!$request->query->has('cms_content_id')) {
			return $this->handleResponse(['message' => 'Content wurde nicht angegeben!!', 'code' => self::ERROR_NO_CONTENT_GIVEN], 422);
		}

		$contentId = $request->query->get('cms_content_id');

		$contentInfo = $contentService->loadContentInfo($contentId);
		$userId = $userService->loadUser($contentInfo->ownerId);
		$this->repository->setCurrentUser($userId);
		$content = $contentService->loadContent($contentId);

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

	/**
	 * @param Request $request
	 * @return JsonResponse|null
	 */
	public function contentUpdateAction(Request $request)
	{
		$check = $this->checkToken($request);
		if ($check) return $check;

		$fields['post_title'] = $request->request->get('post_title') ?? null;
		$fields['post_content'] = $request->request->get('post_content') ?? null;
		$fields['cms_content_id'] = $request->request->get('cms_content_id') ?? null;
		$fields['keywords'] = $request->request->get('keywords') ?? [];

		if (!$fields['post_title'] || !$fields['post_content'] || !$fields['cms_content_id']) {
			return $this->handleResponse(['message' => '', 'code' => self::ERROR_NO_CONTENT_GIVEN], 422);
		}

		$fields['post_content'] = str_replace('&copy;', '', $fields['post_content']);
		$shortCodes = $this->tagParser->parseShortCodes($fields['post_content']);
		$this->imageHelper->handleCoverImageFromShortCodes($this->imagePathCover, $shortCodes);
		$fields['post_content'] = $this->tagParser->clearShortCodes($fields['post_content']);
		$fields['post_content'] = $this->clearHtml($fields['post_content']);
		$fields['post_content'] = $this->parseImages($fields['post_content']);
		$fields['post_content'] = html_entity_decode($fields['post_content']);

		$content = $this->cmsService->updateContent($fields['cms_content_id'], $fields['post_content'], $fields['post_title'], $fields['keywords'], $shortCodes);

		$res = [
			'code' => self::STATUS_OKAY,
			['message' => ''],
			'cms_content_id' => $content->id
		];

		return $this->handleResponse($res, 200);
	}

	/**
	 * @param Request $request
	 * @return JsonResponse|null
	 */
	private function checkToken(Request $request)
	{
		$token = $request->query->get('token');

		if ($token !== $this->container->getParameter(self::TOKEN)) {
			return $this->handleResponse(['message' => 'Token ist nicht korrekt', 'code' => self::ERROR_GENERAL], 422);
		}
		return null;
	}

	/**
	 * @param Request $request
	 * @return JsonResponse|null
	 * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
	 */
	public function usersAction(Request $request)
	{
		$check = $this->checkToken($request);
		if ($check) return $check;

		$this->repository->setCurrentUser($this->userService->loadUser(14));// only admin can do the next

		$usersContent = $this->cmsService->getUsers();

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

	/**
	 * @param $html
	 * @return mixed|string|string[]|null
	 */
	private function clearHtml($html)
	{
		$html = str_replace(['<div>', '</div>', '<div'], ['<p>', '</p>', '<p'], $html);
		$html = str_replace('id="photographer"', '', $html);
		$html = preg_replace('#(<[a-z]*)(style=("|\')(.*?)("|\'))([a-z]*>)#', '\\1\\6', $html);
		$html = preg_replace('/ style=("|\')(.*?)("|\')/', '', $html);
		return $html;
	}

	/**
	 * @param $html
	 * @return string
	 */
	private function parseImages($html)
	{
		$doc = new DOMDocument('1.0', 'utf-8');
		$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));

		$elements = $doc->getElementsByTagName('img');
		$imageParsed = false;

		for ($i = $elements->length - 1; $i >= 0; $i--) {
			$tag = $elements->item($i);
			$src = $tag->getAttribute('src');
			$alt = $tag->getAttribute('alt');
			$title = basename($src);

			$imageMeta = $this->imageHelper->parseImageAlt($alt);
			$this->imageHelper->downloadImageLocal($src, $this->imagePath);

			$imageValue = $this->imageHelper->generateValue($src, $this->imagePath, $imageMeta);
			$contentId = $this->cmsService->uploadImage($title, $imageMeta[ImageHelper::INDEX_IMAGE_DESCRIPTION] ?? '', $imageValue);
			$nodeDiv = $this->tagParser->createEzEmbed($contentId, $doc, $tag);
			$this->imageHelper->handleImageFallbacks($nodeDiv);
			unlink($this->imagePath);
			$imageParsed = true;

		}

		if ($imageParsed)
			$html = $doc->saveHTML($doc->getElementsByTagName('body')->item(0));

		return $html;
	}

	private function handleShortCodes($parentId, $post_content, array $shortCodes)
	{
		foreach ($shortCodes as $code) {
			switch ($code['name']) {
				case 'quote':
					$id = $this->cmsService->createCite($parentId[0], $code['atts']['title'] ?? '', $code['content'] ?? '');
					$div = '<div data-ezelement="ezembed" data-href="ezcontent://' . $id . '" data-ezview="embed"/>';
					$post_content = str_replace('<p>' . $code['tag'], $div . '<p>[' . $code['name'] . ']</p>', $post_content);
					break;

				case 'infobox':
					$id = $this->cmsService->createInfobox($parentId[0], $code['atts']['title'] ?? '', $code['content'] ?? '', $code['atts']['link'] ?? '');
					$div = '<div data-ezelement="ezembed" data-href="ezcontent://' . $id . '" data-ezview="embed"/>';
					$post_content = str_replace('<p>' . $code['tag'], $div . '<p>[' . $code['name'] . ']</p>', $post_content);
					break;
			}
		}
		return $post_content;
	}
}
