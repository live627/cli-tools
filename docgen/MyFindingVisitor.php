<?php

use PhpParser\{Node, NodeVisitorAbstract};

class MyFindingVisitor extends NodeVisitorAbstract
{
	protected array $foundNodes;

	public function __construct(protected $filterCallback)
	{
	}

	public function afterTraverse(array $nodes): array
	{
		return $this->foundNodes;
	}

	public function beforeTraverse(array $nodes)
	{
		$this->foundNodes = [];

		return null;
	}

	public function enterNode(Node $node)
	{
		if (($this->filterCallback)($node))
			$this->foundNodes[] = $node;

		return null;
	}
}