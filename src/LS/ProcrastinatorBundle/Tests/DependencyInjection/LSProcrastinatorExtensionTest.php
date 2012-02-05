<?php
namespace LS\ProcrastinatorBundle\Tests\DependencyInjection;

use PHPUnit_Framework_TestCase as TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use LS\ProcrastinatorBundle\DependencyInjection\LSProcrastinatorExtension;

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
}