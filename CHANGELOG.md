## v0.3.0 (2025-02-13)
* Replaced accessors with property hooks
* Upgraded PHPStan to v2
* Tidied boolean logic
* Fixed Exceptional syntax
* Added PHP8.4 to CI workflow
* Made PHP8.4 minimum version

## v0.2.16 (2025-02-07)
* Fixed implicit nullable arguments
* Updated dependency versions

## v0.2.15 (2024-04-26)
* Updated Archetype dependency
* Updated dependency list

## v0.2.14 (2023-12-13)
* Added interface Archetype dereferencing
* Moved to PHP8.1 minimum

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
