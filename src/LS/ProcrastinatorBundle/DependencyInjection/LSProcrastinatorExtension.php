<?php
namespace LS\ProcrastinatorBundle\DependencyInjection;

use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\Config\FileLocator;
use LS\ProcrastinatorBundle\DependencyInjection\Configuration;
use Symfony\Component\DependencyInjection\Reference;
use ReflectionClass;

class LSProcrastinatorExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');

        $this->buildDecoratorChain($config['executor']['decorators'], $container);
        $this->addEventListenerTags($container);
    }

    private function addEventListenerTags(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('procrastinator.executor.decorator.doctrine_event_conditional')) {
            return;
        }

        if (!class_exists('Doctrine\ORM\Events') || !class_exists('Doctrine\DBAL\Events')) {
            return;
        }

        $definition = $container->getDefinition('procrastinator.executor.decorator.doctrine_event_conditional');

        $eventSources = array(
            new ReflectionClass('Doctrine\ORM\Events'),
            new ReflectionClass('Doctrine\DBAL\Events'),
        );
        foreach ($eventSources as $eventSource) {
            foreach ($eventSource->getConstants() as $constant) {
                $definition->addTag('doctrine.event_listener', array('event' => $constant));
            }
        }
    }

    private function buildDecoratorChain(array $decorators, ContainerBuilder $container)
    {
        $wrappedId = 'procrastinator.executor.real';
        foreach (array_reverse($decorators) as $decoratorId) {
            $container->getDefinition($decoratorId)
                      ->replaceArgument(0, new Reference($wrappedId));
            $wrappedId = $decoratorId;
        }
        $container->setAlias('procrastinator.executor', $wrappedId, false);
        $container->getAlias('procrastinator.executor')->setPublic(false);
    }
}