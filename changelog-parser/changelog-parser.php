<?php

namespace live627\ChangelogParser;

include 'vendor/autoload.php';

use JsonSerializable;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\{CommonMarkCoreExtension, Node\Block\Heading, Node\Block\ListItem, Node\Inline\Link};
use League\CommonMark\Node\{Block\Paragraph, Inline\Text};
use League\CommonMark\Parser;
use Stringable;

class MarkdownParser
{
	public function parse(string $content): ParseResult
	{
		$config = [
			'html_input' => 'strip',
			'allow_unsafe_links' => false
		];
		$environment = new Environment($config);
		$environment->addExtension(new CommonMarkCoreExtension());
		$parser = new Parser\MarkdownParser($environment);
		$document = $parser->parse($content);

		$walker = $document->walker();
		$releases = [];
		$releaseIndex = -1;
		$type = null;
		$content = '';
		while ($event = $walker->next())
		{
			$node = $event->getNode();
			if ($event->isEntering() && $node->parent() instanceof Heading)
			{
				$firstChild = $node->firstChild();

				if ($node instanceof Link && $firstChild instanceof Text)
					$content = $firstChild->getLiteral();
				if ($node instanceof Text)
				{
					$content .= $node->getLiteral();
					preg_match('/^[Vv]?(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)(?:\.(0|[1-9][0-9]*))?(?:-((?:0|[1-9][0-9]*|[0-9]*[a-zA-Z-][0-9a-zA-Z-]*)(?:\.(?:0|[1-9][0-9]*|[0-9]*[a-zA-Z-][0-9a-zA-Z-]*))*))?(?:\+([0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?(?:\s\(([0-9]{4})-(0[1-9]|1[012])-(0[1-9]|[12][0-9]|3[01])\))?:?$/', $content, $matches, PREG_UNMATCHED_AS_NULL);

					if ($matches != [])
					{
						unset($matches[0]);
						$releases[++$releaseIndex] = Release::createFromParts(...$matches);
					}
					if ($releases != [])
						$type = $content;

					$content = '';
				}
			}

			if ($releases != [] && $type)
			{
				$parent = $node->parent();
				$grandParent = $parent?->parent();
				if (isset($releases[$releaseIndex]) && $node instanceof Text && $parent instanceof Paragraph && $grandParent instanceof ListItem)
					$releases[$releaseIndex]->addChange(ucfirst($type), $node->getLiteral());
			}
		}

		return new ParseResult($releases);
	}
}

class ParseResult implements JsonSerializable
{
	/** @var array */
	private array $releases;

	/**
	 * @param array $releases
	 */
	public function __construct(array $releases = [])
	{
		foreach ($releases as $release)
			$this->addRelease($release);
	}

	/**
	 * @return Release[]
	 */
	public function getReleases(): array
	{
		return $this->releases;
	}

	public function addRelease(Release $release)
	{
		$this->releases[] = $release;
	}

	public function jsonSerialize(): mixed
	{
		return $this->releases;
	}
}

class Release implements JsonSerializable
{
	private array $changes;

	public function __construct(private Version $version, private int $date, array $changes = [])
	{
		$this->changes = $changes;
	}

	public function getVersion(): Version
	{
		return $this->version;
	}

	public function getDate(): int
	{
		return $this->date;
	}

	public function getChanges(): array
	{
		return $this->changes;
	}

	public static function createFromParts(
		string $major,
		string $minor,
		?string $patch,
		?string $pre,
		?string $build,
		?string $year,
		?string $month,
		?string $day
	): self
	{
		return new self(
			new Version($major, $minor, $patch, $pre, $build),
			mktime(0, 0, 0, $month, $day, $year)
		);
	}

	public function addChange($type, $change)
	{
		if (!isset($this->changes[$type]))
			$this->changes[$type] = [];

		$change = trim($change, '( )');
		if ($change != '')
			$this->changes[$type][] = $change;
	}

	public function jsonSerialize(): mixed
	{
		return [
			'version' => $this->version,
			'date' => $this->date,
			'changes' => $this->changes,
		];
	}
}

class Version implements JsonSerializable, Stringable
{
	private int $major;
	private int $minor;
	private int $patch;

	public function __construct(
		string $major,
		string $minor,
		?string $patch,
		private ?string $preRelease,
		private ?string $buildNumber
	)
	{
		$this->major = (int) $major;
		$this->minor = (int) $minor;
		$this->patch = (int) $patch;
	}

	public static function fromString(string $content): Version
	{
		preg_match('/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)(?:-((?:0|[1-9][0-9]*|[0-9]*[a-zA-Z-][0-9a-zA-Z-]*)(?:\.(?:0|[1-9][0-9]*|[0-9]*[a-zA-Z-][0-9a-zA-Z-]*))*))?(?:\+([0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?$/', $content, $matches, PREG_UNMATCHED_AS_NULL);
		unset($matches[0]);

		return new self(...$matches);
	}

	public function getMajor(): int
	{
		return $this->major;
	}

	public function getMinor(): int
	{
		return $this->minor;
	}

	public function getPatch(): int
	{
		return $this->patch;
	}

	public function getPreRelease(): ?string
	{
		return $this->preRelease;
	}

	public function getBuildNumber(): ?string
	{
		return $this->buildNumber;
	}

	public function __toString()
	{
		return sprintf(
			'%d.%d.%d',
			$this->getMajor(),
			$this->getMinor(),
			$this->getPatch()
		);
	}

	public function jsonSerialize(): mixed
	{
		return [
			'string' => (string) $this,
			'major' => $this->major,
			'minor' => $this->minor,
			'patch' => $this->patch,
			'preRelease' => $this->preRelease,
			'buildNumber' => $this->buildNumber,
		];
	}
}

$parser = new MarkdownParser();

$result = $parser->parse(file_get_contents('../CHANGELOG.md'));

//~ foreach ($result->getReleases() as $release)
//~ {
	//~ echo $release->getVersion() . PHP_EOL;
	//~ echo date('Y-m-d', $release->getDate()) . PHP_EOL;
	//~ foreach ($release->getChanges() as $type => $changes)
	//~ {
		//~ echo $type . PHP_EOL;
		//~ foreach ($changes as $change)
			//~ echo "- $change" . PHP_EOL;
	//~ }
//~ }

file_put_contents('../versions.json', json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES| JSON_UNESCAPED_UNICODE);
