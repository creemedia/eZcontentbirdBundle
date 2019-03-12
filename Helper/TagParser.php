<?php

namespace creemedia\Bundle\eZcontentbirdBundle\Helper;

class TagParser
{
    const SHORTOCODE_REGEXP = "/(?P<tag>\[[a-z]*\])(?P<content>[^\[]*)\[\/[a-z]*\]/";
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
            $tag = str_replace(['[', ']'], ['',''], $value['tag']);
            $shortCodes[$key]['shortcode'] = $tag;
            $shortCodes[$key]['name'] = $tag;
            if (isset($value['content'])) {
                $shortCodes[$key]['content'] = $value['content'];
            }
        }
        return $shortCodes;
    }
}
