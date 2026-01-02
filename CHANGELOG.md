# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.3] - 2024-12-XX

### Removed
- GitHub token configuration option (simplified for public repositories only)

### Improved
- Simplified updater code by removing private repository support
- Cleaner settings interface

## [1.0.2] - 2024-12-XX

### Added
- Update checker in Settings tab with real-time version comparison
- "Check for Updates" button to force immediate update check
- "Clear Update Cache" button to refresh cached version data
- Improved updater error handling and debugging

### Improved
- Enhanced GitHub API integration with better error messages
- Better ZIP file detection from GitHub releases
- Improved plugin basename matching for update detection

## [1.0.1] - 2024-12-XX

### Added
- Settings link on plugin page (next to Deactivate button)
- Activation notice with link to Installation Wizard
- Quick access to configuration from plugins list

## [1.0.0] - 2024-12-XX

### Added
- Initial release
- Multi-step decision tree functionality with conditional branching
- Installation wizard for AI-powered configuration
- Customizable styling options with preset styles (Style 1, Style 2, Style 3, Custom)
- Recommendation system supporting pages, posts, products, and custom post types
- Usage tracking and statistics dashboard
- Import/Export functionality for configurations
- JSON starter pack generator
- Responsive design for mobile and desktop
- Translation support (Finnish included)
- GitHub integration for automatic updates
- Step and option descriptions
- Image support for options
- URL state management with readable query parameters
- Cookie-based state persistence
- Admin debugging tools
- Form reset functionality
- Shortcode support in recommendation descriptions
- Database tracking for user interactions
- Mobile-responsive image handling
- Tabbed admin interface (Steps, Recommendations, Styling, Statistics, Settings, Shortcode, Wizard)
- Collapsible step configuration boxes
- Results display customization (excerpt length, show/hide title/image/excerpt)
- Required field validation for target steps and recommendations
- Recommendation bundles with multiple linked content items

