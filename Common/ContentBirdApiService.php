<?php

namespace creemedia\Bundle\eZcontentBirdBundle\Common;
use Symfony\Component\DependencyInjection\ContainerInterface as Container;
use eZ\Publish\API\Repository\Repository;
use eZ\Publish\API\Repository\Values\Content\LocationQuery;
use eZ\Publish\API\Repository\Values\Content\Query;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion;
use eZ\Publish\API\Repository\Values\Content\Query\SortClause;
use GuzzleHttp\Client;

class ContentBirdApiService {

	private $container;
	private $repository;

	public function __construct( Container $container, Repository $repository ) {
		$this->container = $container;
		$this->repository = $repository;
	}

	function printResponse($response) {
		echo "<pre>";
		print_r($response);
		echo "</pre>";
	}

	public function pluginStatus($url) {
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
		$this->printResponse($response);
	}

	public function contentStatus($contentId, $date, $status) {

		$body = [
			'instance_domain' => 'http://4d99a9c8.ngrok.io/contentbird',
			'cms_content_id' => (int)$contentId,
			'content_id' => 21,
			'content_url' => 'http://4d99a9c8.ngrok.io/contentbird',
			'content_published_date' => $date,
			'content_status' => $status,
			'future_post' => false
		];

		$body = json_encode($body);

		$response = $this->createRequest('PUT', '/api/public/content/status', $body);
	}


	public function createRequest($method, $endpoint, $body) {

		$token = $this->container->getParameter('cm_content_bird_connector.token');
		$token = $this->getTokenPlugin();

		$headers = [
			'Authorization' => 'Bearer ' . $token,
			'Accept' => 'application/json'
		];

		$instance = $this->getInstanceFromToken($this->container->getParameter('cm_content_bird_connector.token'));
		$client = new \GuzzleHttp\Client(['base_uri' => $instance['iss']]);

		$response = $client->request($method, $endpoint, ['headers' => $headers, 'body' => $body]);

		$responseBody = json_decode($response->getBody(), true);

		return $responseBody;

	}

	public function getTokenPlugin() {
		$instance = $this->getInstanceFromToken($this->container->getParameter('cm_content_bird_connector.token'));
		$client = new \GuzzleHttp\Client(['base_uri' => $instance['iss']]);
		var_dump($instance);
		$response = $client->request('GET');
		$token =  json_decode($response->getBody(), true);
		var_dump($token);
		return $token['token'];

	}

	private function getInstanceFromToken($token) {
        $token_parts = explode( '.', $token );
        return json_decode( base64_decode( $token_parts[1] ), true );
	}
}
