<?php
namespace LS\ProcrastinatorBundle\Tests\DependencyInjection;

use InvalidArgumentException;
use LS\ProcrastinatorBundle\DependencyInjection\LSProcrastinatorExtension;
use PHPUnit\Framework\TestCase;
use Procrastinator\DeferralManager;
use Procrastinator\Executor\Decorator\DoctrineEventConditionalExecutorDecorator;
use Procrastinator\Executor\Decorator\PhpFpmExecutorDecorator;
use Procrastinator\Scheduler\ImmediateScheduler;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

class LSProcrastinatorExtensionTest extends TestCase
{
    public function testManagedObjectsArePresent()
    {
        $container = new ContainerBuilder();
        $loader = new LSProcrastinatorExtension();
        $loader->load([], $container);

        $this->assertTrue($container->hasDefinition('procrastinator'));
        $this->assertTrue($container->hasDefinition('procrastinator.executor.real'));
        $this->assertTrue($container->hasAlias('procrastinator.executor'));
        $this->assertTrue($container->hasDefinition('procrastinator.scheduler'));
    }

    public function testExecutorIsDecoratedPerDefaultWithPhpFpmAndDoctrineEventConditional()
    {
        $container = new ContainerBuilder();
        $loader = new LSProcrastinatorExtension();
        $loader->load([], $container);

        $this->assertSame('procrastinator.executor.decorator.php_fpm', (string)$container->getAlias('procrastinator.executor'));
        $this->assertFalse($container->getAlias('procrastinator.executor')->isPublic());

        $definition = $container->getDefinition('procrastinator.executor.decorator.php_fpm');
        $this->assertSame(PhpFpmExecutorDecorator::class, $definition->getClass());
        $this->assertFalse($definition->isPublic());
        $this->assertSame('procrastinator.executor.decorator.doctrine_event_conditional', (string)$definition->getArgument(0));

        $definition = $container->getDefinition('procrastinator.executor.decorator.doctrine_event_conditional');
        $this->assertSame(DoctrineEventConditionalExecutorDecorator::class, $definition->getClass());
        $this->assertFalse($definition->isPublic());
        $this->assertSame('procrastinator.executor.real', (string)$definition->getArgument(0));
    }

    public function testEmptyDecoratorChain()
    {
        $container = new ContainerBuilder();
        $loader = new LSProcrastinatorExtension();
        $loader->load(['ls_procrastinator' => ['executor' => ['decorators' => []]]], $container);

        $this->assertSame('procrastinator.executor.real', (string)$container->getAlias('procrastinator.executor'));
    }

    public function testDoctrineListenerIsRegisteredAsListenerToAllDoctrineEvents()
    {
        $container = new ContainerBuilder();
        $loader = new LSProcrastinatorExtension();
        $loader->load([], $container);

        $tags = $container->getDefinition('procrastinator.executor.decorator.doctrine_event_conditional')->getTags();
        $this->assertGreaterThan(10, count($tags['doctrine.event_listener']));
    }

    public function testParametersAreDefined()
    {
        $container = new ContainerBuilder();
        $loader = new LSProcrastinatorExtension();
        $loader->load([], $container);

        $this->assertTrue($container->hasParameter('procrastinator.class'));
        $this->assertTrue($container->hasParameter('procrastinator.executor.class'));
        $this->assertTrue($container->hasParameter('procrastinator.scheduler.class'));
    }

    public function testRealExecutorMayNotBeInChain()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid configuration for path "ls_procrastinator.executor.decorators.0": For internal usage only');
        $this->createContainer(
            null,
            false,
            ['executor' => ['decorators' => ['procrastinator.executor.real']]]
        );
    }

    public function testExecutorAliasMayNotBeInChain()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid configuration for path "ls_procrastinator.executor.decorators.0": For internal usage only');
        $this->createContainer(
            null,
            false,
            ['executor' => ['decorators' => ['procrastinator.executor']]]
        );
    }

    public function testSchedulerIsImmediateScheduler()
    {
        $container = $this->createContainer();
        $this->assertSame(ImmediateScheduler::class, $container->getParameter('procrastinator.scheduler.class'));
        $this->assertInstanceOf(ImmediateScheduler::class, $container->get('procrastinator.scheduler'));
    }

    public function testGettingProcrastinator()
    {
        $this->assertInstanceOf(DeferralManager::class, $this->createContainer()->get('procrastinator'));
    }

    protected function createContainer($file = null, $debug = false, array $config = [], array $definitions = [])
    {
        $container = new ContainerBuilder(new ParameterBag(['kernel.debug' => $debug]));
        $container->registerExtension(new LSProcrastinatorExtension());

        $this->loadFromFile($container, $file);

        $container->addDefinitions($definitions);
        $container->loadFromExtension('ls_procrastinator', $config);

        $container->getCompilerPassConfig()->setOptimizationPasses([]);
        $container->getCompilerPassConfig()->setRemovingPasses([]);
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

            case 'php':
                $loader = new PhpFileLoader($container, $locator);
                break;

            default:
                throw new InvalidArgumentException('Invalid file type');
                break;
        }

        $loader->load($file);
    }
}
