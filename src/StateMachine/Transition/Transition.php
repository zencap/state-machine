<?php

namespace StateMachine\Transition;

use StateMachine\State\StateInterface;

class Transition implements TransitionInterface
{
    /** @var StateInterface */
    protected $fromState;
    /** @var StateInterface */
    protected $toState;
    /** @var string */
    protected $name;
    /** @var  string */
    protected $eventName;
    /** @var array */
    protected $preTransitions;
    /** @var array */
    protected $postTransitions;
    /** @var array */
    protected $guards;

    /**
     * @param StateInterface $fromState
     * @param StateInterface $toState
     * @param string         $eventName
     */
    public function __construct(StateInterface $fromState = null, StateInterface $toState = null, $eventName = null)
    {
        $this->fromState = $fromState;
        $this->toState = $toState;
        $this->eventName = $eventName;
        $this->guards = [];
        $this->preTransitions = [];
        $this->postTransitions = [];
    }

    /**
     * {@inheritdoc}
     */
    public function getFromState()
    {
        return $this->fromState;
    }

    /**
     * {@inheritdoc}
     */
    public function getToState()
    {
        return $this->toState;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->fromState->getName().static::EDGE_SYMBOL.$this->toState->getName();
    }

    /**
     * {@inheritdoc}
     */
    public function getEventName()
    {
        return $this->eventName;
    }

    /**
     * {@inheritdoc}
     */
    public function getPreTransitions()
    {
        return $this->preTransitions;
    }

    /**
     * {@inheritdoc}
     */
    public function addPreTransition($preTransition)
    {
        $this->preTransitions[] = $this->resolveCallable($preTransition);
    }

    /**
     * {@inheritdoc}
     */
    public function getPostTransitions()
    {
        return $this->postTransitions;
    }

    /**
     * {@inheritdoc}
     */
    public function addPostTransition($postTransition)
    {
        $this->postTransitions[] = $this->resolveCallable($postTransition);
    }

    /**
     * {@inheritdoc}
     */
    public function getGuards()
    {
        return $this->guards;
    }

    /**
     * {@inheritdoc}
     */
    public function addGuard($guard)
    {
        $this->guards[] = $this->resolveCallable($guard);
    }

    /**
     * Resolve callable which can be one of
     *  - closure
     *  - class instance and method
     *  - class path and static method.
     *
     * @param $callable
     *
     * @return string
     */
    public static function resolveCallable($callable)
    {
        if ($callable instanceof \Closure) {
            $callableClass = 'closure';
        } elseif (is_object($callable[0])) {
            $callableClass = get_class($callable[0]);
        } else {
            $callableClass = $callable[0].'::'.$callable[1];
        }

        return $callableClass;
    }
}
