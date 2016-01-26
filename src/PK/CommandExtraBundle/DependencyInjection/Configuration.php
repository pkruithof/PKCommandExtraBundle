<?php

namespace PK\CommandExtraBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * @author Peter Kruithof <pkruithof@gmail.com>
 */
class Configuration implements ConfigurationInterface
{
    /**
     * @inheritdoc
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('pk_command_extra');

        $rootNode
            ->children()
                ->booleanNode('pid_dir')->defaultValue('%kernel.root_dir%/../var/run')->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
