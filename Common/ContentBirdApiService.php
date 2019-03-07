<?php

namespace creemedia\Bundle\eZcontentbirdBundle\Common;

use Symfony\Component\DependencyInjection\ContainerInterface as Container;
use eZ\Publish\API\Repository\Repository;
use eZ\Publish\API\Repository\Values\Content\LocationQuery;
use eZ\Publish\API\Repository\Values\Content\Query;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion;
use eZ\Publish\API\Repository\Values\Content\Query\SortClause;
use GuzzleHttp\Client;

class ContentBirdApiService
{
	private $container;
	private $repository;

	const TOKEN = 'contentbird.token';

	public function __construct(Container $container, Repository $repository)
	{
		$this->container = $container;
		$this->repository = $repository;
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

	public function contentStatus($contentId, $date, $status)
	{

		$body = [
			'cms_content_id' => (int)$contentId,
			'content_url' => 'no-url',
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
