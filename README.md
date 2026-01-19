# Time Tracker für Nextcloud

Ein professionelles Zeiterfassungssystem für Nextcloud mit deutscher Arbeitszeitgesetz-Konformität.

## Nextcloud Best Practices

Diese App folgt den **offiziellen Nextcloud Entwicklungs-Best Practices**:

✅ **Vue.js Framework**: Nextcloud's offizielles Frontend-Framework (seit NC 16+)  
✅ **@nextcloud/vue Komponenten**: Verwendung der nativen Nextcloud UI-Komponenten (NcButton, NcModal, NcAppNavigation, etc.)  
✅ **Nextcloud Theming**: Automatische Integration in das Nextcloud-Theme  
✅ **@nextcloud/l10n**: Mehrsprachigkeit mit Nextcloud's Übersetzungssystem  
✅ **Nextcloud App Framework**: Backend mit OCP (Nextcloud Platform) Klassen  
✅ **Datenbankabstraktion**: QBMapper für sichere Datenbankzugriffe  
✅ **RESTful API**: Standard Nextcloud-Controller mit Response-Klassen  

## Features

### 📊 Kernfunktionen
- **Kunden-Verwaltung**: Kunden mit vollständigen Kontaktdaten anlegen und verwalten
- **Projekt-Management**: Projekte Kunden zuordnen, Stundensätze und Budgets definieren
- **Zeiterfassung**: 
  - Live-Timer zum Starten/Stoppen der Zeiterfassung
  - Manuelle Zeiteinträge für nachträgliche Erfassung
  - Beschreibungen und Abrechenbarkeit pro Eintrag

### 📈 Reporting
- **Kunden-Berichte**: Monatliche Übersichten pro Kunde mit allen Projekten
- **Projekt-Berichte**: Detaillierte Auswertung pro Projekt inkl. Mitarbeiter-Aufschlüsselung
- **Mitarbeiter-Berichte**: Persönliche Arbeitszeitübersichten mit täglicher Aufstellung
- **Arbeitszeitgesetz-Prüfung**: Automatische Compliance-Checks nach deutschem Recht

### ⚖️ Deutsche Arbeitsrecht-Konformität
Das System prüft automatisch die Einhaltung des deutschen Arbeitszeitgesetzes (ArbZG):

- ✅ Max. 8 Stunden täglich (Regelarbeitszeit)
- ✅ Max. 10 Stunden täglich (mit Ausgleichspflicht)
- ✅ Max. 48 Stunden wöchentlich
- ✅ Sonntagsarbeit-Erkennung (Ersatzruhetag erforderlich)
- ✅ Detaillierte Verstöße und Warnungen

## Installation

### Voraussetzungen
- Nextcloud 27 oder höher
- PHP 8.0 oder höher
- Node.js 16+ (für Frontend-Build)

### Schritte

1. **App herunterladen**
   ```bash
   cd /pfad/zu/nextcloud/apps
   git clone https://github.com/moregeo/timetracking.git timetracking
   cd timetracking
   ```

2. **Dependencies installieren**
   ```bash
   composer install
   npm install --legacy-peer-deps
   ```

3. **Frontend bauen**
   ```bash
   npm run build
   ```

4. **App in Nextcloud aktivieren**
   - Gehen Sie zu **Einstellungen** → **Apps**
   - Suchen Sie nach "Time Tracker"
   - Klicken Sie auf "Aktivieren"

## Entwicklung

### Frontend entwickeln
```bash
npm run dev       # Entwicklungsserver
npm run watch     # Build im Watch-Mode
```

### Code-Qualität
```bash
npm run lint      # Code-Analyse
npm run lint:fix  # Automatische Fehlerbehebung
```

## Nutzung

### 1. Kunden anlegen
- Navigieren Sie zu **Time Tracker** → **Kunden**
- Klicken Sie auf "Neuer Kunde"
- Füllen Sie die Kundeninformationen aus

### 2. Projekte erstellen
- Gehen Sie zu **Projekte**
- Klicken Sie auf "Neues Projekt"
- Wählen Sie den Kunden und definieren Sie Stundensatz und Budget

### 3. Zeit erfassen
- **Live-Timer**: Wählen Sie ein Projekt und starten Sie den Timer
- **Manuelle Einträge**: Tragen Sie Zeit nachträglich ein

### 4. Berichte erstellen
- Navigieren Sie zu **Berichte**
- Wählen Sie den gewünschten Berichtstyp
- Wählen Sie Zeitraum und Kunde/Projekt
- Klicken Sie auf "Bericht erstellen"

### 5. Arbeitszeitgesetz prüfen
- Gehen Sie zu **Berichte** → **Arbeitszeitgesetz-Prüfung**
- Wählen Sie Jahr und Monat
- Das System zeigt automatisch alle Verstöße und Warnungen an

## Datenbank-Schema

### Tabellen
- `tt_customers` - Kundendaten
- `tt_projects` - Projekte
- `tt_entries` - Zeiteinträge
- `tt_vacations` - Urlaubsverwaltung
- `tt_emp_settings` - Mitarbeitereinstellungen

## API-Endpunkte

### Kunden
- `GET /api/customers` - Alle Kunden abrufen
- `POST /api/customers` - Kunde erstellen
- `PUT /api/customers/{id}` - Kunde aktualisieren
- `DELETE /api/customers/{id}` - Kunde löschen

### Projekte
- `GET /api/projects` - Alle Projekte abrufen
- `POST /api/projects` - Projekt erstellen
- `PUT /api/projects/{id}` - Projekt aktualisieren
- `DELETE /api/projects/{id}` - Projekt löschen

### Zeiteinträge
- `GET /api/time-entries` - Zeiteinträge abrufen
- `POST /api/time-entries` - Zeiteintrag erstellen
- `POST /api/time-entries/start` - Timer starten
- `POST /api/time-entries/stop` - Timer stoppen
- `DELETE /api/time-entries/{id}` - Zeiteintrag löschen

### Berichte
- `GET /api/reports/customer/{customerId}/{year}/{month}` - Kunden-Monatsbericht
- `GET /api/reports/project/{projectId}/{year}/{month}` - Projekt-Monatsbericht
- `GET /api/reports/employee/{userId}/{year}/{month}` - Mitarbeiter-Monatsbericht
- `GET /api/reports/compliance/{userId}/{year}/{month}` - Arbeitszeitgesetz-Prüfung

## Rechtliche Hinweise

### Deutsches Arbeitszeitgesetz (ArbZG)
Diese App unterstützt bei der Einhaltung des deutschen Arbeitszeitgesetzes, ersetzt jedoch nicht die rechtliche Beratung. Die automatischen Prüfungen basieren auf folgenden Regelungen:

- **§3 ArbZG**: Tägliche Arbeitszeit von max. 8 Stunden (erweiterbar auf 10 Stunden)
- **§9 ArbZG**: Sonn- und Feiertagsruhe
- **§5 ArbZG**: Ruhepausen
- **§11 ArbZG**: Aufzeichnungspflicht

**Wichtig**: Die Verantwortung für die Einhaltung liegt beim Arbeitgeber. Diese Software dient als Hilfsmittel.

## Support

Bei Fragen oder Problemen:
- GitHub Issues: https://github.com/moregeo/nextcloud-timetracking/issues
- E-Mail: support@moregeo.de

## Lizenz

AGPL-3.0-or-later

## Entwickelt von

MoreGeo - https://moregeo.de

---

**Hinweis**: Diese App befindet sich in aktiver Entwicklung. Feedback und Beiträge sind willkommen!
