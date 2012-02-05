<?php
namespace LS\ProcrastinatorBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $tb = new TreeBuilder();

        $root = $tb
            ->root('ls_procrastinator', 'array')
                ->children()
        ;

        $this->addExecutorSection($root);

        return $tb;
    }

    private function addExecutorSection(NodeBuilder $builder)
    {
        $builder
            ->arrayNode('executor')
                ->fixXmlConfig('decorator')
                ->addDefaultsIfNotSet()
                ->children()
                    ->arrayNode('decorators')
                        ->addDefaultsIfNotSet()
                        ->defaultValue(
                                array(
                                    'procrastinator.executor.decorator.php_fpm',
                                    'procrastinator.executor.decorator.doctrine_event_conditional'
                                )
                        )
                        ->prototype('scalar')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}