## v0.4.3 (unreleased)

### 🐛 Bug fixes

* Use Guzzle's CachingStream to replicate a file ([#5](https://github.com/webalternatif/flysystem-failover-bundle/pull/5))

## v0.4.2 (January 16, 2023)

### ✨ New features

* Add support of PHP 8.2 ([#1](https://github.com/webalternatif/flysystem-failover-bundle/pull/1))

## v0.4.1 (June 14, 2022)

### ✨ New features

* Allow Symfony components `^6.0` ([0d6e0d1](https://github.com/webalternatif/flysystem-failover-bundle/commit/0d6e0d141dfed4795f004e6bd3c4dac40b526444))

## v0.4.0 (April 2, 2022)

### 💥 Breaking changes

* Bump league/flysystem to version `^3.0` ([4882390](https://github.com/webalternatif/flysystem-failover-bundle/commit/48823907115faddf121f1bea15fcd09315c6956d))

## v0.3.2 (December 30, 2021)

### ✨ New features

* Add support of PHP 8.1 ([5926da6](https://github.com/webalternatif/flysystem-failover-bundle/commit/5926da67e5392b33e40ff88e3619b3a0e28223d8))

### 🐛 Bug fixes

* Add composer conflict with doctrine/dbal < 2.13.1 ([e2e63df](https://github.com/webalternatif/flysystem-failover-bundle/commit/e2e63dfdf674215af47160452bfa9345287c5696))

## v0.3.1 (November 17, 2021)

### 🐛 Bug fixes

* Fix Symfony 4 compatibility ([06c2a32](https://github.com/webalternatif/flysystem-failover-bundle/commit/06c2a32b38ca45fd128fe6aba0bef07bef6248e5))

## v0.3.0 (October 9, 2021)

### 💥 Breaking changes

* Add `findBy()` method to `MessageRepositoryInterface` ([a08be35](https://github.com/webalternatif/flysystem-failover-bundle/commit/a08be35ab8b6971fa3acdfea50838071cb2200f9))
* Add `getFailoverAdapter()`, `getPath()`, `getInnerSourceAdapter()` and `getInnerDestinationAdapter()` methods to `MessageInterface` ([d42de54](https://github.com/webalternatif/flysystem-failover-bundle/commit/d42de547afae0b0a9369a30713140002ffaaf8ff))
* Rename `replicate` action to `replicate_file` in `DoctrineMessageRepository` (needs to remove or rename impacted rows in the database) ([d42de54](https://github.com/webalternatif/flysystem-failover-bundle/commit/d42de547afae0b0a9369a30713140002ffaaf8ff))

### ✨ New features

* Add command to list remaining messages to process ([a08be35](https://github.com/webalternatif/flysystem-failover-bundle/commit/a08be35ab8b6971fa3acdfea50838071cb2200f9))
* `FailoverAdapter` now implements [`CompositeFilesystemAdapter`](https://github.com/webalternatif/flysystem-composite/blob/v0.1.0/src/CompositeFilesystemAdapter.php) ([b5faab9](https://github.com/webalternatif/flysystem-failover-bundle/commit/b5faab9b405241a75d5c9b11741b589088b372d2))

## v0.2.1 (September 16, 2021)

### 🐛 Bug fixes

* Fix `DoctrineMessageRepository` issues with SQLite driver ([99198c2](https://github.com/webalternatif/flysystem-failover-bundle/commit/99198c2edb93c612cbe79b99caefe522313a41a9))

### ⚡ Performance improvements

* Retry pop 10 times before returning null in `DoctrineMessageRepository` ([66894a0](https://github.com/webalternatif/flysystem-failover-bundle/commit/66894a08145b1f72a5fd6207b765623570d2c0be))

## v0.2.0 (September 14, 2021)

### 💥 Breaking changes

* Rename tag `webf_flysystem_failovermessage_handler` into `webf_flysystem_failover.message_handler` ([69cda6e](https://github.com/webalternatif/flysystem-failover-bundle/commit/69cda6e37a87f12ecc143faa84e4a027bdbc95ae))

### ✨ New features

* Register commands and services even if there is no adapter in configuration ([3d0a7a0](https://github.com/webalternatif/flysystem-failover-bundle/commit/3d0a7a0ddbb1baf41839716ab2b3be1783a4e7e4))

### ⚡ Performance improvements

* Consume `FailoverAdaptersLocator`'s iterator only when it's necessary ([aca722a](https://github.com/webalternatif/flysystem-failover-bundle/commit/aca722a35f2626ede7ab8f275c4b966f48e23cec))

## v0.1.0 (September 10, 2021)

First version. ([351e632](https://github.com/webalternatif/flysystem-failover-bundle/commit/351e6328c5bac8f80c34aaaae4ae01e820898056))
