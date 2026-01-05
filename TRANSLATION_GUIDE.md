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

### Files Modified

#### Frontend (Vue.js)
All Vue components have been updated to use the `t()` translation function from `@nextcloud/l10n`:

1. **src/App.vue** - Main navigation menu
2. **src/views/Dashboard.vue** - Dashboard view with statistics
3. **src/views/TimeTracking.vue** - Time entry and timer functionality
4. **src/views/Customers.vue** - Customer management
5. **src/views/Projects.vue** - Project management
6. **src/views/Reports.vue** - Reporting functionality
7. **src/views/Vacations.vue** - Vacation/holiday management
8. **src/views/EmployeeSettings.vue** - Employee work settings

#### Translation Files
1. **l10n/de.json** - Complete German translation (default)
2. **l10n/en.json** - Complete English translation

### Translation Keys

Total translation keys: ~140

Categories:
- Navigation (7 keys)
- Dashboard (15 keys)
- Time Tracking (20 keys)
- Customers (15 keys)
- Projects (18 keys)
- Reports (25 keys)
- Vacations (20 keys)
- Employee Settings (20 keys)

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

### Testing Translations

1. Change your Nextcloud language in Settings → Personal → Language
2. Refresh the Time Tracker app
3. Verify all text displays in the selected language
4. Test all features to ensure translations appear correctly

### Key Features Translated

- ✅ Main navigation menu
- ✅ Dashboard statistics and cards
- ✅ Time entry forms and timer
- ✅ Customer CRUD operations
- ✅ Project management
- ✅ Reporting (Customer, Project, Employee, Compliance)
- ✅ Vacation management
- ✅ Employee settings
- ✅ Error messages
- ✅ Success notifications
- ✅ Confirmation dialogs
- ✅ Form labels and placeholders
- ✅ Status labels
- ✅ Table headers

### Backend Considerations

The PHP backend uses Nextcloud's built-in localization for:
- Date formatting
- Number formatting
- Time zones

The API returns data in a locale-neutral format (ISO dates, numeric values), and the frontend handles localization for display.

### Maintenance

When adding new features:

1. Use translation keys for all user-facing text
2. Add new keys to both `de.json` and `en.json`
3. Follow the existing naming convention (German text as key)
4. Test in both languages before deploying

### Known Limitations

- Month names in reports use hardcoded German names in some methods
  - These should be replaced with `Intl.DateTimeFormat` for proper localization
- Date formatting currently uses 'de-DE' locale
  - Should be updated to use the user's locale preference

### Future Improvements

1. Add more language support (French, Spanish, Italian, etc.)
2. Implement dynamic date/time formatting based on user locale
3. Add language selector within the app (optional)
4. Externalize compliance-related terminology for different regions
5. Support for RTL (right-to-left) languages

## Verification Checklist

- [x] All Vue components use `t()` function
- [x] Translation files created for German and English
- [x] Navigation menu translated
- [x] All views translated
- [x] Form labels and placeholders translated
- [x] Error and success messages translated
- [x] Status labels translated
- [x] Table headers translated
- [x] Button text translated
- [x] Dialog content translated

## Support

For translation issues or to contribute new translations, please contact the development team or submit a pull request to the repository.
