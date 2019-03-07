<?php

namespace creemedia\Bundle\eZcontentbirdBundle\Helper;

use DOMXPath;
use DOMDocument;
use CM\ExtendedImageBundle\eZ\Publish\FieldType\ExtendedImage\Value;

class ImageHelper
{
	/**
	 * @param $src
	 * @param $path
	 */
	public function downloadImageLocal($src, $path)
	{
		$image = file_get_contents($src);
		$fp = fopen($path, "w");
		fwrite($fp, $image);
		fclose($fp);
	}

	/**
	 * @param $nodeDiv
	 */
	public function handleImageFallbacks(&$nodeDiv)
	{
		if ($nodeDiv->parentNode->tagName === 'strong' && !empty($nodeDiv->parentNode->parentNode->tagName)) {
			$nodeDiv->parentNode->parentNode->replaceChild($nodeDiv, $nodeDiv->parentNode);
		}

		if ($nodeDiv->parentNode->tagName === 'p' && !empty($nodeDiv->parentNode->parentNode->tagName)) {
			$nodeDiv->parentNode->parentNode->replaceChild($nodeDiv, $nodeDiv->parentNode);
		}
	}

	/**
	 * @param $contentId
	 * @param $doc
	 * @param $tag
	 * @return node
	 */
	public function createEzEmbed($contentId, $doc, &$tag)
	{
		$ezEmbed = '<div data-ezelement="ezembed" data-href="ezcontent://' . $contentId . '" data-ezview="embed"/>';
		$nodeDiv = $doc->createTextNode($ezEmbed);
		$tag->parentNode->replaceChild($nodeDiv, $tag);
		return $nodeDiv;
	}

	/**
	 * @param $imagePath
	 * @param $shortCodes
	 * @throws \eZ\Publish\Core\Base\Exceptions\InvalidArgumentType
	 */
	public function handleCoverImageFromShortCodes($imagePath, &$shortCodes)
	{
		foreach ($shortCodes as &$code) {
			if ($code['name'] === 'image' && isset($code['content'])) {
				$xpath = new DOMXPath(@DOMDocument::loadHTML($code['content']));
				$src = $xpath->evaluate("string(//img/@src)");
				$alt = $xpath->evaluate("string(//img/@alt)");

				$this->downloadImageLocal($src, $imagePath);

				$value = $this->generateValue($src, $alt, $imagePath);
				$code['content'] = $value;
			}
		}
	}

	/**
	 * @param $src
	 * @param $alt
	 * @param $path
	 * @return Value
	 * @throws \eZ\Publish\Core\Base\Exceptions\InvalidArgumentType
	 */
	public function generateValue($src, $alt, $path)
	{
		$value = new Value([
			'fileName' => basename($src),
			'path' => $path,
			'alternativeText' => $alt
		]);
		return $value;
	}
}
