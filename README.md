# Time Tracker für Nextcloud

Ein professionelles Zeiterfassungssystem für Nextcloud mit deutscher Arbeitszeitgesetz-Prüfung

## Features

### Kernfunktionen

- **Kunden-Verwaltung**: Kunden mit vollständigen Kontaktdaten anlegen und verwalten
- **Projekt-Management**: Projekte Kunden zuordnen, Stundensätze und Budgets definieren
- **Zeiterfassung**: 
  - Live-Timer zum Starten/Stoppen der Zeiterfassung
  - Manuelle Zeiteinträge für nachträgliche Erfassung
  - Beschreibungen und Abrechenbarkeit pro Eintrag
- **Zweisprachig** (Deutsch/Englisch)

### Reporting

- **Kunden-Berichte**: Monatliche Übersichten pro Kunde mit allen Projekten
- **Projekt-Berichte**: Detaillierte Auswertung pro Projekt inkl. Mitarbeiter-Aufschlüsselung
- **Mitarbeiter-Berichte**: Persönliche Arbeitszeitübersichten mit täglicher Aufstellung
- **Arbeitszeitgesetz-Prüfung**: Automatische Compliance-Checks nach deutschem Recht

### Deutsche Arbeitsrecht-Konformität

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

## Rechtliche Hinweise

### Deutsches Arbeitszeitgesetz (ArbZG)

Diese App unterstützt bei der Einhaltung des deutschen Arbeitszeitgesetzes, ersetzt jedoch nicht die rechtliche Beratung. Die automatischen Prüfungen basieren auf folgenden Regelungen:

- **§3 ArbZG**: Tägliche Arbeitszeit
- **§9 ArbZG**: Sonn- und Feiertagsruhe
- **§5 ArbZG**: Ruhepausen
- **§11 ArbZG**: Aufzeichnungspflicht

**Wichtig**: Die Verantwortung für die Einhaltung liegt beim Arbeitgeber. Diese Software dient als Hilfsmittel.
