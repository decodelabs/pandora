# Changelog

All notable changes to this project will be documented in this file.<br>
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

### Unreleased
- Added parameter list to Container::setType()
- Upgraded Kingdom to v0.2

---

### [v0.4.0](https://github.com/decodelabs/pandora/commits/v0.4.0) - 21st August 2025

- Implemented Kingdom ContainerAdapter interface
- Removed Provider interface
- Removed aliases
- Removed key-value store
- Removed events
- Removed object accessors
- Removed shared option, always shared
- Removed ArrayAccess interface
- Removed fluid interface
- Removed Groups
- Simplified instance resolution
- Added Kingdom Service support

[Full list of changes](https://github.com/decodelabs/pandora/compare/v0.3.4...v0.4.0)

---

### [v0.3.4](https://github.com/decodelabs/pandora/commits/v0.3.4) - 16th July 2025

- Applied ECS formatting to all code

[Full list of changes](https://github.com/decodelabs/pandora/compare/v0.3.3...v0.3.4)

---

### [v0.3.3](https://github.com/decodelabs/pandora/commits/v0.3.3) - 6th June 2025

- Upgraded Exceptional to v0.6

[Full list of changes](https://github.com/decodelabs/pandora/compare/v0.3.2...v0.3.3)

---

### [v0.3.2](https://github.com/decodelabs/pandora/commits/v0.3.2) - 9th April 2025

- Upgraded Slingshot dependency

[Full list of changes](https://github.com/decodelabs/pandora/compare/v0.3.1...v0.3.2)

---

### [v0.3.1](https://github.com/decodelabs/pandora/commits/v0.3.1) - 24th March 2025

- Fixed PHPStan issues

[Full list of changes](https://github.com/decodelabs/pandora/compare/v0.3.0...v0.3.1)

---

### [v0.3.0](https://github.com/decodelabs/pandora/commits/v0.3.0) - 13th February 2025

- Replaced accessors with property hooks
- Upgraded PHPStan to v2
- Tidied boolean logic
- Fixed Exceptional syntax
- Added PHP8.4 to CI workflow
- Made PHP8.4 minimum version

[Full list of changes](https://github.com/decodelabs/pandora/compare/v0.2.16...v0.3.0)

---

### [v0.2.16](https://github.com/decodelabs/pandora/commits/v0.2.16) - 7th February 2025

- Fixed implicit nullable arguments
- Updated dependency versions

[Full list of changes](https://github.com/decodelabs/pandora/compare/v0.2.15...v0.2.16)

---

### [v0.2.15](https://github.com/decodelabs/pandora/commits/v0.2.15) - 26th April 2024

- Updated Archetype dependency
- Updated dependency list

[Full list of changes](https://github.com/decodelabs/pandora/compare/v0.2.14...v0.2.15)

---

### [v0.2.14](https://github.com/decodelabs/pandora/commits/v0.2.14) - 13th December 2023

- Added interface Archetype dereferencing
- Moved to PHP8.1 minimum

[Full list of changes](https://github.com/decodelabs/pandora/compare/v0.2.13...v0.2.14)

---

### [v0.2.13](https://github.com/decodelabs/pandora/commits/v0.2.13) - 10th November 2023

- Switched to Slingshot for function invokation

[Full list of changes](https://github.com/decodelabs/pandora/compare/v0.2.12...v0.2.13)

---

### [v0.2.12](https://github.com/decodelabs/pandora/commits/v0.2.12) - 8th November 2023

- Fixed try*() method binding check

[Full list of changes](https://github.com/decodelabs/pandora/compare/v0.2.11...v0.2.12)

---

### [v0.2.11](https://github.com/decodelabs/pandora/commits/v0.2.11) - 1st November 2023

- Return self for unbound PSR container types
- Allow null from instance factories
- Added tryGet() and tryGetWith()
- Fixed return type hints for getWith()

[Full list of changes](https://github.com/decodelabs/pandora/compare/v0.2.10...v0.2.11)

---

### [v0.2.10](https://github.com/decodelabs/pandora/commits/v0.2.10) - 29th October 2023

- Added default resolution via Archetype

[Full list of changes](https://github.com/decodelabs/pandora/compare/v0.2.9...v0.2.10)

---

### [v0.2.9](https://github.com/decodelabs/pandora/commits/v0.2.9) - 26th September 2023

- Converted phpstan doc comments to generic

[Full list of changes](https://github.com/decodelabs/pandora/compare/v0.2.8...v0.2.9)

---

### [v0.2.8](https://github.com/decodelabs/pandora/commits/v0.2.8) - 26th November 2022

- Fixed binding target resolution infinite loop

[Full list of changes](https://github.com/decodelabs/pandora/compare/v0.2.7...v0.2.8)

---

### [v0.2.7](https://github.com/decodelabs/pandora/commits/v0.2.7) - 26th November 2022

- Added key-value store to container
- Added referential type aliases to bound objects
- Migrated to use effigy in CI workflow

[Full list of changes](https://github.com/decodelabs/pandora/compare/v0.2.6...v0.2.7)

---

### [v0.2.6](https://github.com/decodelabs/pandora/commits/v0.2.6) - 14th November 2022

- Check binding list for class-string $target resolution
- Fixed PHP8.1 testing

[Full list of changes](https://github.com/decodelabs/pandora/compare/v0.2.5...v0.2.6)

---

### [v0.2.5](https://github.com/decodelabs/pandora/commits/v0.2.5) - 31st October 2022

- Added get() return type hints

[Full list of changes](https://github.com/decodelabs/pandora/compare/v0.2.4...v0.2.5)

---

### [v0.2.4](https://github.com/decodelabs/pandora/commits/v0.2.4) - 30th September 2022

- Added ArrayAccess to Container
- Updated composer check script
- Updated CI environment

[Full list of changes](https://github.com/decodelabs/pandora/compare/v0.2.3...v0.2.4)

---

### [v0.2.3](https://github.com/decodelabs/pandora/commits/v0.2.3) - 24th August 2022

- Fixed ECS issue in Binding

[Full list of changes](https://github.com/decodelabs/pandora/compare/v0.2.2...v0.2.3)

---

### [v0.2.2](https://github.com/decodelabs/pandora/commits/v0.2.2) - 24th August 2022

- Fixed Binding factory instantiation

[Full list of changes](https://github.com/decodelabs/pandora/compare/v0.2.1...v0.2.2)

---

### [v0.2.1](https://github.com/decodelabs/pandora/commits/v0.2.1) - 24th August 2022

- Added concrete types to all members

[Full list of changes](https://github.com/decodelabs/pandora/compare/v0.2.0...v0.2.1)

---

### [v0.2.0](https://github.com/decodelabs/pandora/commits/v0.2.0) - 23rd August 2022

- Removed PHP7 compatibility
- Updated PSR Container interface to v2
- Updated ECS to v11
- Updated PHPUnit to v9

[Full list of changes](https://github.com/decodelabs/pandora/compare/v0.1.1...v0.2.0)

---

### [v0.1.1](https://github.com/decodelabs/pandora/commits/v0.1.1) - 10th March 2022

- Transitioned from Travis to GHA
- Updated PHPStan and ECS dependencies

[Full list of changes](https://github.com/decodelabs/pandora/compare/v0.1.0...v0.1.1)

---

### [v0.1.0](https://github.com/decodelabs/pandora/commits/v0.1.0) - 10th May 2021

- Ported codebase from DF
