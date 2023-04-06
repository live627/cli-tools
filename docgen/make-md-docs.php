<?php

declare(strict_types=1);

require_once './vendor/autoload.php';
require_once './DocVisitor.php';
require_once './MyFindingVisitor.php';
require_once './includes.php';

use PhpParser\{Node, NodeTraverser, PrettyPrinter};

const SMF = 'CLI';
const SMFDIR = '../SMF2.1';
require_once __DIR__ . '/' . SMFDIR . '/Sources/Subs.php';
$ignoreFiles = strtr(
	build_regex([
		'./[A-Za-z0-9]+/index.php',
		'./Themes/default/languages/[A-Za-z0-9]+.english.php',
		'./.git/',
		'./cache/',
		'./other/',
		'./tests/',
		'./vendor/',
		'./Sources/minify/',
		'./Sources/random_compat/',
		'./Sources/ReCaptcha/',
		'./Settings.php',
		'./Settings_bak.php',
		'./db_last_error.php',
		'./install.php',
		'./upgrade.php',
		'./upgrade-helper.php',
	]),
	['\\' => '', '.' => '\.']
);

$eta = -hrtime(true);
$mem = memory_get_usage();

$parser = (new PhpParser\ParserFactory)->create(
	PhpParser\ParserFactory::PREFER_PHP7,
	new PhpParser\Lexer\Emulative([
		'usedAttributes' => [
			'startLine',
			'endLine',
			'startFilePos',
			'endFilePos',
			'comments',
		],
	])
);
$traverser = new NodeTraverser;
$prettyPrinter = new PrettyPrinter\Standard;

$traverser->addVisitor(
	new PhpParser\NodeVisitor\MyFindingVisitor(
		fn(Node $stmt) => $stmt instanceof Node\Stmt\Function_
	)
);
$traverser->addVisitor(
	new \live627\DocGen\DocVisitor(
		fn(Node $stmt) => $stmt instanceof Node\Stmt\Function_
	)
);

$errors = ['e' => [], 'f' => [], 'w' => []];
$idx = 0;
$files = readFilesystem(SMFDIR, $ignoreFiles);
$max = count($files) - 1;
foreach ($files as $currentFile)
	if (($fp = fopen($currentFile, 'r')) !== false)
	{
		$contents = stream_get_contents($fp);
		$nodes = $traverser->traverse($parser->parse($contents));
		fclose($fp);
		$count = count($nodes);
		$name = sprintf(
			'../smf-api-docs-test/docs/%s.md',
			strtolower(strtr(substr(basename($currentFile), 0, -4), '.', '-'))
		);
		if ($count > 0 && ($file = fopen($name, 'w')) !== false)
		{
			$normalisedFile = strtr($currentFile, [SMFDIR => '.']);
			fprintf(
				$file,
				"---\nlayout: default\ngroup: func\nnavtitle: %s\ntitle: %s\ncount: %d\n---\n* auto-gen TOC:\n{:toc}\n",
				basename($currentFile),
				$normalisedFile,
				$count
			);
			foreach ($nodes as $node)
			{
				fprintf(
					$file,
					"### %s\n",
					$node->name
				);
				fprintf(
					$file,
					"\n```php\n%s\n```\n",
					str_replace(' :', ':', substr($prettyPrinter->prettyPrint([$node]), 0, -4))
				);

				if ($node->hasAttribute('docblock'))
				{
					$docblock = $node->getAttribute('docblock');
					fprintf(
						$file,
						"%s\n\n%s\n\n",
						$docblock->getSummary(),
						$docblock->getDescription()
					);
					if ($node->hasAttribute('tags'))
						write_params($file, $node->getAttribute('tags'));
				}
				if ($node->hasAttribute('hooks'))
					fprintf(
						$file,
						"Integration hooks\n: %s\n\n",
						implode("\n: ", $node->getAttribute('hooks'))
					);
				if ($node->hasAttribute('warnings'))
					$errors['w'][$normalisedFile] = !isset($errors['w'][$normalisedFile])
						? []
						: array_merge($errors['w'][$normalisedFile], $node->getAttribute('warnings'));
			}

			fclose($file);
			outputProgress('.', $idx++, $max);
		}
		else
			outputProgress('x', $idx++, $max);
	}
outputResults($errors);

$eta += hrtime(true);
printf(
	"\n\nTime elapsed: %.2f seconds\nMemory usage: %s (%s peak)",
	$eta / 1e9,
	byte_format(memory_get_usage() - $mem),
	byte_format(memory_get_peak_usage())
);

function write_params($file, array $tags)
{
	if (isset($tags['param']))
	{
		fwrite($file, "Type|Parameter|Description\n---|---|---\n");
		foreach ($tags['param'] as $tag)
		{
			$description = '';
			if ($tag->getDescription())
				$description = $tag->getDescription()->render();

			$variableName = '';
			if ($tag->getVariableName())
			{
				if ($tag->isReference())
					$variableName .= '\&';
				if ($tag->isVariadic())
					$variableName .= '...';

				$variableName .= sprintf('$%s', $tag->getVariableName());
			}

			fprintf(
				$file,
				"`%s`|`%s`|%s\n",
				str_replace('|', '`&#124;`', (string) $tag->getType() ?: 'null'),
				$variableName,
				str_replace("\n", "\n||", $description)
			);
		}
		fwrite($file, "\n");
	}
}
