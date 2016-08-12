<?php


use Komparu\Utility\TreeHelper;

class TreeHelperTest extends PHPUnit_Framework_TestCase
{
    public function testNormalize()
    {
        $nested = [
            'id' => 1,
            'title' => 'foo',
            'children' => [
                [
                    'id' => 2,
                    'title' => 'bar',
                ]
            ]
        ];

        $expected = [
            [
                'id' => 1,
                'title' => 'foo',
                'parent' => null,
                'children' => [2],
            ],
            [
                'id' => 2,
                'title' => 'bar',
                'parent' => 1,
                'children' => [],
            ],
        ];

        $this->assertSame(TreeHelper::normalize($nested), $expected);
    }

    public function testNormalizeWithCustomKeys()
    {
        $nested = [
            '__id' => 1,
            'title' => 'foo',
            '@@children' => [
                [
                    '__id' => 2,
                    'title' => 'bar',
                ]
            ]
        ];

        $expected = [
            [
                '__id' => 1,
                'title' => 'foo',
                '##parent' => null,
                '@@children' => [2],
            ],
            [
                '__id' => 2,
                'title' => 'bar',
                '##parent' => 1,
                '@@children' => [],
            ],
        ];

        $this->assertSame(TreeHelper::normalize($nested, '__id', '@@children', '##parent'), $expected);
    }

    public function testDenormalize()
    {
        $flattened = [
            [
                'id' => 1,
                'title' => 'foo',
                'parent' => null,
            ],
            [
                'id' => 2,
                'title' => 'bar',
                'parent' => 1,
            ],
            [
                'id' => 3,
                'title' => 'baz',
                'parent' => 2,
            ],
        ];

        $expected = [
            'id' => 1,
            'title' => 'foo',
            'children' => [
                [
                    'id' => 2,
                    'title' => 'bar',
                    'children' => [
                        [
                            'id' => 3,
                            'title' => 'baz',
                            'children' => []
                        ]
                    ],
                ]
            ]
        ];

        $this->assertSame(TreeHelper::denormalize($flattened), $expected);
    }

    public function testDenormalizeWithCustomKeys()
    {
        $flattened = [
            [
                '__id' => 1,
                'title' => 'foo',
                '##parent' => null,
                '@@children' => [2],
            ],
            [
                '__id' => 2,
                'title' => 'bar',
                '##parent' => 1,
                '@@children' => [],
            ],
        ];

        $expected = [
            '__id' => 1,
            'title' => 'foo',
            '@@children' => [
                [
                    '__id' => 2,
                    'title' => 'bar',
                    '@@children' => [],
                ]
            ]
        ];

        $this->assertSame(TreeHelper::denormalize($flattened, '__id', '@@children', '##parent'), $expected);
    }

    public function testFindParents()
    {
        $flattened = [
            [
                'id' => 1,
                'title' => 'foo',
                'parent' => null,
                'children' => [2],
            ],
            [
                'id' => 2,
                'title' => 'bar',
                'parent' => 1,
                'children' => [],
            ],
        ];

        $expected = [
            [
                'id' => 1,
                'title' => 'foo',
                'parent' => null,
                'children' => [2],
            ],
            [
                'id' => 2,
                'title' => 'bar',
                'parent' => 1,
                'children' => [],
            ],
        ];

        $this->assertSame(TreeHelper::findParents($flattened, 2), $expected);
    }

    public function testFindParentsExcludingSelf()
    {
        $flattened = [
            [
                'id' => 1,
                'title' => 'foo',
                'parent' => null,
                'children' => [2],
            ],
            [
                'id' => 2,
                'title' => 'bar',
                'parent' => 1,
                'children' => [],
            ],
        ];

        $expected = [
            [
                'id' => 1,
                'title' => 'foo',
                'parent' => null,
                'children' => [2],
            ],
        ];

        $this->assertSame(TreeHelper::findParents($flattened, 2, $includeNode = false), $expected);
    }

    public function testFilter()
    {
        $flattened = [
            [
                'id' => 1,
                'title' => 'foo',
                'parent' => null,
                'children' => [2],
            ],
            [
                'id' => 2,
                'title' => 'bar',
                'parent' => 1,
                'children' => [],
            ],
        ];

        $expected = [
            [
                'id' => 1,
                'title' => 'foo',
                'parent' => null,
                'children' => [2],
            ],
        ];

        $this->assertSame(TreeHelper::filter($flattened, ['parent' => null]), $expected);
    }

    public function testFindAllChildren()
    {
        $flattened = [
            [
                'id' => 1,
                'title' => 'foo',
                'parent' => null,
                'children' => [2],
            ],
            [
                'id' => 2,
                'title' => 'bar',
                'parent' => 1,
                'children' => [3],
            ],
            [
                'id' => 3,
                'title' => 'baz',
                'parent' => 2,
                'children' => [],
            ],
        ];

        $expected = [
            [
                'id' => 2,
                'title' => 'bar',
                'parent' => 1,
                'children' => [3],
            ],
            [
                'id' => 3,
                'title' => 'baz',
                'parent' => 2,
                'children' => [],
            ],
        ];

        $this->assertSame(TreeHelper::findAllChildren($flattened, 1), $expected);
    }

    public function testNormalizeWithLargeTree()
    {
        $data = file_get_contents(__DIR__ . '/../data/test.json');
        $tree = json_decode($data, true);

        $start = microtime(true);
        $result = TreeHelper::normalize($tree);
        $end = microtime(true);
        var_dump($end - $start);

        $this->assertCount(381, $result);
    }

}