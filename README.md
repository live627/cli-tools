# cli-tools
Tools to help aid/automate development

While we wait for this to be merged in, modify your `composer.json`:

```json
{
  "repositories": [
    {
      "url": "https://github.com/live627/cli-tools.git",
      "type": "vcs"
    }
  ],
  "minimum-stability": "dev",
  "require-dev": {
    "live627/cli-tools": "dev-master"
  }
}
```

Now you can run

```bash
composer install
 ```

Subdirectories may include a composer file with dependencies to install.

#### docgen
Generate docs on https://live627.github.io/smf-api-docs-test

- `make-md-docs.php`:  Get phpdocs from functions. I made this script because I couldn't get phpDocumentor to work right. IIRC, the output kept getting truncated.
- `hooks.php`: List all integration hook points

#### changelog-parser
It was built to parse https://github.com/live627/smf-custom-forms/blob/master/CHANGELOG.md and thus dates are optional.