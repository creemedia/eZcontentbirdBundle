<?php

namespace creemedia\Bundle\eZcontentbirdBundle\Helper;

class TagParser
{
    // const SHORTOCODE_REGEXP = "/(?P<tag>\[[a-z_]*\])(?P<content>[^\[]*)\[\/[a-z_]*\]/";
    const SHORTOCODE_REGEXP = "/(?P<tag>\[[^\]]+\])(?P<content>[^\[]*)\[\/[a-z_]+\]/";
    const SHORTOCODE_REGEXP_ATTR = "/\s([a-z]+)=\"([^\"]+)\"/";
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
            $tmpTag = $value['tag'];
            $tag = str_replace(['[', ']'], ['',''], $value['tag']);
            if ($tag !== 'image')
                $atts = $this->getShortCodeAttributes($value);
            $shortCodes[$key]['shortcode'] = $tag;
            $shortCodes[$key]['name'] =  str_replace(['[', ']'], ['',''], $value['tag']);
            $shortCodes[$key]['atts'] = $atts ?? [];
            $shortCodes[$key]['tag'] = $tmpTag;
            if (isset($value['content'])) {
                $shortCodes[$key]['content'] = $value['content'];
            }
        }
        return $shortCodes;
    }

    private function getShortCodeAttributes(&$shortcode)
    {
        preg_match_all(self::SHORTOCODE_REGEXP_ATTR, $shortcode['tag'], $matches, PREG_SET_ORDER);
        $atts = [];
        foreach ($matches as $match) {
            $atts[$match[1]] = $match[2];
        }

        $shortcode = preg_replace(self::SHORTOCODE_REGEXP_ATTR, '', $shortcode);
        return $atts;
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
}
