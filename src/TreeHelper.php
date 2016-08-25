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
    public static function normalize(Array $nested, $idKey = 'id', $childrenKey = 'children', $parentKey = 'parent', $list = [], $parent = null)
    {
        // We must have an ID to continue
//        if(!isset($nested[$idKey])) {
//            $nested[$idKey] = static::generateId();
//        }

        // We use this to pass thru to the flat list. We don't need the nested model here
        $data = array_filter($nested, function($key) use ($childrenKey) {
            return !in_array($key, [$childrenKey]);
        }, ARRAY_FILTER_USE_KEY);

        // Add the parent reference
        $data[$parentKey] = $parent;

        // Start collection the child ids
        $data[$childrenKey] = [];

        // Find children elements
        if(isset($nested[$childrenKey]) && ArrayHelper::isCollection($nested[$childrenKey])) {

            // Normalize the children
            foreach($nested[$childrenKey] as $child) {

                // Find the child nodes if there are any
                $nodes = static::normalize($child, $idKey, $childrenKey, $parentKey, $list, $nested[$idKey]);

                // Add the child nodes to the list of node IDs
                $list += $nodes;

                // The first node in the list is its direct child
                $data[$childrenKey][] = current($nodes)[$idKey];
            }
        }

        // Add this item to the list
        $list[$nested[$idKey]] = $data;

        // Reverse the list so it gets the right parent child order of nodes
        return array_reverse($list);
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
     * @return array
     */
    public static function denormalize(Array $flattened, $idKey = 'id', $childrenKey = 'children', $parentKey = 'parent', $root = null)
    {
        // Find the root. If no root is provided, we try to find a node that has no parent
        $tree = $root
            ? static::findNode($flattened, $root, $idKey)
            : static::findRoot($flattened, $idKey);

        if(array_key_exists($parentKey, $tree))
        {
            $ids = array_keys(static::findChildren($flattened, $tree[$idKey], $parentKey));
        }
        elseif(array_key_exists($childrenKey, $tree)) {
            $ids = $tree[$childrenKey];
        }
        else {
            // Return early if there are no children
            return $tree;
        }

        // Find the children recursively
        foreach($ids as $child) {
            $children[] = static::denormalize($flattened, $idKey, $childrenKey, $parentKey, $child);
        }

        // Replace the list of IDs with actual nodes
        $tree[$childrenKey] = isset($children) ? $children : [];

        return $tree;
    }

    /**
     * Find the direct children of a node.
     *
     * @param array $nodes
     * @throws \Exception
     * @param $id
     * @param $idKey
     * @return array
     */
    public static function findNode(Array $nodes, $id, $idKey = 'id')
    {
        // We need a string or number here
        if(!is_string($id) && !is_numeric($id)) {
            throw new \Exception(sprintf('Node ID must be string or integer, %s given', gettype($id)));
        }

        return current(array_filter($nodes, function($node) use ($idKey, $id) {
            return $node[$idKey] == $id;
        }));
    }

    /**
     * @param array $nodes
     * @param $idKey
     * @return array
     */
    public static function findRoot(Array $nodes, $idKey = 'id')
    {
        return static::findNode($nodes, null, $idKey);
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