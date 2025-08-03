# Changelog

All notable changes to Ighi's Home Radio will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### 🚀 Planned
- Mobile app (iOS/Android)
- User accounts and favorites
- Song request system
- Live chat during shows
- Podcast archives

## [2.0.0] - 2024-12-31

### ✨ Added
- AI-powered song information in "gentleman style"
- Multi-language support (EN/SK)
- Integration with Perplexity AI, OpenAI GPT, and Google Gemini
- DeepL translation service
- Enhanced metadata enrichment pipeline
- Real-time event system for live shows
- Debug mode (5 clicks on logo)
- Responsive design improvements
- Advanced caching system
- Automatic fallback system
- Song information rotation with skip functionality

### 🔧 Changed
- Complete UI redesign with glassmorphism effects
- Improved metadata accuracy
- Better error handling
- Enhanced mobile experience
- Optimized API calls with intelligent caching

### 🐛 Fixed
- ICY metadata parsing issues
- CORS problems with direct HTTPS streams
- Memory leaks in long sessions
- Mobile layout issues
- API rate limiting problems

### 🔒 Security
- Moved all API keys to .env file
- Added proper input sanitization
- Implemented secure headers
- Enhanced .htaccess protection

## [1.5.0] - 2024-11-15

### ✨ Added
- "Hop alebo Trop" show system
- Email/WhatsApp notifications
- Admin panel for event management
- Social media integration
- PayPal donation button

### 🔧 Changed
- Updated to PHP 8.2
- Improved stream stability
- Better metadata handling

## [1.0.0] - 2024-06-01

### 🎉 Initial Release
- Basic streaming functionality
- ICY metadata display
- Spotify integration
- Last.fm integration
- Simple web interface
- 24/7 streaming capability

---

## Version Guidelines

- **Major version (X.0.0)**: Breaking changes, major redesigns
- **Minor version (0.X.0)**: New features, non-breaking changes
- **Patch version (0.0.X)**: Bug fixes, small improvements

## How to Update

1. Backup your `.env` file
2. Pull latest changes
3. Check for new variables in `.env.example`
4. Update your `.env` file if needed
5. Clear cache directories
6. Test thoroughly

---

Made with ❤️ by the Ighi's Home Radio Family