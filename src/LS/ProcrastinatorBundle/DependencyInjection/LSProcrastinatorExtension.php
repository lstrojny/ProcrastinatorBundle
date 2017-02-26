<?php
namespace LS\ProcrastinatorBundle\DependencyInjection;

use Doctrine\DBAL\Events as DbalEvents;
use Doctrine\ORM\Events as OrmEvents;
use ReflectionClass;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

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

        if (!class_exists(OrmEvents::class) || !class_exists(DbalEvents::class)) {
            return;
        }

        $definition = $container->getDefinition('procrastinator.executor.decorator.doctrine_event_conditional');

        $eventSources = [
            new ReflectionClass(OrmEvents::class),
            new ReflectionClass(DbalEvents::class),
        ];
        foreach ($eventSources as $eventSource) {
            foreach ($eventSource->getConstants() as $constant) {
                $definition->addTag('doctrine.event_listener', ['event' => $constant]);
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
        $container->setAlias('procrastinator.executor', $wrappedId);
        $container->getAlias('procrastinator.executor')->setPublic(false);
    }
}
