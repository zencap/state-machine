<?php

namespace StateMachineBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class StateMachineExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter("statemachine.template_layout", $config['template_layout']);
        $stateMachineFactory = $container->getDefinition('statemachine.factory');

        $historyManager = $container->getDefinition($config['history_manager']);

        $stateMachineFactory->replaceArgument(0, $historyManager);
        $stateMachineFactory->replaceArgument(1, $config['transition_class']);

        foreach ($config['state_machines'] as $stateMachine) {
            foreach ($stateMachine['guards'] as &$guard) {
                $guard['callback'] = $this->resolveCallback($guard);
            }
            foreach ($stateMachine['pre_transitions'] as &$preTransition) {
                $preTransition['callback'] = $this->resolveCallback($preTransition);
            }
            foreach ($stateMachine['post_transitions'] as &$postTransition) {
                $postTransition['callback'] = $this->resolveCallback($postTransition);
            }

            $stateMachineFactory->addMethodCall('register', [$stateMachine]);
        }
    }

    /**
     * Detect if it's class or service.
     *
     * @param $callback
     *
     * @return Reference
     */
    private function resolveCallback($callback)
    {
        if (class_exists($callback['callback'])) {
            return $callback['callback'];
        } else {
            return new Reference($callback['callback']);
        }
    }
}
