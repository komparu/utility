<?php

use Komparu\Utility\Reducer;

require_once __DIR__ . '/../SomeReducer.php';

class ReducerTest extends PHPUnit_Framework_TestCase
{
    public function testUpdatingState()
    {
        $reducer = new Reducer();
        $reducer->setState([
            'foo' => 'some data',
        ]);
        $reducer->register(function($state, $action) {

            switch($action['type']) {

                case 'bar':
                    return $state += ['bar' => $action['payload']];

            }

            return $state;
        });

        $reducer->call([
            'type' => 'bar',
            'payload' => ['test' => 'hahaha'],
        ]);

        $expected = [
            'foo' => 'some data',
            'bar' => ['test' => 'hahaha'],
        ];

        $result = $reducer->getState();

        $this->assertSame($result, $expected);
    }

    public function testCallingResources()
    {
        $reducer = new Reducer();
        $reducer->setState([
            'foo' => 'some data',
        ]);
        $reducer->register(function($state, $action) {

            switch($action['type']) {

                case 'call':
                    $resource = $action['payload']['resource'];
                    $request = $action['payload']['request'];

                    // Here is the actual call...

                    $response = ['id' => 123, 'name' => 'test'];

                    return array_merge($state, [
                        $resource => compact('request', 'response'),
                    ]);
            }

            return $state;
        });

        $reducer->call([
            'type' => 'call',
            'payload' => [
                'resource' => 'bar',
                'request' => [
                    'id' => 123,
                ],
            ]
        ]);

        $expected = [
            'foo' => 'some data',
            'bar' => [
                'request' => [
                    'id' => 123,
                ],
                'response' => [
                    'id' => 123,
                    'name' => 'test',
                ]
            ],
        ];

        $result = $reducer->getState();

        $this->assertSame($result, $expected);
    }

    public function testNestedMessages()
    {
        $reducer = new Reducer();

        $reducer->register(function($state, $action, Reducer $reducer) {

            switch($action['type']) {

                case 'request':

                    // Update the state first...
                    $reducer->setState($state + [
                        $action['payload']['resource'] => [
                            'test' => 456
                        ]
                    ]);

                    // ... Then go an handle more messages
                    foreach($action['payload']['actions'] as $action) {
                        $reducer->push($action);
                    }
            }
        });

        $reducer->register(function($state, $action) {

            switch($action['type']) {

                case 'merge':

                    $from = $action['payload']['from'];
                    $to = $action['payload']['to'];

                    // From
                    $value = $state[$from['resource']][$from['field']];

                    // To
                    return $state + [
                        $to['resource'] => [
                            $to['field'] => $value
                        ]
                    ];

            }
        });


        $reducer->push([
            'type' => 'request',
            'payload' => [
                'resource' => 'foo',
                'request' => [
                    'id' => 123,
                ],
                'actions' => [
                    [
                        'type' => 'merge',
                        'payload' => [
                            'from' => [
                                'resource' => 'foo',
                                'field' => 'test',
                            ],
                            'to' => [
                                'resource' => 'bar',
                                'field' => 'test',
                            ],
                        ]
                    ]
                ]
            ]
        ]);

        $expected = [
            'foo' => [
                'test' => 456,
            ],
            'bar' => [
                'test' => 456
            ]
        ];

        $reducer->handle();
        $result = $reducer->getState();

        $this->assertSame($result, $expected);

    }

    public function testPushingToStack()
    {
        $reducer = new Reducer();
        $reducer->setState([
            'foo' => 'some data',
        ]);
        $reducer->register(function ($state) {
            return $state + ['bar' => 123];
        });

        $reducer->push(['type' => 'some action']);
        $reducer->handle();

        $result = $reducer->getState();

        $expected = ['foo' => 'some data', 'bar' => 123];

        $this->assertSame($result, $expected);
    }

    public function testStackWithSwitch()
    {
        $reducer = new Reducer();

        // A simple logger for viewing the flow
        $reducer->register(function($state, $action) {
            return array_merge_recursive($state, ['log' => [$action] ]);
        });

        $reducer->register(function($state, $action, Reducer $reducer) {

            switch($action['type']) {

                case 'REQUEST':

                    // Fetch resources
                    $reducer->push([
                        'type' => 'FETCH_RESOURCES',
                        'payload' => $action['payload'],
                    ]);

                    // Fetch fields
                    $reducer->push([
                        'type' => 'FETCH_FIELDS',
                        'payload' => $action['payload'],
                    ]);

                    // Fetch relations
                    $reducer->push([
                        'type' => 'FETCH_RELATIONS',
                        'payload' => $action['payload'],
                    ]);

                    // Determine and call child resources
                    $reducer->unshift([
                        'type' => 'EXTRACT_RESOURCES_TO_CALL',
                        'payload' => $action['payload'],
                    ]);

                    // Do merging stuff
                    $reducer->unshift([
                        'type' => 'MERGE',
                        'payload' => $action['payload'],
                    ]);

                break;

                case 'FETCH_RESOURCES':
                    return $state + ['resources' => [
                        [
                            'name' => 'foo',
                        ],
                        [
                            'name' => 'bar',
                        ],
                    ]];

                case 'FETCH_FIELDS':
                    return $state + ['fields' => [
                        [
                            'resource' => 'foo',
                            'name' => 'title',
                            'type' => 'text',
                        ],
                        [
                            'resource' => 'bar',
                            'name' => 'price',
                            'type' => 'price',
                        ],
                    ]];

                case 'FETCH_RELATIONS':
                    return $state + ['relations' => [
                        [
                            'from' => [
                                'resource' => 'bar',
                                'field' => 'price'
                            ],
                            'to' => [
                                'resource' => 'foo',
                                'field' => 'price'
                            ],
                        ],
                    ]];

                case 'EXTRACT_RESOURCES_TO_CALL':

                    // Do some magic here to extract the child resource calls...
                    $children = [
                        [
                            'resource' => 'foo',
                            'params' => [
                                'title' => 'test',
                            ],
                        ],
                        [
                            'resource' => 'bar',
                            'params' => [],
                        ],
                    ];

                    foreach($children as $child) {
                        $reducer->push([
                            'type' => 'CALL',
                            'payload' => $child
                        ]);
                    }

                    break;

                case 'CALL':

                    // Do your resource call and get the response
                    $response = [
                        'title' => 'test',
                    ];

                    return array_merge_recursive($state, [
                        'resources' => [
                            $action['payload']['resource'] => compact('response')
                        ]
                    ]);

                case 'MERGE':

                    // Do the merging stuff with the current data and relations

                    return $state;
            }
        });

        // There is one incoming request
        $reducer->handle([
            'type' => 'REQUEST',
            'payload' => [
                'website' => 123,
                'resource' => 'foo',
                'params' => [
                    'title' => 'test title',
                ],
            ],
        ]);

        $state = $reducer->getState();
        $log = array_map(function($action) { return $action['type']; }, $state['log']);

        $expected = [
            'REQUEST',
            'FETCH_RELATIONS',
            'FETCH_FIELDS',
            'FETCH_RESOURCES',
            'EXTRACT_RESOURCES_TO_CALL',
            'CALL',
            'CALL',
            'MERGE',
        ];

        $this->assertSame($log, $expected);
    }

    public function testStackWithSeparateActions()
    {
        $reducer = new Reducer();

        // A simple logger for viewing the flow
        $reducer->register(function($state, $action) {
            return array_merge_recursive($state, ['log' => [$action] ]);
        });

        $reducer->on('MERGE', function($state, $payload, Reducer $reducer) {
            return $state;
        });

        $reducer->on('FETCH_RESOURCES', function($state, $payload) {
            return $state + ['resources' => [
                [
                    'name' => 'foo',
                ],
                [
                    'name' => 'bar',
                ],
            ]];
        });

        $reducer->on('FETCH_FIELDS', function($state, $payload) {
            return $state + ['fields' => [
                [
                    'resource' => 'foo',
                    'name' => 'title',
                    'type' => 'text',
                ],
                [
                    'resource' => 'bar',
                    'name' => 'price',
                    'type' => 'price',
                ],
            ]];
        });

        $reducer->on('FETCH_RELATIONS', function($state, $payload) {

            // Call ...

            return $state + ['relations' => [
                [
                    'from' => [
                        'resource' => 'bar',
                        'field' => 'price'
                    ],
                    'to' => [
                        'resource' => 'foo',
                        'field' => 'price'
                    ],
                ],
            ]];
        });

        $reducer->on('REQUEST', function($state, $payload, Reducer $reducer) {

            // Fetch resources
            $reducer->push([
                'type' => 'FETCH_RESOURCES',
                'payload' => $payload,
            ]);

            // Fetch fields
            $reducer->push([
                'type' => 'FETCH_FIELDS',
                'payload' => $payload,
            ]);

            // Fetch relations
            $reducer->push([
                'type' => 'FETCH_RELATIONS',
                'payload' => $payload,
            ]);

            // Determine and call child resources
            $reducer->unshift([
                'type' => 'EXTRACT_RESOURCES_TO_CALL',
                'payload' => $payload,
            ]);

            // Do merging stuff
            $reducer->unshift([
                'type' => 'MERGE',
            ]);
        });

        $reducer->on('EXTRACT_RESOURCES_TO_CALL', function($state, $payload, Reducer $reducer) {

            // Do some business logic here to extract the child resource calls...

            $children = [
                [
                    'resource' => 'foo',
                    'params' => [
                        'title' => 'test',
                    ],
                ],
                [
                    'resource' => 'bar',
                    'params' => [],
                ],
            ];

            //

            foreach($children as $child) {
                $reducer->push([
                    'type' => 'CALL',
                    'payload' => $child
                ]);
            }

        });

        $reducer->on('CALL', function($state, $payload, Reducer $reducer) {

            // Do your resource call and get the response
            $response = [
                'title' => 'test',
            ];

            return array_merge_recursive($state, [
                'resources' => [
                    $payload['resource'] => compact('response')
                ]
            ]);

        });

        // There is one incoming request
        $reducer->handle([
            'type' => 'REQUEST',
            'payload' => [
                'website' => 123,
                'resource' => 'foo',
                'params' => [
                    'title' => 'test title',
                ],
            ],
        ]);

        $state = $reducer->getState();
        $log = array_map(function($action) { return $action['type']; }, $state['log']);

        $expected = [
            'REQUEST',
            'FETCH_RELATIONS',
            'FETCH_FIELDS',
            'FETCH_RESOURCES',
            'EXTRACT_RESOURCES_TO_CALL',
            'CALL',
            'CALL',
            'MERGE',
        ];

        $this->assertSame($log, $expected);
    }

    public function testStackWithStaticMethods()
    {
        $reducer = new Reducer();

        // A simple logger for viewing the flow
        $reducer->register(function($state, $action) {
            return array_merge_recursive($state, ['log' => [$action] ]);
        });

        $reducer->handler(SomeReducer::class);

        // There is one incoming request
        $reducer->handle([
            'type' => 'REQUEST',
            'payload' => [
                'website' => 123,
                'resource' => 'foo',
                'params' => [
                    'title' => 'test title',
                ],
            ],
        ]);

        $state = $reducer->getState();
        $log = array_map(function($action) { return $action['type']; }, $state['log']);

        $expected = [
            'REQUEST',
            'FETCH_RELATIONS',
            'FETCH_FIELDS',
            'FETCH_RESOURCES',
            'EXTRACT_RESOURCES_TO_CALL',
            'CALL',
            'CALL',
            'MERGE',
        ];

        $this->assertSame($log, $expected);
    }

}
