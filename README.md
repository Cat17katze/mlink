# MLINK – Universal Music Link Resolver

Ein leichtgewichtiges, datenschutzfreundliches PHP- und JavaScript-Tool, um dem Streaming-Anbieter-Chaos ein Ende zu setzen.

**MLINK** nimmt Streaming-Links (Spotify, Deezer, YouTube Music, Apple Music, Tidal usw.) oder reine Freitextsuchen (Songtitel/Interpret) entgegen und generiert plattformübergreifende Direktlinks zu allen gängigen Musikstreaming-Diensten – inklusive Qobuz-Suchlink und einer vorbereiteten Teilen-Karte für Messenger und Social Media.

## ✨ Features

* **Universelle Erkennung:** Verarbeitet direkte URLs von Spotify, Deezer, YouTube, YouTube Music, Apple Music, Tidal, Amazon Music, SoundCloud und Bandcamp.
* **Freitextsuche (Deezer Fallback):** Akzeptiert auch Suchbegriffe wie `Rick Astley Never Gonna Give You Up`. Es ermittelt automatisch den besten Treffer und priorisiert Originalaufnahmen vor Remixen oder Karaoke-Versionen.
* **Tracking-Schutz:** Entfernt automatisch störende Tracking-Parameter (`si`, `utm_*`, `fbclid`, `igshid` etc.) aus Eingabe- und Ausgabe-URLs.
* **Qobuz-Integration:** Erzeugt automatisch gezielte Such-Links für Qobuz (das von Odesli/Songlink nativ nicht abgedeckt wird).
* **Zwei-Klick-Datenschutz (Opt-in für Cover):** Album-Cover von Drittservern werden aus Datenschutz- und Urheberrechtsgründen erst nach explizitem Klick des Nutzers geladen.
* **Teilen-Karte:** Formatiert alle Daten (Titel, Interpret, formatiertes Release-Datum und Plattformlinks) in ein kopierfertiges Textfeld.
* **Web Share API:** Unterstützt auf Mobilgeräten den nativen Teilen-Dialog des Betriebssystems (`navigator.share`).
* **CSS-agnostisch:** Das Frontend enthält keinerlei störende Stylesheets oder CSS-Klassen und fügt sich nahtlos in jedes bestehende Seiten-Design ein.
* **Keine API-Keys nötig:** Nutzt die offenen Schnittstellen von Odesli (`api.songlink.co`) und Deezer Search.

## 📁 Struktur

```
.
├── api/
│   └── site/
│       └── mlink/
│           └── index.php   # Backend-Endpoint & Test-UI
└── frontend_snippet.html   # HTML/JS zur Einbettung in deine Website
```

## 🚀 Voraussetzungen & Installation

### Voraussetzungen

* Webserver (Apache, Nginx oder Caddy)
* **PHP 8.1+** mit aktiviertem `php-curl` und `php-mbstring`
* Aktuelles CA-Zertifikatsbündel auf dem Server (z. B. Paket `ca-certificates` unter Debian / Ubuntu / YunoHost)

### 1. Backend einrichten

Kopiere `index.php` in dein Web-Verzeichnis (z. B. `/api/site/mlink/index.php`).

> **YunoHost / Debian Hinweis:** Das Skript greift automatisch auf `/etc/ssl/certs/ca-certificates.crt` zu, um den typischen `cURL-Fehler [60]: unable to get local issuer certificate` zu verhindern.

### 2. Frontend einbinden

Füge den Inhalt von `frontend_snippet.html` an beliebiger Stelle in deiner Website ein. Falls die `index.php` unter einem anderen Pfad liegt, passe die URL im JavaScript-Block an:

```javascript
const response = await fetch('/api/site/mlink/index.php', { ... });
```

## 🛠️ API-Nutzung

Der Endpunkt akzeptiert sowohl JSON-POST-Anfragen als auch reguläre Web-Formulare:

### Request

```bash
curl -X POST https://example.com/api/site/mlink/index.php \
     -H "Content-Type: application/json" \
     -d '{"url": "https://open.spotify.com/track/4cOdK2wGLETKBW3PvgPWqT"}'
```

### Response (Auszug)

```json
{
  "artist": "Rick Astley",
  "title": "Never Gonna Give You Up",
  "releaseDate": "23.02.1987",
  "cleanedUrl": "https://open.spotify.com/track/4cOdK2wGLETKBW3PvgPWqT",
  "thumbnail": "https://i.scdn.co/image/...",
  "pageUrl": "https://song.link/s/4cOdK2wGLETKBW3PvgPWqT",
  "links": [
    { "platform": "Spotify", "url": "https://open.spotify.com/track/4cOdK2wGLETKBW3PvgPWqT" },
    { "platform": "Deezer", "url": "https://www.deezer.com/track/..." },
    { "platform": "Qobuz (Suche)", "url": "https://www.qobuz.com/de-de/search?q=Rick%20Astley%20Never%20Gonna%20Give%20You%20Up" }
  ],
  "isTextSearch": false,
  "multipleResults": false,
  "totalResults": 1
}
```

## ⚖️ Lizenz & Rechtliches

Dieses Programm ist freie Software. Sie können es unter den Bedingungen der **GNU General Public License Version 2 (GPL-2.0)**, wie von der Free Software Foundation veröffentlicht, weitergeben und/oder modifizieren.

Die Veröffentlichung erfolgt in der Hoffnung, dass es Ihnen von Nutzen sein wird, aber OHNE IRGENDEINE GARANTIE; sogar ohne die implizite Garantie der MARKTREIFE oder der VERWENDBARKEIT FÜR EINEN BESTIMMTEN ZWECK. Details finden Sie in der [GNU General Public License Version 2](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html).

*Markenhinweis:* Alle genannten Produkt- und Plattformnamen (Spotify, Deezer, Apple Music, Tidal etc.) sind eingetragene Warenzeichen ihrer jeweiligen Eigentümer und werden hier ausschließlich zur beschreibenden Zuordnung verwendet.
