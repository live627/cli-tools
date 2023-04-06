<?php

// Stuff we will ignore.
$ignoreFiles = [
	// Index files.
	'\./attachments/index\.php',
	'\./avatars/index\.php',
	'\./avatars/[A-Za-z0-9]+/index\.php',
	'\./cache/index\.php',
	'\./custom_avatar/index\.php',
	'\./Packages/index\.php',
	'\./Packages/backups/index\.php',
	'\./Smileys/[A-Za-z0-9]+/index\.php',
	'\./Smileys/index\.php',
	'\./Sources/index\.php',
	'\./Sources/tasks/index\.php',
	'\./Themes/default/css/index\.php',
	'\./Themes/default/fonts/index\.php',
	'\./Themes/default/fonts/sound/index\.php',
	'\./Themes/default/images/[A-Za-z0-9]+/index\.php',
	'\./Themes/default/images/index\.php',
	'\./Themes/default/index\.php',
	'\./Themes/default/languages/index\.php',
	'\./Themes/default/scripts/index\.php',
	'\./Themes/index\.php',
	// Language Files are ignored as they don't use the License format.
	'./Themes/default/languages/[A-Za-z0-9]+\.english\.php',
	// Cache and miscellaneous.
	'\./cache/',
	'\./other/',
	'\./tests/',
	'\./vendor/',
	// Minify Stuff.
	'\./Sources/minify/',
	// random_compat().
	'\./Sources/random_compat/',
	// ReCaptcha Stuff.
	'\./Sources/ReCaptcha/',
	'\./Sources/tasks/',
	'\./Sources/Unicode/',
	// We will ignore Settings.php if this is a live dev site.
	'\./Settings\.php',
	'\./Settings_bak\.php',
	'\./db_last_error\.php',
];

$eta = -hrtime(true);
$mem = memory_get_usage();
$max = -1;
$idx = 0;
$files = readFilesystem('../smf2.1');
array_multisort(
	array_map(
		fn($filename) => substr_count($filename, '-') . $filename,
		array_keys($files)
	),
	SORT_NATURAL | SORT_FLAG_CASE,
	$files
);
$max = count($files) - 1;
foreach ($files as $currentFile)
 	{
		if (($calls = get_defined_functions_in_file($currentFile)) !== false)
		{
			$basename = $orig_basename = strtolower(strtr(basename($currentFile, '.php'), '.', '-'));
			$section = '';
			$mode = 'w';
			if (str_starts_with($basename, 'manage'))
			{
				$basename = 'admin';
				$mode = 'a';
			}
			elseif (str_starts_with($basename, 'profile-'))
			{
				$basename = 'profile';
				$mode = 'a';
			}
			elseif (str_starts_with($basename, 'subs-'))
			{
				$basename = substr($basename, 5);
				if (isset($files[strtr($currentFile, ['Subs-' => ''])]))
					$mode = 'a';
			}
			$count = array_reduce(
				$calls,
				fn($accumulator, $hooks) => $accumulator + count($hooks),
				0
			);
			$name = '../smf-api-docs-test/hookdocs/' . $basename . '.md';

			if ($mode == 'a')
				file_put_contents($name, preg_replace_callback('/count: (\d+)/', fn($m) => 'count: ' . $m[1] + $count, file_get_contents($name)));

			if (($file = fopen($name, $mode)) !== false)
			{
				fprintf(
					$file,
					$mode == 'a'
						? "\n## %s\n"
						: "---\nlayout: default\ngroup: hooks\ntitle: %s\ncount: %d\n---\n{:toc}\n## %s\n",
					$mode == 'a' ? basename($currentFile) : ucfirst($basename),
					$count,
					basename($currentFile)
				);

				foreach ($calls as $call => $hooks)
				{
					foreach ($hooks as $hook => $args)
					{
						fprintf(
							$file,
							"### %s\n\n```php\ncall_integration_hook('%s'%s)\n```\n\n",
							$hook,
							$hook,
							$args == '' ? '' : ', array(' . $args . ')'
						);
						if ($args != '')
						{
							fwrite($file, "Type|Parameter|Description\n---|---|---\n");
							// Me be a pirate. Arr!
							foreach (explode(', ' , $args) as $arrg)
								fprintf(
									$file,
									"`array`|`%s`|desc\n",
									str_contains($arrg, ':')
										? '???'
										: str_replace('$$', '$', preg_replace('/([&\$])[-\w]+\[?\'?([^\']+)\'?\]/', '$1$2', $arrg))
								);
						}

						if (isset($def))
						{
							fprintf(
								$file,
								"\nDefined in\n: [`%s`](../docs/%s.html)\n",
								strtr($def, ['./smf2.1' => '']),
								strtolower(basename($def, '.php'))
							);
							unset($def);
						}
						fprintf(
							$file,
							"\nCalled from\n: [`%s()` in `%s`](../docs/%s.html#%s)\n\nNotes\n: Since 2.1\n\n",
							$call,
							strtr($currentFile, ['./smf2.1' => '']),
							$orig_basename,
							strtolower($call)
						);
					}
				}
				fclose($file);
			}
			outputProgress('.', $idx++, $max);
		}
		else
			outputProgress('x', $idx++, $max);
	}
$eta += hrtime(true);
printf(
	"\n\nTime elapsed: %.2f seconds\nMemory usage: %s (%s peak)\n",
	$eta / 1e9,
	byte_format(memory_get_usage() - $mem),
	byte_format(memory_get_peak_usage())
);

function get_defined_functions_in_file(string $file): bool|array
{
	$source = file_get_contents($file);
	if (!str_contains($source, 'call_integration_hook'))
		return false;
	else
	{
		preg_match_all('/\bnamespace\s++((?P>label)(?:\\\(?P>label))*+)\s*+;|\bclass\s++((?P>label))[\w\s]*+{[^@]|\bfunction\s++((?P>label))\s*+\([^\n]*+\n[:\|\w\s]*+{|call_integration_hook\(\'([\w\[[\]\. \'\$]+)\'(?:, array\(([^\)]*))?\)(?(DEFINE)(?<label>[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]++))/i', $source, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

		$functions = array();
		$namespace = '';
		$class = '';
		$function = '';

		foreach ($matches as $match)
		{
			if (!empty($match[1][0]))
				$namespace = $match[1][0];
			elseif (!empty($match[2][0]))
				$class = $match[2][0];
			elseif (!empty($match[3][0]))
				$function = $namespace . '\\' . $class . '::' . $match[3][0];
			elseif (!empty($match[4][0]))
				$functions[$function][$match[4][0]] = $match[5][0] ?? '';
		}

		return $functions;
	}
}

function byte_format(int $bytes): string
{
	for ($i = 0; $bytes > 1024 && $i < 3; $i++)
		$bytes /= 1024;

	return number_format($bytes, 2) . ' ' . ['B', 'KB', 'MB'][$i];
}

function readFilesystem(string $dirname): array
{
	global $ignoreFiles;

	return iterator_to_array(
		new RecursiveIteratorIterator(
			new RecursiveCallbackFilterIterator(
				new RecursiveDirectoryIterator(
					$dirname,
					FilesystemIterator::CURRENT_AS_PATHNAME | FilesystemIterator::UNIX_PATHS
				),
				function ($currentFile, $key, $iterator) use ($ignoreFiles)
				{
					// Allow recursion
					if ($iterator->hasChildren())
						return true;
					foreach ($ignoreFiles as $if)
						if (preg_match('~' . $if . '~i', strtr($currentFile, ['./smf2.1' => ''])))
							return false;

					return str_ends_with($currentFile,'.php');
				}
			)
		)
	);
}

function outputProgress(string $progress, int $i, int $max): void
{
	static $column = 0;

	switch ($progress)
	{
		case 'x':
			echo "\033[1;36m$progress\033[0m";
			break;
		case 'F':
			echo "\033[1;37;41m$progress\033[0m";
			break;
		case 'E':
			echo "\033[1;31m$progress\033[0m";
			break;
		case '.':
			echo "\033[1;32m$progress\033[0m";
			break;
	}
	$width = strlen((string) $max);
	$format = ' %' . $width;
	$maxColumn = 80 - strlen('  /  (XXX%)') - (2 * $width);
	if (++$column == $maxColumn || $i == $max)
	{
		if ($i == $max)
			$format = ' %' . $maxColumn - $column+$width;

		printf(
			$format . 'd / %' . $width . 'd (%3s%%)',
			$i+1,
			$max+1,
			floor(($i / $max) * 100)
		);

		if ($column == $maxColumn)
		{
			$column = 0;
			echo "\n";
		}
	}
}
