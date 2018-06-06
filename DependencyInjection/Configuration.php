<?php
namespace creemedia\Bundle\eZcontentbirdBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('contentbird');

		$rootNode
			->children()
				->scalarNode('token')->defaultValue('')->end()
			->end()
			;

        return $treeBuilder;
    }
}
