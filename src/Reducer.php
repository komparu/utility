<?php

namespace Komparu\Utility;

class Reducer implements Contracts\Reducer
{
    protected $resolvers = [];
    protected $state = [];
    protected $stack;

    public function __construct()
    {
        $this->stack = new \SplStack();
    }

    public function setState(Array $state)
    {
        $this->state = $state;
    }

    public function getState()
    {
        return $this->state;
    }

    public function handler($handler)
    {
        $this->resolvers[] = ['type' => 'handler', 'callback' => $handler];
    }

    public function register(Callable $callback)
    {
        $this->resolvers[] = ['type' => 'reducer', 'callback' => $callback];
    }

    public function on($type, Callable $callback)
    {
        $this->resolvers[] = ['type' => 'action', 'name' => $type, 'callback' => $callback];
    }

    public function call(Array $action)
    {
        foreach($this->resolvers as $resolver) {

            $state = $this->getState();

            switch($resolver['type']) {

                case 'reducer':
                    $newState = call_user_func_array($resolver['callback'], [$state, $action, $this]);
                    break;

                case 'action':
                    $payload = isset($action['payload']) ? $action['payload'] : [];

                    // We only trigger the action if it is asked for
                    if($action['type'] != $resolver['name']) break;

                    $newState = call_user_func_array($resolver['callback'], [$state, $payload, $this]);
                    break;

                case 'handler':
                    $payload = isset($action['payload']) ? $action['payload'] : [];

                    // For esthetic reason make a camelCase version of the method name
                    $method = str_replace(' ', '', lcfirst(ucwords(strtolower(str_replace('_', ' ', $action['type'])))));

                    $newState = call_user_func_array([$resolver['callback'], $method], [$state, $payload, $this]);
                    break;

            }

            if($newState && $state !== $newState) {
                $this->setState($newState);
            }
        }
    }

    public function unshift(Array $action)
    {
        $this->stack->unshift($action);
    }

    public function push(Array $action)
    {
        $this->stack->push($action);
    }

    public function stack()
    {
        return $this->stack;
    }

    public function handle(Array $action = null)
    {
        if($action) {
            $this->push($action);
        }

        while(!$this->stack->isEmpty()) {
            $this->call($this->stack->pop());
        }
    }

}