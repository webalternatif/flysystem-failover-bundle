# Flysystem failover bundle

[![Source code](https://img.shields.io/badge/source-GitHub-blue)](https://github.com/webalternatif/flysystem-failover-bundle)
[![Software license](https://img.shields.io/github/license/webalternatif/flysystem-failover-bundle)](https://github.com/webalternatif/flysystem-failover-bundle/blob/master/LICENSE)
[![GitHub issues](https://img.shields.io/github/issues/webalternatif/flysystem-failover-bundle)](https://github.com/webalternatif/flysystem-failover-bundle/issues)
[![Test status](https://img.shields.io/github/workflow/status/webalternatif/flysystem-failover-bundle/test?label=tests)](https://github.com/webalternatif/flysystem-failover-bundle/actions/workflows/test.yml)
[![Psalm coverage](https://shepherd.dev/github/webalternatif/flysystem-failover-bundle/coverage.svg)](https://psalm.dev)
[![Psalm level](https://shepherd.dev/github/webalternatif/flysystem-failover-bundle/level.svg)](https://psalm.dev)
[![Infection MSI](https://badge.stryker-mutator.io/github.com/webalternatif/flysystem-failover-bundle/master)](https://infection.github.io)

This bundle allows creating failover [Flysystem][1] adapters and provides
tooling to keep underlying storages synchronized.

## How it works

This bundle allows you to create failover adapters for Flysystem. A failover
adapter is an adapter that is built upon multiple (already existing) adapters.

When you use a failover adapter, it will forward method calls to inner adapters.

  * For reading, it will use the first that works.
  * For writing, it will use the first that works and will push messages into a
    repository to keep underlying storages of all adapters synchronized.

Messages in the repository can then be asynchronously processed by a console
command call (see [Processing messages](#processing-messages) section).

## Installation

Make sure Composer is installed globally, as explained in the
[installation chapter][2] of the Composer documentation.

### Applications that use Symfony Flex

Open a command console, enter your project directory and execute:

```console
$ composer require webalternatif/flysystem-failover-bundle
```

### Applications that don't use Symfony Flex

#### Step 1: Download the Bundle

Open a command console, enter your project directory and execute the following
command to download the latest stable version of this bundle:

```console
$ composer require webalternatif/flysystem-failover-bundle
```

#### Step 2: Enable the Bundle

Then, enable the bundle by adding it to the list of registered bundles in the
`config/bundles.php` file of your project:

```php
// config/bundles.php

return [
    // ...
    Webf\FlysystemFailoverBundle\WebfFlysystemFailoverBundle::class => ['all' => true],
];
```

## Usage

### Configuration

Failover adapters are configured under the `webf_flysystem_failover.adapters`
Symfony config path, and are then available as services with id
`webf_flysystem_failover.adapter.{name}`.

Each failover adapter must have at least 2 inner adapters. An inner adapter
could be a string to reference a service id, or an array with the following
attributes:

  * `service_id`: *(required)* identifier of the inner adapter service,
  * `time_shift`: a time shift for when the synchronization command compares
    modification dates between inner adapters.

Message repository is customizable under the
`webf_flysystem_failover.message_repository_dsn` Symfony config path. For now,
only `doctrine://<connection_name>` and `service://<service_id>` are available.

```yaml
webf_flysystem_failover:
    adapters:
        adapter1: # service: webf_flysystem_failover.adapter.adapter1
            adapters:
                - service_id_of_adapter_1
                - service_id: service_id_of_adapter_2
                  time_shift: 7200 # underlying storage of this adapter use a +02:00 timezone

        adapter2: # service: webf_flysystem_failover.adapter.adapter2
            adapters:
                - service_id_of_adapter_3
                - service_id_of_adapter_4
                - service_id_of_adapter_5

    message_repository_dsn: doctrine://my_connection
```

Run `bin/console config:dump-reference webf_flysystem_failover` for more info.

### Processing messages

To process messages created by failover adapters, the following command is
available:

```bash
$ bin/console webf:flysystem-failover:process-messages
```

It will process and remove the oldest message present in the repository.

### Synchronize existing storages

If you start to use this bundle on an existing project, you may want to manually
synchronize an existing non-empty storage with other new empty ones.

It is possible with the following command:

```bash
$ bin/console webf:flysystem-failover:sync
```

Use `--help` for more info.

## Tests

To run all tests, execute the command:

```bash
$ composer test
```

This will run [Psalm][3], [PHPUnit][4], [Infection][5] and a [PHP-CS-Fixer][6]
check, but you can run them individually like this:

```bash
$ composer psalm
$ composer phpunit
$ composer infection
$ composer cs-check
```

[1]: https://github.com/thephpleague/flysystem
[2]: https://getcomposer.org/doc/00-intro.md
[3]: https://psalm.dev
[4]: https://phpunit.de
[5]: https://infection.github.io
[6]: https://cs.symfony.com/
