# Changelog

All notable changes to this extension are documented here. The format
is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [1.1.2] - 2026-10-04

### Changed
- Warmup Log grid: Status shows coloured labels (Success, Failed, Skipped, Pending) with a select filter, Page Type has a select filter with readable labels, and rows without an HTTP response show "No response" instead of 0.
- Concurrent Requests must be a whole number from 1 to 50; larger stored values are capped at 50.

### Fixed
- Warmup Schedule (Cron) is validated on save; an invalid cron expression is rejected with a clear message instead of silently breaking the warmup cron job.
- Pages to Warm Up can now be cleared (saving with nothing selected no longer keeps the previous selection).
- With "Enable Cache Manager" set to No, the warmup and invalidation sub-settings are now hidden together with their parent toggles.
- Removed duplicate unit test files that were placed outside Test/Unit.
