<?php

declare(strict_types=1);

function byte_format(int $bytes): string
{
	for ($i = 0; $bytes > 1024 && $i < 3; $i++)
		$bytes /= 1024;

	return number_format($bytes, 2) . ' ' . ['B', 'KB', 'MB'][$i];
}

function readFilesystem(string $dirname, string $ignoreFiles): iterable
{
	return iterator_to_array(
		new RecursiveIteratorIterator(
			new RecursiveCallbackFilterIterator(
				new RecursiveDirectoryIterator(
					$dirname,
					FilesystemIterator::CURRENT_AS_PATHNAME | FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS
				),
				fn($currentFile, $key, $iterator) => $iterator->hasChildren() || (str_ends_with($currentFile, '.php') && preg_match("~$ignoreFiles~", strtr($currentFile, [SMFDIR => '.'])) === 0)
			)
		),
		false
	);
}

function outputProgress(string $progress, int $i, int $maxIdx): void
{
	static $column = 0;

	printf(
		match ($progress)
		{
			'x' => "\033[1;36m%s\033[0m",
			'F' => "\033[1;37;41m%s\033[0m",
			'E' => "\033[1;31m%s\033[0m",
			'.' => "\033[1;32m%s\033[0m"
		},
		$progress
	);
	$width = strlen((string) $maxIdx);
	$format = $width;
	$maxColumn = 80 - strlen('  /  (XXX%)') - (2 * $width);
	if (++$column == $maxColumn || $i == $maxIdx)
	{
		if ($i == $maxIdx)
			$format = $maxColumn - $column + $width;

		printf(
			"%{$format}d / %{$width}d (%3s%%)",
			$i + 1,
			$maxIdx + 1,
			floor(($i / $maxIdx) * 100)
		);

		if ($column == $maxColumn)
		{
			$column = 0;
			echo "\n";
		}
	}
}

function outputResults(array $errors)
{
	foreach (['e', 'f', 'w'] as $type)
		if (!empty($errors[$type]))
		{
			fwrite(STDERR, "\n");
			foreach ($errors[$type] as $file => $errs)
				foreach ($errs as $error)
					outputError($error, $file); 
		}
	if (!empty($errors['e']) && !empty($errors['f']))
		exit(1);
}

function outputError(string $error, string $file)
{
	fwrite(STDERR, "\e[31m✖\e[39m $error (\e[4;33m$file\e[24;39m)\n");
}
