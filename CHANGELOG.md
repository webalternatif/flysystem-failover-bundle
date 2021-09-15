## v0.2.1 (unreleased)

### 🐛 Bug fixes

  * Fix `DoctrineMessageRepository` issues with SQLite driver

## v0.2.0 (September 14, 2021)

### 💥 Breaking changes

  * Rename tag `webf_flysystem_failovermessage_handler` into `webf_flysystem_failover.message_handler`

### ✨ New features

  * Register commands and services even if there is no adapter in configuration

### ⚡ Performance improvements

  * Consume `FailoverAdaptersLocator`'s iterator only when it's necessary

## v0.1.0 (September 10, 2021)

First version.
