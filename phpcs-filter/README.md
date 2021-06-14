# Anomiex Phpcs Filter

**⚠️ This is a draft. If I decide to go ahead an publish this, it will probably be done under a different name.**

This is a prototype fancy filter for [PHP CodeSniffer]. It adds the following features:

* Uses `.phpcsignore` files for file exclusion.
* Uses per-directory configuration files (by default `.phpcs.dir.xml`).

## Installation

Require using `composer require anomiex/phpcs-filter`.

## Usage

Basic usage is to pass the filter to `phpcs` or `phpcbf` via the `--filter` command line option:
```
vendor/bin/phpcs --filter=vendor/anomiex/phpcs-filter/src/PhpcsFilter.php .
```

It may be more convenient to do so by adding the following to your `.phpcs.xml.dist`:
```xml
<arg name="filter" value="vendor/anomiex/phpcs-filter/src/PhpcsFilter.php" />
```

## Configuration

Configuration is done via phpcs's `--runtime-set` or `--config-set` command line options, or by a `<config>` key in `.phpcs.xml.dist`.

### Base directory: `anomiex-filter-basedir`

Any `.phpcsignore` or per-directory configuration file above the base directory is ignored. The idea is that the "base" directory should be the base of your checkout,
and any files that happen to be in parent directories should not affect the operation of the filter.

The base directory is determined by the first of these that are available:

* The `anomiex-filter-basedir` config setting.
* The `--basepath` command line option.
* The current working directory.

### Per-directory configuration file name: `anomiex-filter-perdir-file`

By default, the per-directory configuration file is named `.phpcs.dir.xml`. You can override this by setting `anomiex-filter-perdir-file`.

## Limitations

Note the following limitations on what can be specified in the per-directory configuration file.

### `<arg>`

This should be avoided. Some args, such as `-d`, take effect globally rather than being scoped to the directory.
Others, such as `--filter`, `-s`, and so on, may not have any effect as they're not processed on a per-directory basis.

### `<autoload>`

This should be avoided, as it takes effect globally rather than being scoped to the directory.

### `<config>`

This should be avoided, as it takes effect globally rather than being scoped to the directory. In addition, some configs are processed before the filter runs and so will not have any effect when specified in a per-directory file.

Sniffs that are configured via `<config>` should be updated to allow configuration via `<property>` instead or in addition (with any `<config>`-set value being used as a fallback). Note that a property can be set for every rule in a standard:
```xml
<rule ref="Standard">
  <!-- This will set property "foo" for every rule in the standard. -->
  <!-- (unless the rule was already added by an earlier <rule> directive in this file) -->
  <properties>
    <property name="foo" value="123" />
  </properties>
</rule>
```

### `<exclude>`

If a rule is enabled in a parent directory, the 3-component syntax does not work to turn it off.
This is because the per-directory configuration is loaded as a peer to the parent's configuration (as if the command line had been like `--standard=.phpcs.xml.dist,subdir/.phpcs.dir.xml`) rather than loading the parent's configuration as a sub-ruleset (as if the `subdir/.phpcs.dir.xml` had included something like `<rule ref="../.phpcs.xml.dist" />` at the start).

You can still exclude such a rule by setting its severity to zero. The 4-component syntax to exclude individual messages from a rule also works.

```xml
<rule ref="Standard.Category.Rule">
  <!-- This does not work if the rule was enabled in a parent directory! -->
  <exclude name="Standard.Category.Rule" />

  <!-- This works. -->
  <severity>0</severity>

  <!-- This works too. -->
  <exclude name="Standard.Category.Rule.Message" />
</rule>
```

### `<file>`

This is applied before the filter runs, and so has no effect on a per-directory basis.

### `<ini>`

This should be avoided, as it takes effect globally rather than being scoped to the directory.


[PHP CodeSniffer]: https://github.com/squizlabs/PHP_CodeSniffer
