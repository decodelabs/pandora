## v0.2.13 (2023-11-10)
* Switched to Slingshot for function invokation

## v0.2.12 (2023-11-08)
* Fixed try*() method binding check

## v0.2.11 (2023-11-01)
* Return self for unbound PSR container types
* Allow null from instance factories
* Added tryGet() and tryGetWith()
* Fixed return type hints for getWith()

## v0.2.10 (2023-10-29)
* Added default resolution via Archetype

## v0.2.9 (2023-09-26)
* Converted phpstan doc comments to generic

## v0.2.8 (2022-11-26)
* Fixed binding target resolution infinite loop

## v0.2.7 (2022-11-26)
* Added key-value store to container
* Added referential type aliases to bound objects
* Migrated to use effigy in CI workflow

## v0.2.6 (2022-11-14)
* Check binding list for class-string $target resolution
* Fixed PHP8.1 testing

## v0.2.5 (2022-10-31)
* Added get() return type hints

## v0.2.4 (2022-09-30)
* Added ArrayAccess to Container
* Updated composer check script
* Updated CI environment

## v0.2.3 (2022-08-24)
* Fixed ECS issue in Binding

## v0.2.2 (2022-08-24)
* Fixed Binding factory instantiation

## v0.2.1 (2022-08-24)
* Added concrete types to all members

## v0.2.0 (2022-08-23)
* Removed PHP7 compatibility
* Updated PSR Container interface to v2
* Updated ECS to v11
* Updated PHPUnit to v9

## v0.1.1 (2022-03-10)
* Transitioned from Travis to GHA
* Updated PHPStan and ECS dependencies

## v0.1.0 (2021-05-10)
* Ported codebase from DF
