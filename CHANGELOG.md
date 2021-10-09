## v0.3.0 (unreleased)

### 💥 Breaking changes

  * Add `getFailoverAdapter()`, `getPath()`, `getInnerSourceAdapter()` and `getInnerDestinationAdapter()` methods to `MessageInterface`
  * Rename `replicate` action to `replicate_file` in `DoctrineMessageRepository` (needs to remove or rename impacted rows in the database)

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
