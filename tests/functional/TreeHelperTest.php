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
            'id' => 1,
            'title' => 'foo',
            'parent' => null,
            'children' => [
                [
                    'id' => 2,
                    'title' => 'bar',
                    'parent' => 1,
                    'children' => [],
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
            '##parent' => null,
            '@@children' => [
                [
                    '__id' => 2,
                    'title' => 'bar',
                    '##parent' => 1,
                    '@@children' => [],
                ]
            ]
        ];

        $this->assertSame(TreeHelper::denormalize($flattened, '__id', '@@children', '##parent'), $expected);
    }

}