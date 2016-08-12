<?php

use Komparu\Utility\Contracts\Reducer;

class SomeReducer
{
    /**
     * @param array $state
     * @param array $payload
     * @param Reducer $reducer
     * @return array
     */
    public static function merge(Array $state, Array $payload, Reducer $reducer)
    {
        return $state;
    }

    /**
     * @param array $state
     * @param array $payload
     * @return array
     */
    static public function fetchResources(Array $state, Array $payload)
    {
        return $state + ['resources' => [
            [
                'name' => 'foo',
            ],
            [
                'name' => 'bar',
            ],
        ]];
    }

    /**
     * @param array $state
     * @param array $payload
     * @return array
     */
    static public function fetchFields(Array $state, Array $payload)
    {
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
    }

    /**
     * @param array $state
     * @param array $payload
     * @return array
     */
    static public function fetchRelations(Array $state, Array $payload)
    {
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
    }

    /**
     * @param array $state
     * @param array $payload
     * @param Reducer $reducer
     */
    static public function request(Array $state, Array $payload, Reducer $reducer)
    {
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
    }

    /**
     * @param array $state
     * @param array $payload
     * @param Reducer $reducer
     */
    static public function extractResourcesToCall(Array $state, Array $payload, Reducer $reducer)
    {
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
    }

    /**
     * @param array $state
     * @param array $payload
     * @param Reducer $reducer
     * @return array
     */
    static public function call(Array $state, Array $payload, Reducer $reducer)
    {
        // Do your resource call and get the response
        $response = [
            'title' => 'test',
        ];

        return array_merge_recursive($state, [
            'resources' => [
                $payload['resource'] => compact('response')
            ]
        ]);
    }
}