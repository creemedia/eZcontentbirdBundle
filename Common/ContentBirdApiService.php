<?php

namespace creemedia\Bundle\eZcontentbirdBundle\Common;

use eZ\Publish\API\Repository\Exceptions\NotFoundException;
use eZ\Publish\API\Repository\Exceptions\UnauthorizedException;
use eZ\Publish\Core\Repository\Values\Content\Content;
use Symfony\Component\DependencyInjection\ContainerInterface as Container;
use eZ\Publish\API\Repository\Repository;
use Symfony\Component\Routing\RouterInterface;

class ContentBirdApiService
{
	private $container;
	private $repository;
	private $router;

	const TOKEN = 'contentbird.token';

	public function __construct(Container $container, Repository $repository, RouterInterface $router)
	{
		$this->container = $container;
		$this->repository = $repository;
		$this->router = $router;
	}

	public function pluginStatus($url)
	{
		$status = 'activated';

		$body = [
			'plugin_status' => $status,
			'cms_frontend_url' => $url . '/contentbird',
			'cms_admin_url' => $url . '/ez',
			'cms_editor_url' => $url . '/contentbird',
			'cms_plugin_api_url' => $url . '/contentbird'
		];

		$body = json_encode($body);

		$response = $this->createRequest('POST', '/api/public/plugin/status', $body);
		return $response;
	}

	private function pathCount($pathString)
	{
		return count(explode('/', $pathString));
	}

	public function getMainLocation($contentInfo)
	{
		$locations = $this->repository->getLocationService()->loadLocations($contentInfo);
		$mainLocation = $this->repository->getLocationService()->loadLocation($contentInfo->mainLocationId);

		if (count($locations) > 1 && $this->pathCount($mainLocation->pathString) > 7) {
			foreach ($locations as $location) {
				if ($this->pathCount($location->pathString) < $this->pathCount($mainLocation->pathString)) {
					$mainLocation = $location;
				}
			}
		}

		return $mainLocation;
	}

	public function contentStatus($contentId, $date, $status)
	{
		/** @var Content $content */
		try {
			$content = $this->repository->getContentService()->loadContent($contentId);
			$location = $this->repository->getLocationService()->loadLocation($content->contentInfo->mainLocationId);
			$location = $this->getMainLocation($location->getContentInfo());
		} catch (NotFoundException $e) {
			return null;
		}

		$url = $this->router->generate($location, [], false);

		$url = str_replace("/admin", $this->container->getParameter('www.host'), $url);

		$body = [
			'cms_content_id' => (int)$contentId,
			'content_url' => $url,
			'content_published_date' => $date,
			'content_status' => $status,
			'future_post' => false
		];

		$body = json_encode($body);

		$response = $this->createRequest('PUT', '/api/public/content/status', $body);
		return $response;
	}

	/**
	 * @param $method
	 * @param $endpoint
	 * @param $body
	 * @return bool|mixed
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function createRequest($method, $endpoint, $body)
	{
		$token = $this->getTokenPlugin();

		$headers = [
			'Authorization' => 'Bearer ' . $token,
			'Accept' => 'application/json'
		];

		$instance = $this->getInstanceFromToken($this->container->getParameter(self::TOKEN));

		try {
			$client = new \GuzzleHttp\Client(['base_uri' => $instance['iss']]);
			$response = $client->request($method, $endpoint, ['headers' => $headers, 'body' => $body]);
			$responseBody = json_decode($response->getBody(), true);
			return $responseBody;
		} catch (\Exception $e) {
			echo "error";
			return false;
		}
	}

	/**
	 * @return mixed
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function getTokenPlugin()
	{
		$instance = $this->getInstanceFromToken($this->container->getParameter(self::TOKEN));
		$client = new \GuzzleHttp\Client(['base_uri' => $instance['iss']]);

		$response = $client->request('GET');
		$token = json_decode($response->getBody(), true);

		return $token['token'];

	}

	/**
	 * @param $token
	 * @return mixed
	 */
	private function getInstanceFromToken($token)
	{
		$token_parts = explode('.', $token);
		return json_decode(base64_decode($token_parts[1]), true);
	}
}
