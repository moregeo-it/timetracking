<template>
    <div class="public-holidays">
        <div class="page-header">
            <h1>{{ t('timetracking', 'Feiertage') }}</h1>
        </div>
        
        <!-- Info for non-admins -->
        <div v-if="!isAdmin" class="info-box">
            <p>{{ t('timetracking', 'Hier werden alle Feiertage angezeigt, die bei der Zeiterfassung berücksichtigt werden.') }}</p>
        </div>
        
        <!-- Year Selection & Import -->
        <div class="toolbar">
            <div class="form-group">
                <label>{{ t('timetracking', 'Jahr') }}</label>
                <select v-model="selectedYear" @change="loadHolidays">
                    <option v-for="year in availableYears" :key="year" :value="year">{{ year }}</option>
                </select>
            </div>
            
            <div v-if="isAdmin" class="actions">
                <NcButton type="button" @click="showModal = true">
                    <template #icon>
                        <Plus :size="20" />
                    </template>
                    {{ t('timetracking', 'Feiertag hinzufügen') }}
                </NcButton>
                <NcButton type="secondary" @click="importGermanHolidays">
                    {{ t('timetracking', 'Feiertage NRW importieren') }}
                </NcButton>
            </div>
        </div>
        
        <!-- Holiday List -->
        <div class="holiday-list">
            <div v-if="loading" class="loading">
                {{ t('timetracking', 'Laden...') }}
            </div>
            
            <div v-else-if="holidays.length === 0" class="empty-state">
                <p>{{ t('timetracking', 'Keine Feiertage vorhanden') }}</p>
                <p v-if="isAdmin" class="hint">
                    {{ t('timetracking', 'Klicken Sie auf "Feiertage NRW importieren" um die Feiertage für Nordrhein-Westfalen zu laden.') }}
                </p>
            </div>
            
            <table v-else class="holiday-table">
                <thead>
                    <tr>
                        <th>{{ t('timetracking', 'Datum') }}</th>
                        <th>{{ t('timetracking', 'Name') }}</th>
                        <th v-if="isAdmin">{{ t('timetracking', 'Aktionen') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="holiday in sortedHolidays" :key="holiday.id">
                        <td>
                            <strong>{{ formatDate(holiday.date) }}</strong>
                            <span class="weekday">({{ getWeekday(holiday.date) }})</span>
                        </td>
                        <td>{{ holiday.name }}</td>
                        <td v-if="isAdmin" class="actions-cell">
                            <NcButton type="tertiary" @click="editHoliday(holiday)">
                                {{ t('timetracking', 'Bearbeiten') }}
                            </NcButton>
                            <NcButton type="tertiary" @click="deleteHoliday(holiday)">
                                {{ t('timetracking', 'Löschen') }}
                            </NcButton>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Add/Edit Modal -->
        <NcModal v-if="showModal" @close="closeModal">
            <div class="modal-content">
                <h2>{{ editingHoliday ? t('timetracking', 'Feiertag bearbeiten') : t('timetracking', 'Neuer Feiertag') }}</h2>
                
                <form @submit.prevent="saveHoliday">
                    <div class="form-group">
                        <label>{{ t('timetracking', 'Datum') }} *</label>
                        <input v-model="form.date" type="date" required>
                    </div>
                    
                    <div class="form-group">
                        <label>{{ t('timetracking', 'Name') }} *</label>
                        <input v-model="form.name" type="text" required :placeholder="t('timetracking', 'z.B. Weihnachten')">
                    </div>
                    
                    <div class="form-actions">
                        <NcButton type="tertiary" @click="closeModal">
                            {{ t('timetracking', 'Abbrechen') }}
                        </NcButton>
                        <NcButton type="primary" native-type="submit">
                            {{ editingHoliday ? t('timetracking', 'Speichern') : t('timetracking', 'Erstellen') }}
                        </NcButton>
                    </div>
                </form>
            </div>
        </NcModal>
    </div>
</template>

<script>
import { NcButton, NcModal } from '@nextcloud/vue'
import { generateUrl } from '@nextcloud/router'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import Plus from 'vue-material-design-icons/Plus.vue'

export default {
    name: 'PublicHolidays',
    components: {
        NcButton,
        NcModal,
        Plus,
    },
    data() {
        const currentYear = new Date().getFullYear()
        return {
            loading: true,
            isAdmin: getCurrentUser()?.isAdmin || false,
            holidays: [],
            showModal: false,
            editingHoliday: null,
            selectedYear: currentYear,
            form: {
                date: '',
                name: '',
            },
        }
    },
    computed: {
        availableYears() {
            const currentYear = new Date().getFullYear()
            return [currentYear - 1, currentYear, currentYear + 1, currentYear + 2]
        },
        sortedHolidays() {
            return [...this.holidays].sort((a, b) => {
                return a.date.localeCompare(b.date)
            })
        },
    },
    mounted() {
        this.loadHolidays()
    },
    methods: {
        t,
        async loadHolidays() {
            this.loading = true
            try {
                const response = await axios.get(
                    generateUrl(`/apps/timetracking/api/public-holidays?year=${this.selectedYear}`)
                )
                this.holidays = response.data
            } catch (error) {
                console.error('Error loading holidays:', error)
                showError(t('timetracking', 'Fehler beim Laden der Feiertage'))
            } finally {
                this.loading = false
            }
        },
        formatDate(dateStr) {
            if (!dateStr) return ''
            const date = new Date(dateStr)
            return date.toLocaleDateString(undefined, {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
            })
        },
        getWeekday(dateStr) {
            if (!dateStr) return ''
            const date = new Date(dateStr)
            return date.toLocaleDateString(undefined, { weekday: 'short' })
        },
        editHoliday(holiday) {
            this.editingHoliday = holiday
            this.form = {
                date: holiday.date,
                name: holiday.name,
            }
            this.showModal = true
        },
        closeModal() {
            this.showModal = false
            this.editingHoliday = null
            this.form = {
                date: '',
                name: '',
            }
        },
        async saveHoliday() {
            try {
                if (this.editingHoliday) {
                    await axios.put(
                        generateUrl(`/apps/timetracking/api/public-holidays/${this.editingHoliday.id}`),
                        this.form
                    )
                    showSuccess(t('timetracking', 'Feiertag aktualisiert'))
                } else {
                    await axios.post(
                        generateUrl('/apps/timetracking/api/public-holidays'),
                        this.form
                    )
                    showSuccess(t('timetracking', 'Feiertag erstellt'))
                }
                this.closeModal()
                this.loadHolidays()
            } catch (error) {
                console.error('Error saving holiday:', error)
                if (error.response?.status === 403) {
                    showError(t('timetracking', 'Nur Administratoren können Feiertage verwalten'))
                } else {
                    showError(t('timetracking', 'Fehler beim Speichern'))
                }
            }
        },
        async deleteHoliday(holiday) {
            if (!confirm(t('timetracking', 'Möchten Sie den Feiertag "{name}" wirklich löschen?', { name: holiday.name }))) {
                return
            }
            
            try {
                await axios.delete(generateUrl(`/apps/timetracking/api/public-holidays/${holiday.id}`))
                showSuccess(t('timetracking', 'Feiertag gelöscht'))
                this.loadHolidays()
            } catch (error) {
                console.error('Error deleting holiday:', error)
                showError(t('timetracking', 'Fehler beim Löschen'))
            }
        },
        async importGermanHolidays() {
            try {
                const response = await axios.post(
                    generateUrl(`/apps/timetracking/api/public-holidays/import-german/${this.selectedYear}`)
                )
                showSuccess(t('timetracking', '{imported} Feiertage importiert, {skipped} übersprungen', {
                    imported: response.data.imported,
                    skipped: response.data.skipped,
                }))
                this.loadHolidays()
            } catch (error) {
                console.error('Error importing holidays:', error)
                if (error.response?.status === 403) {
                    showError(t('timetracking', 'Nur Administratoren können Feiertage importieren'))
                } else {
                    showError(t('timetracking', 'Fehler beim Importieren'))
                }
            }
        },
    },
}
</script>

<style scoped>
.public-holidays {
    max-width: 900px;
}

.toolbar {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    gap: 20px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.toolbar .form-group {
    margin-bottom: 0;
}

.toolbar .actions {
    display: flex;
    gap: 10px;
}

.holiday-list {
    background: var(--color-main-background);
    border-radius: 8px;
    border: 1px solid var(--color-border);
    overflow: hidden;
}

.holiday-table {
    margin-top: 0;
    width: 100%;
    border-collapse: collapse;
}

.holiday-table th,
.holiday-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid var(--color-border);
}

.holiday-table th {
    background: var(--color-background-hover);
    font-weight: 600;
    color: var(--color-main-text);
}

.holiday-table tbody tr:hover {
    background: var(--color-background-hover);
}

.weekday {
    color: var(--color-text-lighter);
    margin-left: 5px;
    font-size: 0.9em;
}

.actions-cell {
    display: flex;
    gap: 5px;
}

.modal-content {
    padding: 20px;
    min-width: 400px;
}

.modal-content h2 {
    margin-top: 0;
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
}

.form-group input[type="text"],
.form-group input[type="date"] {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--color-border);
    border-radius: 4px;
    font-size: 14px;
}

.hint {
    color: var(--color-text-lighter);
    font-size: 0.9em;
    margin-top: 5px;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid var(--color-border);
}

.info-box {
    background: var(--color-background-hover);
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    border: 1px solid var(--color-border);
}

.info-box p {
    margin: 0;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--color-text-lighter);
}

.empty-state .hint {
    margin-top: 10px;
}

.loading {
    text-align: center;
    padding: 40px 20px;
    color: var(--color-text-lighter);
}
</style>
