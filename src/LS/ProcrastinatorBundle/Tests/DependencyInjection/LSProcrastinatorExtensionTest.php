<?php
namespace LS\ProcrastinatorBundle\Tests\DependencyInjection;

use PHPUnit_Framework_TestCase as TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use LS\ProcrastinatorBundle\DependencyInjection\LSProcrastinatorExtension;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

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

    public function testRealExecutorMayNotBeInChain()
    {
        $this->setExpectedException(
            'Symfony\Component\Config\Definition\Exception\InvalidConfigurationException',
            'Invalid configuration for path "ls_procrastinator.executor.decorators.0": For internal usage only'
        );
        $this->createContainer(
            null,
            false,
            array('executor' => array('decorators' => array('procrastinator.executor.real')))
        );
    }

    public function testExecutorAliasMayNotBeInChain()
    {
        $this->setExpectedException(
            'Symfony\Component\Config\Definition\Exception\InvalidConfigurationException',
            'Invalid configuration for path "ls_procrastinator.executor.decorators.0": For internal usage only'
        );
        $this->createContainer(
            null,
            false,
            array('executor' => array('decorators' => array('procrastinator.executor')))
        );
    }

    public function testSchedulerIsImmediateScheduler()
    {
        $container = $this->createContainer();
        $this->assertSame('Procrastinator\Scheduler\ImmediateScheduler', $container->getParameter('procrastinator.scheduler.class'));
        $this->assertInstanceOf('Procrastinator\Scheduler\ImmediateScheduler', $container->get('procrastinator.scheduler'));
    }

    public function testGettingProcrastinator()
    {
        $this->assertInstanceOf('Procrastinator\DeferralManager', $this->createContainer()->get('procrastinator'));
    }

    protected function createContainer($file = null, $debug = false, array $config = array(), array $definitions = array())
    {
        $container = new ContainerBuilder(new ParameterBag(array('kernel.debug' => $debug)));
        $container->registerExtension(new LSProcrastinatorExtension());

        $this->loadFromFile($container, $file);

        $container->addDefinitions($definitions);
        $container->loadFromExtension('ls_procrastinator', $config);

        $container->getCompilerPassConfig()->setOptimizationPasses(array());
        $container->getCompilerPassConfig()->setRemovingPasses(array());
        $container->compile();

        return $container;
    }

    private function loadFromFile(ContainerBuilder $container, $file)
    {
        if ($file === null) {
            return;
        }

        $locator = new FileLocator($this->getContainerFixturePath());
        switch (substr($file, -3)) {
            case 'xml':
                $loader = new XmlFileLoader($container, $locator);
                break;

            case 'yml':
                $loader = new YamlFileLoader($container, $locator);
                break;

            case 'xml':
                $loader = new PhpFileLoader($container, $locator);
                break;

            default:
                throw new InvalidArgumentException('Invalid file type');
                break;
        }

        $loader->load($file);
    }
}
