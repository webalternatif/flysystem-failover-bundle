## v0.3.2 (unreleased)

### 🐛 Bug fixes

* Add composer conflict with doctrine/dbal < 2.13.1

## v0.3.1 (November 17, 2021)

### 🐛 Bug fixes

* Fix Symfony 4 compatibility

## v0.3.0 (October 9, 2021)

### 💥 Breaking changes

* Add `findBy()` method to `MessageRepositoryInterface`
* Add `getFailoverAdapter()`, `getPath()`, `getInnerSourceAdapter()` and `getInnerDestinationAdapter()` methods to `MessageInterface`
* Rename `replicate` action to `replicate_file` in `DoctrineMessageRepository` (needs to remove or rename impacted rows in the database)

### ✨ New features

* Add command to list remaining messages to process
* `FailoverAdapter` now implements [`CompositeFilesystemAdapter`](https://github.com/webalternatif/flysystem-composite/blob/v0.1.0/src/CompositeFilesystemAdapter.php)

## v0.2.1 (September 16, 2021)

### 🐛 Bug fixes

* Fix `DoctrineMessageRepository` issues with SQLite driver

### ⚡ Performance improvements

* Retry pop 10 times before returning null in `DoctrineMessageRepository`

## v0.2.0 (September 14, 2021)

### 💥 Breaking changes

* Rename tag `webf_flysystem_failovermessage_handler` into `webf_flysystem_failover.message_handler`

### ✨ New features

* Register commands and services even if there is no adapter in configuration

### ⚡ Performance improvements

* Consume `FailoverAdaptersLocator`'s iterator only when it's necessary

## v0.1.0 (September 10, 2021)

First version.
