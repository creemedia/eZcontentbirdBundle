<?php

namespace creemedia\Bundle\eZcontentbirdBundle\Helper;

class TagParser
{
	const SHORTOCODE_REGEXP = "/(?P<shortcode>(?:(?:\\s?\\[))(?P<name>[\\w\\-]{3,})(?:\\s(?P<attrs>[\\w\\d,\\s=\\\"\\'\\-\\+\\#\\%\\!\\~\\`\\&\\.\\s\\:\\/\\?\\|]+))?(?:\\])(?:(?P<content>[\\w\\d\\,\\!\\@\\#\\$\\%\\^\\&\\*\\(\\\\)\\s\\=\\\"\\'\\-\\+\\&\\.\\s\\:\\/\\?\\|\\<\\>]+)(?:\\[\\/[\\w\\-\\_]+\\]))?)/u";
	/**
	 * @param $content
	 * @return string
	 */
	public function clearShortCodes($content)
	{
		return preg_replace(self::SHORTOCODE_REGEXP, '', $content);
	}

	/**
	 * @param $content
	 * @return array
	 */
	public function parseShortCodes($content)
	{
		preg_match_all(self::SHORTOCODE_REGEXP, $content, $matches, PREG_SET_ORDER);
		$shortCodes = [];
		foreach ($matches as $key => $value) {
			$shortCodes[$key]['shortcode'] = $value['shortcode'];
			$shortCodes[$key]['name'] = $value['name'];
			if (isset($value['content'])) {
				$shortCodes[$key]['content'] = $value['content'];
			}
		}

		return $shortCodes;
	}
}
