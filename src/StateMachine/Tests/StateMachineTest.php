<?php
namespace StateMachine\Tests;

use StateMachine\State\StateInterface;
use StateMachine\StateMachine\StateMachine;
use StateMachine\Tests\Entity\Order;
use StateMachine\Tests\Fixtures\StateMachineFixtures;

class StateMachineTest extends \PHPUnit_Framework_TestCase
{
    public function testCorrectObject()
    {
        $stateMachine = new StateMachine(new Order(1));
        $this->assertNotNull($stateMachine->getObject());
    }

    public function testWithNoInitialState()
    {
        $this->setExpectedException(
            'StateMachine\Exception\StateMachineException',
            "No initial state is found"
        );

        $stateMachine = new StateMachine(new Order(1));
        $stateMachine->addState('pending');
        $stateMachine->addState('checking_out');
        $stateMachine->boot();
    }

    public function testTransitionObject()
    {
        $stateMachine = StateMachineFixtures::getBidStateMachine();
        $stateMachine->boot();
        $this->assertEquals(1, count($stateMachine->getCurrentState()->getTransitionObjects()));
    }

    public function testTwoInitialStates()
    {
        $this->setExpectedException(
            'StateMachine\Exception\StateMachineException',
            "Statemachine cannot have more than one initial state, current initial state is (pending)"
        );

        $stateMachine = StateMachineFixtures::getOrderStateMachine();
        $stateMachine->addState('another_initial_state', StateInterface::TYPE_INITIAL);
    }

    public function testFromAnyTransition()
    {
        $stateMachine = StateMachineFixtures::getBidStateMachine();
        $stateMachine->addTransition(null, 'cancelled');
        $transitions = $stateMachine->getTransitions();
        $this->assertArrayHasKey('new::cancelled', $transitions);
        $this->assertArrayHasKey('error::cancelled', $transitions);
        $this->assertArrayHasKey('committed::cancelled', $transitions);
        $this->assertArrayHasKey('paid::cancelled', $transitions);
        //not to have transition to self
        $this->assertArrayNotHasKey('cancelled::cancelled', $transitions);
        $stateMachine->boot();
        $this->assertEquals(2, count($stateMachine->getCurrentState()->getTransitionObjects()));
    }

    public function testToAnyTransition()
    {
        $stateMachine = StateMachineFixtures::getBidStateMachine();
        $stateMachine->addTransition('paid', null);
        $transitions = $stateMachine->getTransitions();
        $this->assertArrayHasKey('paid::new', $transitions);
        $this->assertArrayHasKey('paid::cancelled', $transitions);
        $this->assertArrayHasKey('paid::originating', $transitions);
        $this->assertArrayHasKey('paid::committed', $transitions);
        $this->assertArrayHasKey('paid::error', $transitions);
        //not to have transition to self
        $this->assertArrayNotHasKey('paid::paid', $transitions);

    }

    public function testFromManyTransitions()
    {
        $stateMachine = StateMachineFixtures::getBidStateMachine();
        $stateMachine->addState('from_many');
        $stateMachine->addTransition(['paid', 'originating'], 'from_many');
        $transitions = $stateMachine->getTransitions();
        $this->assertArrayHasKey('originating::from_many', $transitions);
        $this->assertArrayHasKey('paid::from_many', $transitions);
    }

    public function testToManyTransitions()
    {
        $stateMachine = StateMachineFixtures::getBidStateMachine();
        $stateMachine->addTransition('paid', ['new', 'originating']);
        $transitions = $stateMachine->getTransitions();
        $this->assertArrayHasKey('paid::new', $transitions);
        $this->assertArrayHasKey('paid::originating', $transitions);
    }

    public function testFromManyToAll()
    {
        $stateMachine = StateMachineFixtures::getBidStateMachine();
        $stateMachine->addState('source1');
        $stateMachine->addState('source2');
        $stateMachine->addTransition(['source1', 'source2'], null);
        $transitions = $stateMachine->getTransitions();
        $this->assertArrayHasKey('source1::new', $transitions);
        $this->assertArrayHasKey('source2::new', $transitions);

        $this->assertArrayHasKey('source1::cancelled', $transitions);
        $this->assertArrayHasKey('source2::cancelled', $transitions);

        $this->assertArrayHasKey('source1::originating', $transitions);
        $this->assertArrayHasKey('source2::originating', $transitions);

        $this->assertArrayHasKey('source1::committed', $transitions);
        $this->assertArrayHasKey('source2::committed', $transitions);

        $this->assertArrayHasKey('source1::error', $transitions);
        $this->assertArrayHasKey('source2::error', $transitions);

        $this->assertArrayHasKey('source1::paid', $transitions);
        $this->assertArrayHasKey('source2::paid', $transitions);
    }

    public function testFromAllToMany()
    {
        $stateMachine = StateMachineFixtures::getBidStateMachine();
        $stateMachine->addState('destination1');
        $stateMachine->addState('destination2');
        $stateMachine->addTransition(null, ['destination1', 'destination2']);
        $transitions = $stateMachine->getTransitions();
        $this->assertArrayHasKey('new::destination1', $transitions);
        $this->assertArrayHasKey('new::destination2', $transitions);

        $this->assertArrayHasKey('cancelled::destination1', $transitions);
        $this->assertArrayHasKey('cancelled::destination2', $transitions);

        $this->assertArrayHasKey('originating::destination1', $transitions);
        $this->assertArrayHasKey('originating::destination2', $transitions);

        $this->assertArrayHasKey('committed::destination1', $transitions);
        $this->assertArrayHasKey('committed::destination2', $transitions);

        $this->assertArrayHasKey('error::destination1', $transitions);
        $this->assertArrayHasKey('error::destination2', $transitions);

        $this->assertArrayHasKey('paid::destination1', $transitions);
        $this->assertArrayHasKey('paid::destination2', $transitions);
    }

    public function testAllowedTransitions()
    {
        $stateMachine = StateMachineFixtures::getOrderStateMachine();
        $stateMachine->boot();

        $allowedTransitions = $stateMachine->getAllowedTransitions();

        $this->assertEquals("pending", $stateMachine->getCurrentState());

        $this->assertEquals(['checking_out', 'cancelled'], $allowedTransitions);
        $this->assertTrue($stateMachine->canTransitionTo('cancelled'));
        $this->assertFalse($stateMachine->canTransitionTo('shipped'));
    }

    public function testNotAllowedTransition()
    {
        $this->setExpectedException(
            'StateMachine\Exception\StateMachineException',
            "There's no transition defined from (pending) to (shipped), allowed transitions to : [ checking_out,cancelled ]"
        );

        $stateMachine = StateMachineFixtures::getOrderStateMachine();
        $stateMachine->boot();
        $stateMachine->transitionTo('shipped');
    }

    public function testTransitionTo()
    {
        $stateMachine = StateMachineFixtures::getOrderStateMachine();
        $stateMachine->boot();
        $stateMachine->transitionTo('checking_out');
        $this->assertEquals($stateMachine->getCurrentState(), 'checking_out');
    }

    public function testStateTypes()
    {
        $stateMachine = StateMachineFixtures::getOrderStateMachine();
        $stateMachine->boot();
        $this->assertEquals($stateMachine->getCurrentState()->getType(), StateInterface::TYPE_INITIAL);
        $this->assertTrue($stateMachine->getCurrentState()->isInitial());

        $stateMachine->transitionTo('checking_out');
        $stateMachine->transitionTo('purchased');
        $stateMachine->transitionTo('shipped');
        $this->assertTrue($stateMachine->getCurrentState()->isNormal());
        $stateMachine->transitionTo('refunded');
        $this->assertEquals($stateMachine->getCurrentState()->getType(), StateInterface::TYPE_FINAL);
        $this->assertTrue($stateMachine->getCurrentState()->isFinal());
    }

    public function testAddTransitionToBootedMachine()
    {
        $this->setExpectedException('StateMachine\Exception\StateMachineException');
        $stateMachine = StateMachineFixtures::getOrderStateMachine();
        $stateMachine->addTransition("new", 'cancelled');
        $stateMachine->boot();
    }

    public function testPreTransitionToBootedMachine()
    {
        $this->setExpectedException('StateMachine\Exception\StateMachineException');
        $stateMachine = StateMachineFixtures::getOrderStateMachine();
        $stateMachine->boot();
        $stateMachine->addPostTransition(
            "new::committed",
            function () {
            }
        );
    }

    public function testPostTransitionToBootedMachine()
    {
        $this->setExpectedException('StateMachine\Exception\StateMachineException');
        $stateMachine = StateMachineFixtures::getOrderStateMachine();
        $stateMachine->boot();
        $stateMachine->addPreTransition(
            "new::committed",
            function () {
            }
        );
    }

    public function testGuardToBootedMachine()
    {
        $this->setExpectedException('StateMachine\Exception\StateMachineException');
        $stateMachine = StateMachineFixtures::getOrderStateMachine();
        $stateMachine->boot();
        $stateMachine->addGuard(
            "new::committed",
            function () {
            }
        );
    }

    public function testAddTransitionWithBootedMachine()
    {
        $this->setExpectedException(
            'StateMachine\Exception\StateMachineException',
            'Cannot add more transitions to booted StateMachine'
        );
        $stateMachine = StateMachineFixtures::getOrderStateMachine();
        $stateMachine->boot();
        $stateMachine->addTransition("new::committed");
    }


    public function testGetAllowedTransitionsForNonBootedMachine()
    {
        $this->setExpectedException('StateMachine\Exception\StateMachineException');
        $stateMachine = StateMachineFixtures::getOrderStateMachine();
        $stateMachine->getAllowedTransitions();
    }

    public function testCanTransitToForNonBootedMachine()
    {
        $this->setExpectedException('StateMachine\Exception\StateMachineException');
        $stateMachine = StateMachineFixtures::getOrderStateMachine();
        $stateMachine->canTransitionTo('paid');
    }


    public function testTransitToForNonBootedMachine()
    {
        $this->setExpectedException('StateMachine\Exception\StateMachineException');
        $stateMachine = StateMachineFixtures::getOrderStateMachine();
        $stateMachine->transitionTo('paid');
    }

    public function testBootTwice()
    {
        $this->setExpectedException('StateMachine\Exception\StateMachineException', 'Statemachine is already booted');
        $stateMachine = StateMachineFixtures::getOrderStateMachine();
        $stateMachine->boot();
        $stateMachine->boot();

    }
}
