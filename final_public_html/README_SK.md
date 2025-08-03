# 🎵 Ighi's Home Radio

<div align="center">
  <img src="https://i.imgur.com/Ym67jVJ.jpeg" alt="Ighi's Home Radio Logo" width="200" height="200" style="border-radius: 50%;">
  
  **Medzigeneračné Internetové Rádio**
  
  [![Živé vysielanie](https://img.shields.io/badge/🔴_NAŽIVO-ighishomeradio.net-ff2d55?style=for-the-badge)](https://ighishomeradio.net)
  [![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
  [![Status](https://img.shields.io/badge/status-aktívne-00ff88?style=for-the-badge)](https://ighishomeradio.net)
</div>

---

## 🌟 O projekte

**Ighi's Home Radio** je jedinečná rodinná internetová rozhlasová stanica, ktorá spája generácie prostredníctvom hudby. Založili ju anesteziológ Ighi a jeho dcéra z generácie Z - Kika. Vysielajú 24/7 od 90. rokov až po dnešné hity.

### 🎯 Hlavné funkcie

- 🎵 **24/7 Živé vysielanie** - Nepretržitá hudba z viacerých dekád
- 🤖 **AI generovaný obsah** - Inteligentné informácie o skladbách v gentlemanskom štýle
- 🔍 **Pokročilé obohacovanie metadát** - Integrácia so Spotify, Last.fm, MusicBrainz a ďalšími
- 🌐 **Viacjazyčná podpora** - Slovenské a anglické rozhranie
- 📱 **Responzívny dizajn** - Perfektné na všetkých zariadeniach
- 🎨 **Moderné UI** - Glassmorphism dizajn s plynulými animáciami
- 📊 **Real-time rozpoznávanie skladieb** - Spracovanie ICY metadát
- 🎭 **Relácia "Hop alebo Trop"** - Špeciálna 2-hodinová generačná hudobná bitka

## 🛠️ Technológie

### Frontend
- **HTML5** so sémantickým značkovaním
- **CSS3** s custom properties a animáciami
- **Vanilla JavaScript** (ES6+)
- **Web Audio API** pre streamovanie
- **Responzívny dizajn** s CSS Grid/Flexbox

### Backend
- **PHP 8.2+** 
- **cURL** pre API integrácie
- **JSON** pre výmenu dát
- **.env** pre bezpečnú konfiguráciu

### API a služby
- 🎵 **Spotify Web API** - Metadáta skladieb a albumové obaly
- 📻 **Last.fm API** - Hudobné štatistiky a žánre
- 🎼 **MusicBrainz** - Detailné hudobné metadáta (ZADARMO)
- 💿 **Deezer API** - Alternatívny zdroj metadát (ZADARMO)
- 🎬 **YouTube Data API** - Hudobné videá a dodatočné info
- 🤖 **AI služby**:
  - **Google Gemini** - AI generované popisy skladieb
  - **OpenAI GPT** - Alternatívny AI obsah
  - **Perplexity AI** - Rozšírené informácie
  - **DeepL API** - Profesionálne preklady

## 📦 Inštalácia

### Požiadavky
- PHP 8.2 alebo vyšší
- Apache/Nginx web server
- SSL certifikát (pre HTTPS)
- cURL PHP rozšírenie
- JSON PHP rozšírenie

### Rýchly štart

1. **Naklonujte repozitár**
   ```bash
   git clone https://github.com/yourusername/ighis-home-radio.git
   cd ighis-home-radio
   ```

2. **Vytvorte konfiguračný súbor**
   ```bash
   cp .env.example .env
   ```

3. **Upravte `.env` súbor s vašimi API kľúčmi**
   ```env
   # Stream konfigurácia
   STREAM_URL="https://fra-pioneer01.dedicateware.com:2415/stream"
   
   # API kľúče
   SPOTIFY_CLIENT_ID="váš_spotify_client_id"
   SPOTIFY_CLIENT_SECRET="váš_spotify_secret"
   GEMINI_API_KEY="váš_gemini_kľúč"
   # ... atď
   ```

4. **Nastavte priečinky**
   ```bash
   chmod 755 cache/
   chmod 755 data/
   chmod 755 logs/
   ```

5. **Nakonfigurujte web server**
   - Nasmerujte document root na priečinok projektu
   - Zabezpečte spracovanie `.htaccess` (Apache)
   - Povoľte URL rewriting

## ⚙️ Konfigurácia

### Získanie API kľúčov

#### Bezplatné služby (nevyžadujú API kľúč)
- ✅ **MusicBrainz** - Hudobné metadáta
- ✅ **Deezer** - Základné informácie o skladbách

#### Platené/Freemium služby
1. **Spotify** (dostupná free verzia)
   - Registrujte sa na [Spotify for Developers](https://developer.spotify.com)
   - Vytvorte aplikáciu a získajte prihlasovacie údaje

2. **Google Gemini** (Free tier: 60 požiadaviek/minútu zadarmo)
   - Získajte kľúč na [Google AI Studio](https://makersuite.google.com/app/apikey)

3. **Last.fm** (Zadarmo pre nekomerčné použitie)
   - Registrujte sa na [Last.fm API](https://www.last.fm/api/account/create)

4. **DeepL** (Free tier: 500 000 znakov/mesiac)
   - Prihláste sa na [DeepL Pro API](https://www.deepl.com/pro-api)

## 🎨 Prispôsobenie

### Zmena farieb
Upravte CSS premenné v `index.html`:
```css
:root {
    --primary-red: #ff2d55;
    --primary-blue: #007aff;
    --accent-green: #00ff88;
    /* ... ďalšie farby */
}
```

### Pridanie jazykov
1. Pridajte preklady do objektu `translations`
2. Pridajte jazykové tlačidlo do prepínača
3. Aktualizujte funkciu `switchLanguage()`

## 🤝 Ako prispieť

Uvítame príspevky! Prosím:
1. Forknite repozitár
2. Vytvorte feature branch (`git checkout -b feature/SkvelaFunkcia`)
3. Commitnite zmeny (`git commit -m 'Pridať skvelú funkciu'`)
4. Pushnite do branch (`git push origin feature/SkvelaFunkcia`)
5. Otvorte Pull Request

## 👨‍👩‍👧 Tím

- **Ighi** - Zakladateľ, DJ, kurátor hudby 90. rokov
- **Kika** - Spoluzakladateľka, špecialista na modernú hudbu

## 📞 Kontakt

- 🌐 **Web**: [ighishomeradio.net](https://ighishomeradio.net)
- 📧 **Email**: hello@ighishomeradio.com
- 📸 **Instagram**: [@IghisHomeRadio](https://www.instagram.com/IghisHomeRadio/)
- 🎬 **TikTok**: [@ighishomeradio](https://www.tiktok.com/@ighishomeradio)
- 🐦 **Twitter**: [@IghisHomeRadio](https://x.com/IghisHomeRadio)
- 📘 **Facebook**: [Ighi's Home Radio](https://www.facebook.com/@ighi.s.home.radio)

## 💖 Podporte nás

Ak sa vám naše rádio páči, zvážte podporu:

[![PayPal](https://img.shields.io/badge/PayPal-00457C?style=for-the-badge&logo=paypal&logoColor=white)](https://paypal.me/ighishomeradio)

---

<div align="center">
  <p>
    Vytvorené s ❤️ rodinou Ighi's Home Radio
  </p>
  <p>
    <strong>🎵 Spájame generácie prostredníctvom hudby od roku 2024 🎵</strong>
  </p>
</div>

## 🔧 Riešenie problémov

### Časté problémy

#### Chyba 500 pri AI Info
- Skontrolujte či existuje `.env` súbor a je čitateľný
- Overte že aspoň jeden AI API kľúč je nastavený
- Skontrolujte PHP error logy
- Použite `ai_info_simple.php` ako záložné riešenie

#### Nezobrazujú sa metadáta
- Overte stream URL v `.env`
- Skontrolujte či je `metadata_proxy.php` prístupný
- Zabezpečte že cURL PHP rozšírenie je povolené

### Debug režim
Povoľte debug režim 5-násobným kliknutím na logo. Zobrazí:
- API volania v reálnom čase
- Proces obohacovania metadát
- Chybové hlášky
- Výkonnostné metriky