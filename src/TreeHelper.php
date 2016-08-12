<?php

namespace Komparu\Utility;

class TreeHelper
{
    /**
     * Create a flat list of Nodes, grouped by their ID. This will make these things possible:
     * - Look up node information by ID
     * - Store the node information separately
     * - Build a tree again based on the parent/children relationship
     *
     * @param array $nested
     * @param string $idKey
     * @param string $childrenKey
     * @param string $parentKey
     * @param array $list
     * @param null $parent
     * @return array
     */
    public static function normalize(Array $nested, $idKey = 'id', $childrenKey = 'children', $parentKey = 'parent')
    {
        $stack = new \SplStack();
        $stack->push($nested);
        $list = [];

        while(!$stack->isEmpty()) {
            $node = $stack->pop();
            $list[] = static::normalizeWithStack($stack, $node, $idKey, $childrenKey, $parentKey);
        }

        return $list;
    }

    /**
     * @param \SplStack $stack
     * @param array $nested
     * @param string $idKey
     * @param string $childrenKey
     * @param string $parentKey
     * @param array $list
     * @param null $parent
     * @return array
     */
    protected static function normalizeWithStack(\SplStack $stack, Array $nested, $idKey = 'id', $childrenKey = 'children', $parentKey = 'parent')
    {
        // We must have an ID to continue
        if(!isset($nested[$idKey])) {
            $nested[$idKey] = static::generateId();
        }

        // We use this to pass thru to the flat list. We don't need the nested model here
        $data = array_filter($nested, function($key) use ($childrenKey) {
            return !in_array($key, [$childrenKey]);
        }, ARRAY_FILTER_USE_KEY);

        // Add the parent reference
        $data[$parentKey] = isset($nested[$parentKey]) ? $nested[$parentKey] : null;

        // Start collection the child ids
        $data[$childrenKey] = [];

        // Find children elements
        if(isset($nested[$childrenKey]) && ArrayHelper::isCollection($nested[$childrenKey])) {

            // Normalize the children
            foreach(array_reverse($nested[$childrenKey]) as $child) {

                // We must have an ID to continue
                if(!isset($child[$idKey])) {
                    $child[$idKey] = static::generateId();
                }

                // Add the parent relationship to this child
                $child[$parentKey] = $nested[$idKey];

                // Normalize the children for this child using a
                // stack for performance reasons.
                $stack->push($child);

                // The first node in the list is its direct child
                $data[$childrenKey][] = $child[$idKey];
            }

        }

        return $data;
    }

    /**
     * Get a flattened node list back into a tree of nodes.
     *
     * If no $root is provided, then the first node with no parent is used as root as a fallback.
     *
     * @param array       $flattened
     * @param string      $idKey
     * @param string      $childrenKey
     * @param string      $parentKey
     * @param string|null $root The node ID.
     * @throws \Exception
     * @return array
     */
    public static function denormalize(Array $flattened, $idKey = 'id', $childrenKey = 'children', $parentKey = 'parent', $root = null)
    {
        // Find the root. If no root is provided, we try to find a node that has no parent
        $tree = $root
            ? static::findNode($flattened, $root, $idKey)
            : static::findRoot($flattened, $parentKey);

        // See if there is a node found where we can work with
        if(!$tree) {
            throw new \Exception(sprintf('The node "%s" for idKey "%s" could not be found', $root, $idKey));
        }

        $id = $tree[$idKey];

        // Find the children based on their parent
        $ids = array_map(function($node) use ($idKey) {
            return $node[$idKey];
        }, array_filter($flattened, function($node) use ($parentKey, $id) {
            return $node[$parentKey] == $id;
        }));

        // Or find the children based on their children ids
//        $ids = array_key_exists($childrenKey, $tree) ? $tree[$childrenKey] : [];

        // Denormalize the children recursively
        foreach($ids as $child) {
            $children[] = static::denormalize($flattened, $idKey, $childrenKey, $parentKey, $child);
        }

        // Replace the list of IDs with actual nodes
        $tree[$childrenKey] = isset($children) ? $children : [];

        // Remove the parent, because we now have the actual children
        unset($tree[$parentKey]);

        return $tree;
    }

    /**
     * Find the direct children of a node.
     *
     * @param array $nodes
     * @throws \Exception
     * @param $id
     * @param $idKey
     * @return array|bool
     */
    public static function findNode(Array $nodes, $id, $idKey = 'id')
    {
        return current(array_filter($nodes, function($node) use ($idKey, $id) {
            return $node[$idKey] == $id;
        }));
    }

    /**
     * When we don't have any identifier, we can try to find the root element
     * by searching the node that has no parent.
     *
     * @param array $nodes
     * @param string $parentKey
     * @return array|bool
     */
    public static function findRoot(Array $nodes, $parentKey = 'parent')
    {
        return static::findNode($nodes, null, $parentKey);
    }

    /**
     * Find the direct children of a node.
     *
     * @param array $nodes
     * @param $id
     * @param $parentKey
     * @return array
     */
    public static function findChildren(Array $nodes, $id = null, $parentKey = 'parent')
    {
        return array_filter($nodes, function($node) use ($parentKey, $id) {
            return $node[$parentKey] == $id;
        });
    }

    /**
     * Find all the children recursively for one node.
     *
     * @param array $nodes
     * @param null $id
     * @param string $idKey
     * @param string $parentKey
     * @return array
     */
    public static function findAllChildren(Array $nodes, $id = null, $idKey = 'id', $parentKey = 'parent')
    {
        $children = static::findChildren($nodes, $id, $parentKey);

        foreach($children as $child) {
            $children = array_merge($children, static::findAllChildren($nodes, $child[$idKey], $idKey, $parentKey));
        }

        return $children;
    }

    /**
     * @param array $nodes
     * @param $id
     * @param string $idKey
     * @param string $parentKey
     * @param bool $includeNode
     * @return array
     */
    public static function findParents(Array $nodes, $id, $includeNode = true, $idKey = 'id', $parentKey = 'parent')
    {
        // Find the current node to look for its parent
        $node = static::findNode($nodes, $id, $idKey);

        // Gather all parents recursively
        $parents = $node[$parentKey]
            ? static::findParents($nodes, $node['parent'], true, $idKey, $parentKey)
            : [];

        return $includeNode ? array_merge($parents, [$node]) : $parents;
    }

    /**
     * Narrow down the list of nodes with multiple where statements
     *
     * @param array $nodes
     * @param array $where
     * @return array
     */
    public static function filter(Array $nodes, Array $where)
    {
        return array_filter($nodes, function($node) use ($where) {

            foreach($where as $key => $value) {
                if(!array_key_exists($key, $node))  return;
                if($node[$key] !== $value) return;
            }

            return $node;
        });
    }

    /**
     * Get all reference nodes
     *
     * @param array $nodes
     * @param string $referenceKey
     * @return array
     */
    public static function findReferences(Array $nodes, $referenceKey = 'reference')
    {
        $nodesWithReferences = array_filter($nodes, function($node) use ($referenceKey) {
            return $node[$referenceKey];
        });

        return array_map(function($node) use ($referenceKey) {
            return $node[$referenceKey];
        }, $nodesWithReferences);
    }

    /**
     * If a node has a reference, then use the reference instead of the original one.
     *
     * @param array $nodes
     * @param string $referenceKey
     * @return array
     */
    public static function replaceWithReferences(Array $nodes, $referenceKey = 'reference')
    {
        return array_map(function($node) use ($referenceKey) {
            return $node[$referenceKey] ?: $node;
        }, $nodes);
    }

    /**
     * @param array $nodes
     * @return array
     */
    public static function mergeReferences(Array $nodes)
    {
        return array_merge($nodes, static::findReferences($nodes));
    }

    /**
     * Get the unique values from a flat list, grouped by the
     * provided key.
     *
     * @param array  $nodes
     * @param string $key
     * @param string|array $except
     * @return array
     */
    public static function unique(Array $nodes, $key, $except = null)
    {
        $unique = array_values(array_unique(array_map(function($node) use ($key) {
            return $node[$key];
        }, $nodes)));

        if($except) {
            return array_filter($unique, function($value) use($except) {
                return !in_array($value, (array) $except);
            });
        }


        return $unique;
    }

    /**
     * Generate a unique ID for nodes that don't have one already.
     *
     * @return string
     */
    public static function generateId()
    {
        return chr(mt_rand(97, 122)) . substr(md5(microtime(false)), 1); // The same as in Schema package
    }

}