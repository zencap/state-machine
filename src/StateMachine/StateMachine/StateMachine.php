<?php
namespace StateMachine\StateMachine;

use StateMachine\Accessor\StateAccessor;
use StateMachine\Accessor\StateAccessorInterface;
use StateMachine\Exception\StateMachineException;
use StateMachine\State\State;
use StateMachine\State\StatefulInterface;
use StateMachine\State\StateInterface;
use StateMachine\Transition\Transition;
use StateMachine\Transition\TransitionInterface;

class StateMachine implements StateMachineInterface
{
    /** @var  string */
    private $class;

    /** @var StatefulInterface */
    private $object;

    private $stateAccessor;
    /** @var  StateInterface */
    private $currentState;

    /** @var  array */
    private $transitions;

    /** @var  array */
    private $states;

    /** @var bool */
    private $booted;

    /**
     * @param string                 $class
     * @param StateAccessorInterface $stateAccessor
     * @param StatefulInterface      $object
     * @param string                 $property
     */
    public function __construct(
        $class,
        StatefulInterface $object,
        StateAccessorInterface $stateAccessor = null,
        $property = 'state'
    ) {
        $this->class = $class;
        $this->stateAccessor = $stateAccessor ?: new StateAccessor($property);
        $this->object = $object;
        $this->booted = false;
        $this->object->setStateMachine($this);
    }

    /**
     * {@inheritdoc}
     */
    public function boot()
    {
        if ($this->booted) {
            throw new StateMachineException("Statemachine is already booted");
        }
        if (null === $this->object) {
            throw new StateMachineException(
                sprintf("Cannot boot StateMachine without object, have you forgot to setObject()? ")
            );
        }
        if (get_class($this->object) !== $this->class) {
            throw new StateMachineException(
                sprintf(
                    "StateMachine expected object of class %s instead of %s",
                    $this->class,
                    get_class($this->object)
                )
            );
        }

        $state = $this->stateAccessor->getState($this->object);
        //no state found for the object it means it's new instance, set initial state
        if (null === $state || '' == $state) {
            $state = $this->getInitialState();
            $this->stateAccessor->setState($this->object, $state->getName());
        }

        $this->boundTransitionsToStates();
        $this->currentState = $state;
        $this->booted = true;
    }

    /**
     * {@inheritdoc}
     */
    public function getObject()
    {
        return $this->object;
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentState()
    {
        return $this->currentState;
    }

    /**
     * {@inheritdoc}
     */
    public function addTransition($from = null, $to = null)
    {
        if ($this->booted) {
            throw new StateMachineException("Cannot add more transitions to booted StateMachine");
        }
        if (!isset($this->states[$from])) {
            throw new StateMachineException(
                sprintf(
                    "State with name: %s is not found, states available are: %s",
                    $from,
                    implode(',', $this->states)
                )
            );
        }
        if (!isset($this->states[$to])) {
            throw new StateMachineException(
                sprintf(
                    "State with name: %s is not found, states available are: %s",
                    $to,
                    implode(',', $this->states)
                )
            );
        }

        $fromState = $this->states[$from];
        $toState = $this->states[$to];
        $transition = new Transition($fromState, $toState);
        $this->transitions[$transition->getName()] = $transition;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addState(
        $name,
        $type = StateInterface::TYPE_NORMAL
    ) {
        //@TODO check if there's more than one initial state
        //@TODO check for duplicate states
        $state = new State($name, $type);
        $this->states[$name] = $state;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getAllowedTransitions()
    {
        return $this->currentState->getTransitions();
    }

    /**
     * {@inheritdoc}
     */
    public function canTransitionTo(
        $state
    ) {
        return in_array($state, $this->currentState->getTransitions());
    }

    /**
     * {@inheritdoc}
     */
    public function transitionTo(
        $state
    ) {
        if (!$this->canTransitionTo($state)) {
            throw new StateMachineException(
                sprintf(
                    "There's no transition defined from (%s) to (%s), allowed transitions to : [ %s ]",
                    $this->currentState->getName(),
                    $state,
                    implode(',', $this->currentState->getTransitions())
                )
            );
        }
        $this->currentState = $this->states[$state];
        $this->stateAccessor->setState($this->object, $state);
        //@TODO here we trigger guards
        //@TODO we trigger before and after actions
        //@TODO may be fire some events

        return true;
    }

    /**
     * Find the initial state in the state machine
     * @return StateInterface
     * @throws StateMachineException
     */
    private function getInitialState()
    {
        /** @var StateInterface $state */
        foreach ($this->states as $state) {
            if ($state->isInitial()) {
                return $state;
            }
        }

        throw new StateMachineException("No initial state is found");
    }

    /**
     * Add transitions to states, triggered after booting
     */
    private function boundTransitionsToStates()
    {
        /** @var StateInterface $state */
        foreach ($this->states as $state) {
            $allowedTransitions = [];
            $allowedTransitionsObjects = [];
            /** @var TransitionInterface $transition */
            foreach ($this->transitions as $transition) {
                if ($transition->getFromState()->getName() == $state->getName()) {
                    $allowedTransitionsObjects[] = $transition;
                    $allowedTransitions [] = $transition->getToState()->getName();
                }
            }
            $state->setTransitions($allowedTransitions);
            $state->setTransitionObjects($allowedTransitionsObjects);
        }

    }
}
