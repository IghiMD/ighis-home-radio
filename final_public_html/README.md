# 🎵 Ighi's Home Radio

<div align="center">
  <img src="https://i.imgur.com/Ym67jVJ.jpeg" alt="Ighi's Home Radio Logo" width="200" height="200" style="border-radius: 50%;">
  
  **Cross-Generational Internet Radio Station**
  
  [![Live Demo](https://img.shields.io/badge/🔴_LIVE-www.ighishomeradio.com-ff2d55?style=for-the-badge)](https://www.ighishomeradio.com)
  [![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
  [![Status](https://img.shields.io/badge/status-active-00ff88?style=for-the-badge)](https://www.ighishomeradio.com)
</div>

---

## 🌟 About

**Ighi's Home Radio** is a unique family internet radio station that bridges generations through music. Founded by anesthesiologist Ighi and his Gen Z daughter Kika, we stream 24/7 from 90s classics to today's hits.

### 🎯 Key Features

- 🎵 **24/7 Live Streaming** - Non-stop music from multiple decades
- 🤖 **AI-Powered Content** - Intelligent song information in gentleman style
- 🔍 **Advanced Metadata Enrichment** - Integration with Spotify, Last.fm, MusicBrainz, and more
- 🌐 **Multilingual Support** - English and Slovak interfaces
- 📱 **Responsive Design** - Perfect on all devices
- 🎨 **Modern UI** - Glassmorphism design with smooth animations
- 📊 **Real-time Song Recognition** - ICY metadata processing
- 🎭 **"Hop alebo Trop" Show** - Special 2-hour generational music battle

## 🖼️ Screenshots

<div align="center">
  <img src="https://github.com/yourusername/ighis-home-radio/assets/screenshot1.png" alt="Main Interface" width="600">
  <p><em>Main Radio Interface with Now Playing</em></p>
</div>

## 🛠️ Tech Stack

### Frontend
- **HTML5** with semantic markup
- **CSS3** with custom properties and animations
- **Vanilla JavaScript** (ES6+)
- **Web Audio API** for streaming
- **Responsive Design** with CSS Grid/Flexbox

### Backend
- **PHP 8.2+** 
- **cURL** for API integrations
- **JSON** for data exchange
- **Server-Sent Events** for real-time updates

### APIs & Services
- 🎵 **Spotify Web API** - Track metadata and album artwork
- 📻 **Last.fm API** - Music statistics and genres
- 🎼 **MusicBrainz** - Detailed music metadata (FREE)
- 💿 **Deezer API** - Alternative metadata source (FREE)
- 🎬 **YouTube Data API** - Music videos and additional info
- 🤖 **AI Services**:
  - **Google Gemini** - AI-powered song descriptions
  - **OpenAI GPT** - Alternative AI content
  - **Perplexity AI** - Enhanced information
  - **DeepL API** - Professional translations

## 📦 Installation

### Prerequisites
- PHP 8.2 or higher
- Apache/Nginx web server
- SSL certificate (for HTTPS)
- cURL PHP extension
- JSON PHP extension

### Quick Start

1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/ighis-home-radio.git
   cd ighis-home-radio
   ```

2. **Create configuration file**
   ```bash
   cp .env.example .env
   ```

3. **Edit `.env` file with your API keys**
   ```env
   # Stream Configuration
   STREAM_URL="https://your-stream-url:port/stream"
   
   # API Keys (get your own)
   SPOTIFY_CLIENT_ID="your_spotify_client_id"
   SPOTIFY_CLIENT_SECRET="your_spotify_secret"
   GEMINI_API_KEY="your_gemini_key"
   # ... etc
   ```

4. **Set up directories**
   ```bash
   chmod 755 cache/
   chmod 755 data/
   chmod 755 logs/
   ```

5. **Configure web server**
   - Point document root to the project folder
   - Ensure `.htaccess` is processed (Apache)
   - Enable URL rewriting

## ⚙️ Configuration

### Essential API Keys

#### Free Services (No API Key Required)
- ✅ **MusicBrainz** - Music metadata
- ✅ **Deezer** - Basic track information

#### Paid/Freemium Services
1. **Spotify** (Free tier available)
   - Register at [Spotify for Developers](https://developer.spotify.com)
   - Create an app and get credentials

2. **Google Gemini** (Free tier: $0 for 60 queries/minute)
   - Get key at [Google AI Studio](https://makersuite.google.com/app/apikey)

3. **Last.fm** (Free for non-commercial use)
   - Register at [Last.fm API](https://www.last.fm/api/account/create)

4. **DeepL** (Free tier: 500,000 chars/month)
   - Sign up at [DeepL Pro API](https://www.deepl.com/pro-api)

### Directory Structure
```
ighis-home-radio/
├── index.html          # Main application
├── config.php          # Configuration loader
├── .env               # Environment variables (create this)
├── ai_info.php        # AI content generation
├── metadata_proxy.php  # Stream metadata proxy
├── webhook.php        # Track history webhook
├── cache/             # Cache directory
│   └── ai/           # AI responses cache
├── data/             # Data storage
├── logs/             # Application logs
└── uploads/          # Event images
```

## 🎨 Customization

### Changing Colors
Edit CSS variables in `index.html`:
```css
:root {
    --primary-red: #ff2d55;
    --primary-blue: #007aff;
    --accent-green: #00ff88;
    /* ... more colors */
}
```

### Adding Languages
1. Add translations to the `translations` object
2. Add language button to the switcher
3. Update `switchLanguage()` function

## 📡 API Endpoints

### Public Endpoints
- `GET /` - Main radio interface
- `GET /metadata_proxy.php` - Current song metadata
- `GET /ai_info.php?artist=X&title=Y&lang=sk` - AI song information
- `GET /event_api.php` - Current event information

### Webhook
- `POST /webhook.php` - Track history logging

## 🚀 Features in Detail

### AI-Powered Song Information
- Generates sophisticated, "gentleman-style" descriptions
- Multi-language support (EN/SK)
- Fallback system for reliability
- 30-second rotation with skip option

### Metadata Enrichment Pipeline
1. **ICY Metadata** → Basic artist/title
2. **Spotify Search** → Album, year, artwork
3. **MusicBrainz** → Label, ISRC
4. **Last.fm** → Genre, play statistics
5. **AI Generation** → Enhanced descriptions

### Live Show System
- Dynamic event management
- Real-time status updates
- Countdown timers
- Social media integration

## 🤝 Contributing

We welcome contributions! Please:
1. Fork the repository
2. Create a feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## ⚖️ License

This project is currently **not licensed for public use**. All rights reserved.

For licensing inquiries, please contact: hello@ighishomeradio.com

## 👨‍👩‍👧 The Team

- **Ighi** - Founder, DJ, 90s Music Curator
- **Kika** - Co-founder, Modern Music Specialist
- **Contributors** - [List of contributors](https://github.com/yourusername/ighis-home-radio/contributors)

## 📞 Contact

- 🌐 **Website**: [www.ighishomeradio.com](https://www.ighishomeradio.com)
- 📧 **Email**: hello@ighishomeradio.com
- 📸 **Instagram**: [@IghisHomeRadio](https://www.instagram.com/IghisHomeRadio/)
- 🎬 **TikTok**: [@ighishomeradio](https://www.tiktok.com/@ighishomeradio)
- 🐦 **Twitter**: [@IghisHomeRadio](https://x.com/IghisHomeRadio)
- 📘 **Facebook**: [Ighi's Home Radio](https://www.facebook.com/@ighi.s.home.radio)

## 🙏 Acknowledgments

- [Icecast](https://icecast.org/) for streaming technology
- All the API providers for their excellent services
- Our listeners for their continuous support
- The open-source community

## 💖 Support Us

If you enjoy our radio, consider supporting us:

[![PayPal](https://img.shields.io/badge/PayPal-00457C?style=for-the-badge&logo=paypal&logoColor=white)](https://paypal.me/ighishomeradio)
[![Buy Me A Coffee](https://img.shields.io/badge/Buy%20Me%20A%20Coffee-FFDD00?style=for-the-badge&logo=buy-me-a-coffee&logoColor=black)](https://paypal.me/ighishomeradio)

---

<div align="center">
  <p>
    Made with ❤️ by the Ighi's Home Radio Family
  </p>
  <p>
    <strong>🎵 Bridging Generations Through Music Since 2024 🎵</strong>
  </p>
</div>

## 🔧 Troubleshooting

### Common Issues

#### Error 500 on AI Info
- Check if `.env` file exists and is readable
- Verify at least one AI API key is set
- Check PHP error logs
- Use `ai_info_simple.php` as fallback

#### No Metadata Showing
- Verify stream URL in `.env`
- Check if `metadata_proxy.php` is accessible
- Ensure cURL PHP extension is enabled

#### API Rate Limits
- Spotify: 180 requests per minute
- YouTube: 10,000 units per day
- Last.fm: 5 requests per second
- Implement caching to reduce API calls

### Debug Mode
Enable debug mode by clicking the logo 5 times. This shows:
- API calls in real-time
- Metadata enrichment process
- Error messages
- Performance metrics

## 🗺️ Roadmap

- [ ] Mobile app (iOS/Android)
- [ ] User accounts and favorites
- [ ] Song request system
- [ ] Live chat during shows
- [ ] Podcast archives
- [ ] DJ mix uploads
- [ ] Advanced analytics dashboard
- [ ] Multi-stream support

## 📊 Stats

![Radio Uptime](https://img.shields.io/uptimerobot/ratio/m792961280-5c5f5c5f5c5f5c5f5c5f5c5f?style=for-the-badge&label=Radio%20Uptime)
![GitHub Stars](https://img.shields.io/github/stars/yourusername/ighis-home-radio?style=for-the-badge)
![GitHub Issues](https://img.shields.io/github/issues/yourusername/ighis-home-radio?style=for-the-badge)