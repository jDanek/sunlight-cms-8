<?php

namespace Sunlight\Admin;

use Sunlight\Database\TreeFilterInterface;
use Sunlight\Database\TreeReader;
use Sunlight\User;

class PageFilter implements TreeFilterInterface
{
    function __construct(
        private ?int $type = null,
        private bool $checkPrivilege = false
    ) {}

    function filterNode(array $node, TreeReader $reader): bool
    {
        return ($this->type === null || $node['type'] == $this->type)
            && Admin::pageAccess($node, $this->checkPrivilege);
    }

    function acceptInvalidNodeWithValidChild(array $invalidNode, array $validChildNode, TreeReader $reader): bool
    {
        return true;
    }

    function getNodeSql(TreeReader $reader): string
    {
        $sql = '%__node__%.level<=' . User::getLevel();

        if ($this->type !== null) {
            $sql .= ' AND %__node__%.type=' . $this->type;
        }

        return $sql;
    }
}
