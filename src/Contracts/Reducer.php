<?php

namespace Komparu\Utility\Contracts;

interface Reducer
{
    public function setState(Array $state);
    public function getState();
    public function handler($handler);
    public function register(Callable $resolve);
    public function on($type, Callable $resolve);
    public function call(Array $action);
    public function push(Array $action);
    public function unshift(Array $action);
    public function handle(Array $action = null);

    /**
     * @return \SplStack
     */
    public function stack();
}