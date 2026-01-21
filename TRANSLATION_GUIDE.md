# Translation Implementation Documentation

## Overview

The Nextcloud Time Tracker app has been fully internationalized to support both German (de) and English (en) languages.

## Implementation Details

### Translation Infrastructure

#### Location

- Translation files are located in: `/l10n/`
- `de.json` - German translations
- `en.json` - English translations

#### Format

Translation files use Nextcloud's standard JSON format:
```json
{
    "translations": {
        "German Key": "English Translation",
        ...
    }
}
```

### Usage

#### In Vue Components

```javascript
import { translate as t } from '@nextcloud/l10n'

export default {
    methods: {
        t,
    }
}
```

Then in template:

```vue
<h1>{{ t('timetracking', 'Dashboard') }}</h1>
```

### Language Selection

Users can select their preferred language through:

1. Nextcloud personal settings (Settings → Personal → Language)
2. Browser language preference (automatic)

The app will automatically display in the user's selected language.

### Adding New Translations

To add a new language:

1. Create a new JSON file in `/l10n/` (e.g., `fr.json` for French)
2. Copy the structure from `en.json` or `de.json`
3. Translate all values to the target language
4. Keep the keys unchanged (they must match the code)

Example:

```json
{
    "translations": {
        "Dashboard": "Tableau de bord",
        "Zeiterfassung": "Suivi du temps",
        ...
    }
}
```
