# Installation & Test des Time Tracker Plugins

## Voraussetzungen
- Nextcloud 27+ Installation (lokal oder Server)
- PHP 8.0+
- Node.js 16+
- Composer

## Schnellstart - Lokaler Test

### 1. Dependencies Installieren

```powershell
cd timetracking

# PHP Dependencies
composer install

# Frontend Dependencies
npm install --legacy-peer-deps
```

### 2. Frontend Bauen

```powershell
# Production Build
npm run build

# Oder für Entwicklung (mit Watch-Mode)
npm run watch
```

### 3. In Nextcloud Installieren

**Option A: Symlink (Entwicklung - Empfohlen)**
```powershell
# Erstelle Symlink im Nextcloud apps-Verzeichnis
# Ersetze C:\xampp\htdocs\nextcloud mit deinem Nextcloud-Pfad
New-Item -ItemType SymbolicLink -Path "C:\xampp\htdocs\nextcloud\apps\timetracking" -Target "C:\Projects\nextcloud-timetracking"
```

**Option B: Kopieren**
```powershell
# Kopiere den kompletten Ordner
Copy-Item -Recurse -Path "C:\Projects\nextcloud-timetracking" -Destination "C:\xampp\htdocs\nextcloud\apps\timetracking"
```

### 4. App in Nextcloud Aktivieren

1. Öffne deinen Browser: `http://localhost/nextcloud`
2. Login als Admin
3. Gehe zu **Einstellungen** (oben rechts) → **Apps**
4. Suche nach "Time Tracker" in der App-Liste
5. Klicke auf **Aktivieren**

### 5. App Öffnen

- Klicke auf das **Time Tracker** Icon in der oberen Menüleiste
- Oder gehe direkt zu: `http://localhost/nextcloud/apps/timetracking`

## Lokale Nextcloud Installation (Falls noch nicht vorhanden)

### Mit XAMPP (Windows)

1. **XAMPP installieren**: https://www.apachefriends.org/
2. **Nextcloud herunterladen**: https://nextcloud.com/install/
3. Entpacke Nextcloud nach `C:\xampp\htdocs\nextcloud`
4. Starte Apache & MySQL in XAMPP
5. Öffne `http://localhost/nextcloud` und folge dem Setup

### Mit Docker (alle Plattformen)

```powershell
# Nextcloud mit Docker starten
docker run -d -p 8080:80 --name nextcloud nextcloud:latest

# App-Ordner mounten
docker run -d -p 8080:80 --name nextcloud `
  -v C:\Projects\nextcloud-timetracking:/var/www/html/custom_apps/timetracking `
  nextcloud:latest
```

Dann öffne: `http://localhost:8080`

## Entwicklung

### Frontend Live-Development

```powershell
# Terminal 1: Watch-Mode für automatisches Rebuilding
npm run watch

# Terminal 2: Optional - PHP Development Server (wenn kein Apache läuft)
cd C:\xampp\htdocs\nextcloud
php -S localhost:8000
```

Nach Änderungen an Vue-Komponenten:
- Bei `npm run watch`: Automatisch neu gebaut
- Browser-Refresh (F5)

### Datenbank-Migration prüfen

```powershell
# In Nextcloud-Verzeichnis
cd C:\xampp\htdocs\nextcloud

# OCC-Befehl ausführen (Nextcloud CLI)
php occ app:enable timetracking
php occ maintenance:repair
```

## Erste Schritte nach Installation

1. **Kunde anlegen**:
   - Time Tracker öffnen
   - Navigation → "Kunden"
   - "Neuer Kunde" klicken
   - Daten eingeben und speichern

2. **Projekt erstellen**:
   - Navigation → "Projekte"
   - "Neues Projekt"
   - Kunde auswählen, Stundensatz festlegen

3. **Zeit erfassen**:
   - Navigation → "Zeiterfassung"
   - Projekt wählen
   - Timer starten oder manuellen Eintrag erstellen

4. **Berichte ansehen**:
   - Navigation → "Berichte"
   - Berichtstyp wählen (Kunde/Projekt/Mitarbeiter)
   - Zeitraum festlegen und erstellen

5. **Arbeitszeitgesetz-Check**:
   - Berichte → "Arbeitszeitgesetz-Prüfung"
   - Monat auswählen
   - Automatische Compliance-Prüfung anzeigen

## Troubleshooting

### App erscheint nicht in der App-Liste
```powershell
# Cache leeren
php occ maintenance:repair
php occ app:list
```

### JavaScript-Fehler in der Console
```powershell
# Frontend neu bauen
npm run build
# Browser-Cache leeren (Strg+Shift+R)
```

### Datenbank-Fehler
```powershell
# Migrations erneut ausführen
php occ app:disable timetracking
php occ app:enable timetracking
```

### Logs anschauen
- Nextcloud Logs: `data/nextcloud.log`
- Browser Console: F12 → Console-Tab
- PHP Errors: `php_error.log` (je nach Setup)

## Deinstallation

```powershell
# In Nextcloud Admin → Apps → Time Tracker → Deaktivieren

# Oder per CLI
php occ app:disable timetracking
php occ app:remove timetracking

# Datenbank-Tabellen werden automatisch entfernt
```

## Nützliche Befehle

```powershell
# Alle OCC-Befehle für die App
php occ app:list                    # Alle Apps auflisten
php occ app:enable timetracking      # App aktivieren
php occ app:disable timetracking     # App deaktivieren
php occ app:check-code timetracking  # Code-Qualität prüfen

# Entwicklung
npm run dev                         # Dev-Server
npm run build                       # Production Build
npm run watch                       # Watch-Mode
npm run lint                        # Code prüfen
npm run lint:fix                    # Auto-Fix
```

## Support

Bei Problemen:
- Nextcloud Logs prüfen
- Browser Console prüfen (F12)
- GitHub Issues: https://github.com/moregeo/nextcloud-timetracking/issues
