# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.2] - 2026-01-21

- Fix email flow edge cases: conditional email in JS request, validation before wp_mail in refunds
- Add fallbacks for null email in error_log statements and refund notifications
- Remove local test infrastructure (PHPUnit/Brain Monkey) - testing handled by Homeboy

## [1.2.1] - 2025-11-30

### Fixed
- Critical email template sprintf() bug causing fatal errors
- Mobile UX issues in modal system
- Email requirement preventing conversions (removed requirement, email captured from Stripe)

### Added
- Automated cleanup system for improved maintenance
- Enhanced modal system for better conversion rates

### Changed
- Made email field optional in modal to increase conversions
- Improved mobile experience and modal responsiveness

### Security
- Enhanced webhook security and data validation
