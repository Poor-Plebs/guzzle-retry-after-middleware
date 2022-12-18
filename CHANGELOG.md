# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog][1], and this project adheres to
[Semantic Versioning][2].

*Types of changes:*

- `Added`: for new features.
- `Changed`: for changes in existing functionality.
- `Deprecated`: for soon-to-be removed features.
- `Removed`: for now removed features.
- `Fixed`: for any bug fixes.
- `Security`: in case of vulnerabilities.

## [Unreleased]

## [3.0.1] - 2022-12-18

### Fixed

- Fixed CI.

## [3.0.0] - 2022-12-18

### Changed

- Require php >= 8.1.

## [2.1.0] - 2022-11-11

### Changed

- Skip middleware if request option key is not set.

## [2.0.0] - 2022-11-05

### Added

- Added a matrix CI pipeline to test with php 8.0, 8.1 and 8.2, each with lowest
  and highest package requirements.

### Changed

- Changed the way the cache key is provided to the middleware. Instead of once
  on setup, the key is set on the request options either on client instantiation
  or on per request basis.

### Fixed

- Pushed minimum version for carbon to support php 8.2 with lowest requirements.

## [1.1.1] - 2022-11-05

### Changed

- Updated README.md with basic information about the package and it's usage.

## [1.1.0] - 2022-11-05

### Changed

- Loosen up php and dependency version constraints to allow a broader usage of
  this package.

## [1.0.1] - 2022-11-05

### Added

- Added initial CI/CD setup.

## [1.0.0] - 2022-11-05

### Added

- Initial version of the `poor-plebs/guzzle-retry-after-middleware`.

[1]: https://keepachangelog.com/en/1.1.0/
[2]: https://semver.org/spec/v2.0.0.html

[Unreleased]: https://github.com/Poor-Plebs/guzzle-retry-after-middleware/compare/3.0.1...HEAD
[3.0.1]: https://github.com/Poor-Plebs/guzzle-retry-after-middleware/releases/3.0.1
[3.0.0]: https://github.com/Poor-Plebs/guzzle-retry-after-middleware/releases/3.0.0
[2.1.0]: https://github.com/Poor-Plebs/guzzle-retry-after-middleware/releases/2.1.0
[2.0.0]: https://github.com/Poor-Plebs/guzzle-retry-after-middleware/releases/2.0.0
[1.1.1]: https://github.com/Poor-Plebs/guzzle-retry-after-middleware/releases/1.1.1
[1.1.0]: https://github.com/Poor-Plebs/guzzle-retry-after-middleware/releases/1.1.0
[1.0.1]: https://github.com/Poor-Plebs/guzzle-retry-after-middleware/releases/1.0.1
[1.0.0]: https://github.com/Poor-Plebs/guzzle-retry-after-middleware/releases/1.0.0
