#!/bin/bash

# GitHub Auto-Upload Script for Ighi's Home Radio
# Automaticky vytvorí repo a nahrá všetky súbory

echo "🚀 GitHub Upload Script pre Ighi's Home Radio"
echo "==========================================="

# Kontrola či je git nainštalovaný
if ! command -v git &> /dev/null; then
    echo "❌ Git nie je nainštalovaný. Nainštalujte: sudo apt-get install git"
    exit 1
fi

# Vstupné údaje
read -p "GitHub username: " GITHUB_USER
read -s -p "GitHub personal access token: " GITHUB_TOKEN
echo
read -p "Názov repozitára (default: ighis-home-radio): " REPO_NAME
REPO_NAME=${REPO_NAME:-ighis-home-radio}

# Vytvorenie .gitignore
echo "📝 Vytváranie .gitignore..."
cat > .gitignore << 'EOF'
# Environment files
.env
.env.local
.env.backup

# Data files
data/
cache/
logs/
backups/
uploads/

# Sensitive files
subscribers.json
notifications_log.json
*_backup_*.zip

# System files
.DS_Store
Thumbs.db
*.log
*.tmp
*.cache

# IDE files
.vscode/
.idea/
*.sublime-*

# PHP files
vendor/
composer.lock
EOF

# Vytvorenie README.md
echo "📝 Vytváranie README.md..."
cat > README.md << 'EOF'
# 🎵 Ighi's Home Radio

Medzigeneračné internetové rádio vytvorené otcom a dcérou.

## 🌟 O projekte

**Ighi's Home Radio** je jedinečná rodinná internetová rozhlasová stanica, ktorá spája generácie prostredníctvom hudby. Vysielajú 24/7 od 90. rokov až po dnešné hity.

### Funkcie
- 24/7 živé vysielanie
- AI generovaný obsah o skladbách
- Integrácia so Spotify, Last.fm, MusicBrainz
- Slovenské a anglické rozhranie
- Relácia "Hop alebo Trop"

## 🛠️ Technológie
- PHP 8.2+
- HTML5/CSS3/JavaScript
- Web Audio API
- Multiple API integrácie

## 📞 Kontakt
- Web: [ighishomeradio.net](https://ighishomeradio.net)
- Email: hello@ighishomeradio.com

## 💖 Podporte nás
[![PayPal](https://img.shields.io/badge/PayPal-00457C?style=for-the-badge&logo=paypal&logoColor=white)](https://paypal.me/ighishomeradio)

---
Vytvorené s ❤️ rodinou Ighi's Home Radio
EOF

# Vytvorenie LICENSE
echo "📝 Vytváranie LICENSE..."
cat > LICENSE << 'EOF'
MIT License

Copyright (c) 2024 Ighi's Home Radio

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
EOF

# Vytvorenie .env.example
echo "📝 Vytváranie .env.example..."
cat > .env.example << 'EOF'
# Stream Configuration
STREAM_URL="https://your-stream-url.com:port/stream"

# API Keys (obtain from respective services)
SPOTIFY_CLIENT_ID=""
SPOTIFY_CLIENT_SECRET=""
LASTFM_API_KEY=""
YOUTUBE_API_KEY=""
GEMINI_API_KEY=""

# Email Configuration
SMTP_HOST="smtp.gmail.com"
SMTP_PORT=587
SMTP_USERNAME=""
SMTP_PASSWORD=""

# WhatsApp Configuration
WHATSAPP_METHOD="callmebot"
WHATSAPP_API_KEY=""

# Admin Configuration
ADMIN_USERNAME="admin"
ADMIN_PASSWORD_HASH=""
EOF

# Inicializácia git repozitára
echo "🔧 Inicializácia Git repozitára..."
git init

# Konfigurácia git
git config user.name "$GITHUB_USER"
git config user.email "${GITHUB_USER}@users.noreply.github.com"

# Pridanie súborov
echo "📦 Pridávanie súborov..."
git add -A
git commit -m "Initial commit - Ighi's Home Radio 🎵"

# Vytvorenie GitHub repozitára cez API
echo "🌐 Vytváranie GitHub repozitára..."
curl -H "Authorization: token $GITHUB_TOKEN" \
     -H "Accept: application/vnd.github.v3+json" \
     https://api.github.com/user/repos \
     -d "{\"name\":\"$REPO_NAME\",\"description\":\"Medzigeneračné internetové rádio - Father & daughter internet radio station\",\"private\":false,\"has_issues\":true,\"has_projects\":false,\"has_wiki\":false}"

# Pridanie remote origin
echo "🔗 Pripájanie k GitHub..."
git remote add origin "https://github.com/${GITHUB_USER}/${REPO_NAME}.git"

# Push na GitHub
echo "📤 Nahrávanie na GitHub..."
git branch -M main
git push -u origin main

echo ""
echo "✅ HOTOVO! Váš kód je teraz na GitHub:"
echo "🔗 https://github.com/${GITHUB_USER}/${REPO_NAME}"
echo ""
echo "📌 Nezabudnite:"
echo "1. Pridať tento link do Twilio.org žiadosti"
echo "2. Skontrolovať či sa všetko nahralo správne"
echo "3. Pravidelne aktualizovať: git add . && git commit -m 'update' && git push"
