# RC Tournament Site — Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

This file tracks changes made to the project files since the baseline upload.

## ToDo
- In SQL. Add default comments to udpRegisterMatchResults when posting to RankChangeLog. Something like "Promoted to X victory over y" and "Demoted to X victory over y, fencer was between them".
- In SQL and PHP. Fix Judge vs Referee being switched.


## [1.2.2] - (uploaded 2025-09-24)
## Fixed
- tournament_overview.php
  - Fixed scaling in mobile
- fencer_details.php
  - Fixed scaling in mobile


## [1.2.1] - (uploaded 2025-09-24)
## Fixed
- tournament_overview.php
  - Fixed sizing and fill in chart.
  - Fixed tooltip color in light mode.
- fencer_details.php
  - Fixed sizing and fill in chart.
  - Fixed tooltip color in light mode.


## [1.2.0] - (uploaded 2025-09-22)
## Added
- tournament_overview.php
  - Show All and Clear All buttons to the chart

## Fixed
- register_match.php
  - Fixed Judge/Ref naming
- season_overview.php
  - Fixed Judge/Ref naming
- tournament_overview.php
  - Fixed Judge/Ref naming
- fencer_details.php
  - Fixed Judge/Ref naming

## [1.1.0] - (uploaded 2025-09-22)
### Added
- fencer_details.php
  - Added support for dark mode in chart.
- style.css
  - Added handling for .meta class in dark mode

### Removed
- tournament_overview.php
  - Removed debug commands


## [1.0.0] - (uploaded 2025-09-22)
- Initial snapshot of the project including:
  - index.php
  - login.php
  - logout.php
  - navbar.php
  - register_match.php
  - season_overview.php
  - style.css
  - tournament_overview.php
  - auth.php
  - config.php
  - favicon.ico
  - favicon.png
  - fencer_details.php
  - fencers.php