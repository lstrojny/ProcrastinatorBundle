<?php
namespace LS\ProcrastinatorBundle\Tests\DependencyInjection;

use PHPUnit_Framework_TestCase as TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use LS\ProcrastinatorBundle\DependencyInjection\LSProcrastinatorExtension;
use Symfony\Component\DependencyInjection\Definition;

class LSProcrastinatorExtensionTest extends TestCase
{
    public function testManagedObjectsArePresent()
    {
        $container = new ContainerBuilder();
        $loader = new LSProcrastinatorExtension();
        $loader->load(array(), $container);

        $this->assertTrue($container->hasDefinition('procrastinator'));
        $this->assertTrue($container->hasDefinition('procrastinator.executor.real'));
        $this->assertTrue($container->hasAlias('procrastinator.executor'));
        $this->assertTrue($container->hasDefinition('procrastinator.scheduler'));
    }

    public function testExecutorIsDecoratedPerDefaultWithPhpFpmAndDoctrineEventConditional()
    {
        $container = new ContainerBuilder();
        $loader = new LSProcrastinatorExtension();
        $loader->load(array(), $container);

        $this->assertSame('procrastinator.executor.decorator.php_fpm', (string)$container->getAlias('procrastinator.executor'));
        $this->assertFalse($container->getAlias('procrastinator.executor')->isPublic());

        $definition = $container->getDefinition('procrastinator.executor.decorator.php_fpm');
        $this->assertSame('Procrastinator\Executor\Decorator\PhpFpmExecutorDecorator', $definition->getClass());
        $this->assertFalse($definition->isPublic());
        $this->assertSame('procrastinator.executor.decorator.doctrine_event_conditional', (string)$definition->getArgument(0));

        $definition = $container->getDefinition('procrastinator.executor.decorator.doctrine_event_conditional');
        $this->assertSame('Procrastinator\Executor\Decorator\DoctrineEventConditionalExecutorDecorator', $definition->getClass());
        $this->assertFalse($definition->isPublic());
        $this->assertSame('procrastinator.executor.real', (string)$definition->getArgument(0));
    }

    public function testEmptyDecoratorChain()
    {
        $container = new ContainerBuilder();
        $loader = new LSProcrastinatorExtension();
        $loader->load(array('ls_procrastinator' => array('executor' => array('decorators' => array()))), $container);

        $this->assertSame('procrastinator.executor.real', (string)$container->getAlias('procrastinator.executor'));
    }

    public function testDoctrineListenerIsRegisteredAsListenerToAllDoctrineEvents()
    {
        $container = new ContainerBuilder();
        $loader = new LSProcrastinatorExtension();
        $loader->load(array(), $container);

        $tags = $container->getDefinition('procrastinator.executor.decorator.doctrine_event_conditional')->getTags();
        $this->assertGreaterThan(10, count($tags['doctrine.event_listener']));
    }

    public function testParametersAreDefined()
    {
        $container = new ContainerBuilder();
        $loader = new LSProcrastinatorExtension();
        $loader->load(array(), $container);

        $this->assertTrue($container->hasParameter('procrastinator.class'));
        $this->assertTrue($container->hasParameter('procrastinator.executor.class'));
        $this->assertTrue($container->hasParameter('procrastinator.scheduler.class'));
    }

    public function testMixedCustomDecoratorChain()
    {
        $container = new ContainerBuilder();
        $def = new Definition();
        $def->addArgument('placeholder');
        $container->setDefinition('decorator1', clone $def);
        $container->setDefinition('decorator2', clone $def);
        $container->setDefinition('decorator3', clone $def);
        $loader = new LSProcrastinatorExtension();
        $loader->load(
            array(
                'ls_procrastinator' => array(
                    'executor' => array(
                        'decorators' => array(
                            'decorator1',
                            'procrastinator.executor.decorator.php_fpm',
                            'decorator2',
                            'procrastinator.executor.decorator.doctrine_event_conditional',
                            'decorator3',
                        )
                    )
                )
            ),
            $container
        );

        $this->assertSame('decorator1', (string)$container->getAlias('procrastinator.executor'));

        $definition = $container->getDefinition('decorator1');
        $this->assertSame('procrastinator.executor.decorator.php_fpm', (string)$definition->getArgument(0));

        $definition = $container->getDefinition('procrastinator.executor.decorator.php_fpm');
        $this->assertSame('decorator2', (string)$definition->getArgument(0));

        $definition = $container->getDefinition('decorator2');
        $this->assertSame('procrastinator.executor.decorator.doctrine_event_conditional', (string)$definition->getArgument(0));

        $definition = $container->getDefinition('procrastinator.executor.decorator.doctrine_event_conditional');
        $this->assertSame('decorator3', (string)$definition->getArgument(0));

        $definition = $container->getDefinition('decorator3');
        $this->assertSame('procrastinator.executor.real', (string)$definition->getArgument(0));
    }


    public function testCustomDecoratorChain()
    {
        $container = new ContainerBuilder();
        $def = new Definition();
        $def->addArgument('placeholder');
        $container->setDefinition('decorator1', clone $def);
        $container->setDefinition('decorator2', clone $def);
        $container->setDefinition('decorator3', clone $def);
        $loader = new LSProcrastinatorExtension();
        $loader->load(
            array(
                'ls_procrastinator' => array(
                    'executor' => array(
                        'decorators' => array(
                            'decorator1',
                            'decorator2',
                            'decorator3',
                        )
                    )
                )
            ),
            $container
        );

        $this->assertSame('decorator1', (string)$container->getAlias('procrastinator.executor'));

        $definition = $container->getDefinition('decorator1');
        $this->assertSame('decorator2', (string)$definition->getArgument(0));

        $definition = $container->getDefinition('decorator2');
        $this->assertSame('decorator3', (string)$definition->getArgument(0));

        $definition = $container->getDefinition('decorator3');
        $this->assertSame('procrastinator.executor.real', (string)$definition->getArgument(0));
    }

    public function testRealExecutorMayNotBeInChain()
    {
        $container = new ContainerBuilder();
        $loader = new LSProcrastinatorExtension();

        $this->setExpectedException(
            'Symfony\Component\Config\Definition\Exception\InvalidConfigurationException',
            'Invalid configuration for path "ls_procrastinator.executor.decorators.0": For internal usage only'
        );
        $loader->load(
            array('ls_procrastinator' => array('executor' => array('decorators' => array('procrastinator.executor.real')))),
            $container
        );
    }

    public function testExecutorAliasMayNotBeInChain()
    {
        $container = new ContainerBuilder();
        $loader = new LSProcrastinatorExtension();

        $this->setExpectedException(
            'Symfony\Component\Config\Definition\Exception\InvalidConfigurationException',
            'Invalid configuration for path "ls_procrastinator.executor.decorators.0": For internal usage only'
        );
        $loader->load(
            array('ls_procrastinator' => array('executor' => array('decorators' => array('procrastinator.executor')))),
            $container
        );
    }
}