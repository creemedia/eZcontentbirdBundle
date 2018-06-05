<?php

namespace creemedia\Bundle\eZcontentbirdBundle\Common;

use eZ\Bundle\EzPublishCoreBundle\FieldType\RichText\Converter;
use eZ\Publish\Core\FieldType\RichText\Converter\Xslt;
use eZ\Publish\Core\FieldType\RichText\Validator;
use eZ\Publish\Core\FieldType\RichText\Resources\stylesheets\docbook\xhtml5\output;
use Symfony\Component\DependencyInjection\ContainerInterface as Container;
use DOMDocument;

class ContentParserService {

	private $converter;

	public function __construct(Container $container)
	{
		$this->container = $container;
	}

	protected function getConverter() {

		$rootDir = $this->container->getParameter('kernel.root_dir');
    	$filePath = $rootDir.'/../vendor/ezsystems/ezpublish-kernel/eZ/Publish/Core/FieldType/RichText/Resources/stylesheets/docbook/xhtml5/output';

        if ($this->converter === null) {
            $this->converter = new Xslt(
               	$filePath . '/xhtml5.xsl',
                [['path' =>  $filePath . '/core.xsl', 'priority' => 100]]
            );
        }
        return $this->converter;
	}

	protected function createDocument($data) {
        $document = new DOMDocument();
        $document->preserveWhiteSpace = false;
        $document->formatOutput = false;
        $document->loadXml($data, LIBXML_NOENT);
        return $document;
    }

	public function parse($xml) {

		$doc = $this->createDocument($xml);
		$converter = $this->getConverter();
		$convertedDocument = $converter->convert($doc);
		$convertedDocumentNormalized = new DOMDocument();
		return $convertedDocument->saveXML();
	}
}
