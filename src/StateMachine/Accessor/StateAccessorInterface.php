<?php

namespace StateMachine\Accessor;

use StateMachine\State\StatefulInterface;

interface StateAccessorInterface
{
    /**
     * @param StatefulInterface $object
     * @param string            $value
     */
    public function setState(StatefulInterface &$object, $value);

    /**
     * @param StatefulInterface $object
     *
     * @return string
     */
    public function getState(StatefulInterface $object);
}
