# Changelog - My Progress Block

All notable changes to this plugin will be documented in this file.

## [2.2.7] - 2026-04-23

### Fixed
- **sectionerror on admin/settings.php?section=blocksettingmy_progress**: Block had no settings.php
  file, so Moodle never registered the blocksettingmy_progress section in the admin tree. Visiting
  the settings URL (e.g. from the Quick Links block) threw a moodle_exception (sectionerror). Added
  a minimal settings.php with a $hassiteconfig guard and informational heading. No DB schema changes.

## [2.2.5] - 2026-03-11

### Performance
- PERF: Eliminated ~430 DB queries per page load for a student enrolled in 10 courses with 20 activities each.
- PERF: `get_fast_modinfo()` was called twice per course (once in `get_course_progress()` and once in `get_activity_completions()`). Now called once per course, halving modinfo load count.
- PERF: `completion->get_data($cm, true, $userid)` was called per activity per course, duplicated across both methods. Replaced with a single bulk SQL query on `course_modules_completion JOIN course_modules` covering all enrolled courses at once.
- PERF: `get_course_progress()` and `get_activity_completions()` merged into `build_course_data()`: same modinfo traversal now produces both the progress percentage and the activity list in one pass.
- Result: page load for a student in 10 courses reduces from approximately 444 queries to approximately 15 queries (30x reduction). MUC cache (30 second TTL) continues to serve repeat loads from memory.

## [2.2.4] - 2025-12-24

### Fixed
- Fixed selector bug in sortCourses and updateCategoryCounts - was using `.mp-course-wrapper` instead of `.mp-course`
- Added `.mp-category-count` element to template for displaying course count per category
- Added `coursecount` variable to PHP progress_service for proper count display

### Changed
- Rebuilt AMD minified JavaScript with proper selectors

## [2.2.3] - 2025-12-24

### Fixed
- Fixed selector bug in sortCourses - was using `.mp-course-wrapper` instead of `.mp-course`

## [2.2.2] - 2025-12-24

### Fixed
- Rebuilt AMD minified JavaScript for working filter/sort buttons
- Ensured all event handlers are properly compiled

## [2.2.1] - 2025-12-24

### Fixed
- Rebuilt AMD minified JavaScript with esbuild

## [2.2.0] - 2025-12-24

### Added
- Pill-style filter labels replacing dropdown menus
- Inline sort labels instead of dropdown
- Course search box with 200ms debounce
- Inline dates (Start, Completed, Expiry) displayed next to progress percentage

### Changed
- Modern SaaS styling matching AI Grader design system

## [2.1.1] - 2025-12-23

### Security
- Added Privacy API provider for GDPR compliance

## [2.1.0] - 2025-12-20

### Fixed
- Force Inter font on all filter controls (dropdowns, buttons, toggle buttons)
- Added explicit `font-family: var(--mp-font)` to `.mp-select`, `.mp-control-btn`, `.mp-toggle-btn`
- Ensured consistent typography across all UI elements

### Changed
- Aligned design system with lms-labs.com (Inter font, HSL colors, 0.5rem radius)

## [2.0.0] - 2025-12-19

### Added
- Complete rebuild with modern CSS design system
- Progress tracking with visual indicators
- Filter controls for course/activity filtering
- Mobile-responsive layout

## [1.0.0] - 2025-12-01

### Added
- Initial release
- Basic progress display for enrolled courses
- Moodle 4.1+ compatibility
