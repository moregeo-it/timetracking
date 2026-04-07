<template>
    <div class="admin-time-tracking">
        <div class="page-header">
            <h1>{{ t('timetracking', 'Mitarbeiterzeiterfassung') }}</h1>
        </div>

        <!-- Employee Selection -->
        <div class="employee-selection">
            <div class="form-group">
                <label>{{ t('timetracking', 'Mitarbeiter auswählen') }}</label>
                <select v-model="selectedUserId" @change="onEmployeeChange">
                    <option value="">{{ t('timetracking', 'Bitte wählen') }}</option>
                    <option v-for="user in allUsers" :key="user.id" :value="user.id">
                        {{ user.displayName || user.id }}
                    </option>
                </select>
            </div>
        </div>

        <template v-if="selectedUserId">
            <!-- Add Entry Form -->
            <div class="manual-entry-section">
                <h3>{{ t('timetracking', 'Zeiteintrag erfassen') }}</h3>
                <form @submit.prevent="addEntry" class="manual-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label>{{ t('timetracking', 'Projekt') }} *</label>
                            <select v-model="manualForm.projectId" required>
                                <option value="">{{ t('timetracking', 'Bitte wählen') }}</option>
                                <option v-for="project in activeProjects" :key="project.id" :value="project.id">
                                    {{ project.name }} ({{ getCustomerName(project.customerId) }})
                                </option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>{{ t('timetracking', 'Datum') }} *</label>
                            <input v-model="manualForm.date" type="date" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>{{ t('timetracking', 'Start') }} *</label>
                            <input v-model="manualForm.startTime" type="time" required>
                        </div>
                        <div class="form-group">
                            <label>{{ t('timetracking', 'Ende') }} *</label>
                            <input v-model="manualForm.endTime" type="time" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>{{ t('timetracking', 'Beschreibung') }}</label>
                        <input v-model="manualForm.description" type="text">
                    </div>
                    <div class="form-group">
                        <label>
                            <input :checked="manualForm.billable" type="checkbox" style="margin: 0 0.5rem 0 0" @change="onManualBillableChange($event)">
                            {{ t('timetracking', 'Abrechenbar') }}
                        </label>
                    </div>
                    <NcButton type="submit">{{ t('timetracking', 'Eintrag Hinzufügen') }}</NcButton>
                </form>
            </div>

            <!-- Entry List (all entries for selected employee, admin can edit any) -->
            <TimeEntryList
                :entries="entries"
                :projects="projects"
                :customers="customers"
                :can-edit-all="true"
                :loading="loading"
                :save-fn="saveEntry"
                :delete-fn="deleteEntry"
                @filter-change="onFilterChange"
            />
        </template>

        <div v-else class="empty-state">
            <p>{{ t('timetracking', 'Bitte wählen Sie einen Mitarbeiter aus.') }}</p>
        </div>
    </div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import { NcButton } from '@nextcloud/vue'
import TimeEntryList from '../components/TimeEntryList.vue'

export default {
    name: 'AdminTimeTracking',
    components: {
        NcButton,
        TimeEntryList,
    },
    data() {
        const today = new Date().toISOString().split('T')[0]
        const weekAgo = new Date()
        weekAgo.setDate(weekAgo.getDate() - 7)

        return {
            allUsers: [],
            selectedUserId: '',
            projects: [],
            customers: [],
            entries: [],
            loading: false,
            filterStartDate: weekAgo.toISOString().split('T')[0],
            filterEndDate: today,
            manualForm: {
                projectId: '',
                date: today,
                startTime: '09:00',
                endTime: '',
                description: '',
                billable: true,
            },
        }
    },
    computed: {
        activeProjects() {
            return [...this.projects]
                .filter(p => p.active)
                .sort((a, b) => a.name.localeCompare(b.name, undefined, { sensitivity: 'base' }))
        },
    },
    mounted() {
        this.loadUsers()
        this.loadProjects()
        this.loadCustomers()
    },
    methods: {
        t,
        async loadUsers() {
            try {
                const response = await axios.get(generateUrl('/apps/timetracking/api/admin/users'))
                this.allUsers = response.data
            } catch (error) {
                showError(t('timetracking', 'Fehler beim Laden der Mitarbeiter'))
                console.error(error)
            }
        },
        async loadProjects() {
            try {
                const response = await axios.get(generateUrl('/apps/timetracking/api/projects'))
                this.projects = response.data
            } catch (error) {
                console.error(error)
            }
        },
        async loadCustomers() {
            try {
                const response = await axios.get(generateUrl('/apps/timetracking/api/customers'))
                this.customers = response.data
            } catch (error) {
                console.error(error)
            }
        },
        async loadEntries() {
            if (!this.selectedUserId) return
            this.loading = true
            try {
                const startOfDay = new Date(this.filterStartDate + 'T00:00:00')
                const endOfDay = new Date(this.filterEndDate + 'T23:59:59')
                const url = `/apps/timetracking/api/admin/time-entries?userId=${encodeURIComponent(this.selectedUserId)}&startDate=${startOfDay.toISOString()}&endDate=${endOfDay.toISOString()}`
                const response = await axios.get(generateUrl(url))
                this.entries = response.data
            } catch (error) {
                showError(t('timetracking', 'Fehler beim Laden der Einträge'))
                console.error(error)
            } finally {
                this.loading = false
            }
        },
        onEmployeeChange() {
            this.entries = []
            this.loadEntries()
        },
        onFilterChange({ startDate, endDate }) {
            this.filterStartDate = startDate
            this.filterEndDate = endDate
            this.loadEntries()
        },
        async addEntry() {
            try {
                const [year, month, day] = this.manualForm.date.split('-').map(Number)
                const [startHours, startMinutes] = this.manualForm.startTime.split(':').map(Number)
                const [endHours, endMinutes] = this.manualForm.endTime.split(':').map(Number)
                const startDateTime = new Date(year, month - 1, day, startHours, startMinutes, 0)
                const endDateTime = new Date(year, month - 1, day, endHours, endMinutes, 0)

                await axios.post(generateUrl('/apps/timetracking/api/admin/time-entries'), {
                    userId: this.selectedUserId,
                    projectId: this.manualForm.projectId,
                    startTime: startDateTime.toISOString(),
                    endTime: endDateTime.toISOString(),
                    description: this.manualForm.description,
                    billable: this.manualForm.billable,
                })

                showSuccess(t('timetracking', 'Eintrag hinzugefügt'))
                this.manualForm.description = ''
                this.loadEntries()
            } catch (error) {
                if (error.response?.status === 409) {
                    showError(t('timetracking', 'Zeiteintrag überschneidet sich mit einem bestehenden Eintrag'))
                } else {
                    showError(t('timetracking', 'Fehler beim Hinzufügen'))
                }
                console.error(error)
            }
        },
        async saveEntry({ id, form }) {
            try {
                const [year, month, day] = form.date.split('-').map(Number)
                const [startHours, startMinutes] = form.startTime.split(':').map(Number)
                const [endHours, endMinutes] = form.endTime.split(':').map(Number)
                const startDateTime = new Date(year, month - 1, day, startHours, startMinutes, 0)
                const endDateTime = new Date(year, month - 1, day, endHours, endMinutes, 0)

                await axios.put(generateUrl(`/apps/timetracking/api/admin/time-entries/${id}`), {
                    projectId: form.projectId,
                    startTime: startDateTime.toISOString(),
                    endTime: endDateTime.toISOString(),
                    description: form.description,
                    billable: form.billable,
                })
                showSuccess(t('timetracking', 'Eintrag aktualisiert'))
                this.loadEntries()
            } catch (error) {
                if (error.response?.status === 409) {
                    showError(t('timetracking', 'Zeiteintrag überschneidet sich mit einem bestehenden Eintrag'))
                } else {
                    showError(t('timetracking', 'Fehler beim Speichern'))
                }
                console.error(error)
                throw error
            }
        },
        async deleteEntry(id) {
            try {
                await axios.delete(generateUrl(`/apps/timetracking/api/admin/time-entries/${id}`))
                showSuccess(t('timetracking', 'Eintrag gelöscht'))
                this.loadEntries()
            } catch (error) {
                showError(t('timetracking', 'Fehler beim Löschen'))
                console.error(error)
                throw error
            }
        },
        getCustomerName(customerId) {
            const customer = this.customers.find(c => c.id === customerId)
            return customer ? customer.name : t('timetracking', 'Unbekannt')
        },
        onManualBillableChange(event) {
            if (!event.target.checked) {
                if (!confirm(t('timetracking', 'Sind Sie sicher, dass dieser Eintrag nicht abrechenbar sein soll?'))) {
                    event.target.checked = true
                    return
                }
            }
            this.manualForm.billable = event.target.checked
        },
    },
}
</script>

<style scoped>
.admin-time-tracking {
    max-width: 1200px;
}

.employee-selection {
    background: var(--color-background-dark);
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 24px;
    max-width: 400px;
}

.manual-entry-section {
    margin-bottom: 32px;
    padding: 1rem;
    background: var(--color-background-dark);
    border-radius: 8px;
}

.manual-entry-section h3 {
    margin-top: 0;
}

.empty-state {
    text-align: center;
    padding: 3rem;
    color: var(--color-text-maxcontrast);
}
</style>
