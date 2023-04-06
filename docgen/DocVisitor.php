<?php

namespace live627\DocGen;

use phpDocumentor\Reflection\{DocBlockFactory, Type, Types};
use PhpParser\{Node, NodeFinder, NodeVisitorAbstract};

class DocVisitor extends NodeVisitorAbstract
{
	private DocBlockFactory $factory;
	private NodeFinder $nodeFinder;
	private array $warnings;

	public function __construct(private $filterCallback)
	{
		$this->factory = DocBlockFactory::createInstance();
		$this->nodeFinder = new NodeFinder;
	}

	public function enterNode(Node $node)
	{
		$this->warnings = [];
		if (($this->filterCallback)($node))
		{
			$docComment = $node->getDocComment();

			if ($docComment !== null)
			{
				// Remove all comments.
				$node->setAttribute('comments', []);

				$docblock = $this->factory->create($docComment->getText());
				$node->setAttribute('docblock', $docblock);

				$tags = [];
				foreach ($docblock->getTags() as $tag)
					$tags[$tag->getName()][] = $tag;
				$node->setAttribute('tags', $tags);

				$node->returnType = $this->setReturnType($tags, $node);

				$this->findParams($tags, $node);
				$node->setAttribute('warnings', $this->warnings);
			}
			$stmts = array_map(
				fn(Node $stmt) => $stmt->args[0]->value->value,
				$this->nodeFinder->find(
					$node,
					fn(Node $stmt) => $stmt instanceof Node\Expr\FuncCall
						&& $stmt->name instanceof Node\Name
						&& $stmt->name->toString() === 'call_integration_hook'
						&& $stmt->args[0]->value instanceof Node\Scalar\String_
				)
			);
			if ($stmts != [])
				$node->setAttribute('hooks', $stmts);

			// Clean out the function body
			$node->stmts = [];
		}
	}

	private function setReturnType(array $tags, Node\Stmt\Function_|Node $node): Node\Name
	{
		if (isset($tags['return']))
		{
			$type = $tags['return'][0]->getType();
			if ($node->returnType?->name !== (string)$type)
			{
				$type = $this->getType($type);

				return new Node\Name((string)$type ?: '???');
			}
		}

		return new Node\Name('void');
	}

	private function getType(Type $type): Type
	{
		if ($type instanceof Types\AggregatedType && !$type->has(2))
		{
			if ($type->get(0) instanceof Types\Null_)
				$type = new Types\Nullable($type->get(1));
			elseif ($type->get(1) instanceof Types\Null_)
				$type = new Types\Nullable($type->get(0));
		}
		if ($type instanceof Types\AbstractList)
			$type = new Types\Array_;

		return $type;
	}

	private function findParams(array $tags, Node $node): void
	{
		if (isset($tags['param']))
			for ($i = 0; $i < count($tags['param']); $i++)
			{
				$type = $tags['param'][$i]->getType();

				if ($type === null)
					$this->warnings[] = sprintf(
						'Docblock has no type for $%s" in %s()',
						$tags['param'][$i]->getVariableName(),
						$node->name
					);

				if (!isset($node->params[$i]))
				{
					break;
				}

				if (isset($node->params[$i]->type) && ($node->params[$i]->type instanceof Node\NullableType && $node->params[$i]->type->type->name == (string) $type || !$node->params[$i]->type instanceof Node\NullableType && (string) $type != $node->params[$i]->type->name))
					$this->warnings[] = sprintf(
						'Type mismatch for $%s" in %s()',
						$node->params[$i]->var->name,
						$node->name
					);

				if (!isset($node->params[$i]->type) || (!$node->params[$i]->type instanceof Node\NullableType && $node->params[$i]->type->name == ''))
				{
					if ($type === null)
						$this->warnings[] = sprintf(
							'Cannot infer type for $%s" in %s()',
							$node->params[$i]->var->name,
							$node->name
						);
					else
						$type = $this->getType($type);
					$node->params[$i]->type = new Node\Name((string) $type ?: '???');
				}
			}
	}
}