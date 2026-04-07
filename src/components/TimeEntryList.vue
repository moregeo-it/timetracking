<template>
    <div class="entries-section">
        <h2>{{ t('timetracking', 'Letzte Einträge') }}</h2>
        <div class="date-filter">
            <label>
                {{ t('timetracking', 'Von') }}:
                <input v-model="filterStartDate" type="date" @change="emitFilterChange" size="12">
            </label>
            <label>
                {{ t('timetracking', 'Bis') }}:
                <input v-model="filterEndDate" type="date" @change="emitFilterChange" size="12">
            </label>
        </div>

        <div v-if="loading" class="loading">
            {{ t('timetracking', 'Laden...') }}
        </div>

        <table v-else-if="entries.length > 0">
            <thead>
                <tr>
                    <th>{{ t('timetracking', 'Datum') }}</th>
                    <th>{{ t('timetracking', 'Projekt') }}</th>
                    <th>{{ t('timetracking', 'Start') }}</th>
                    <th>{{ t('timetracking', 'Ende') }}</th>
                    <th>{{ t('timetracking', 'Dauer (Std.)') }}</th>
                    <th>{{ t('timetracking', 'Beschreibung') }}</th>
                    <th>{{ t('timetracking', 'Aktionen') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="entry in entries" :key="entry.id" :class="{ 'past-month': !canEdit(entry) }">
                    <td>{{ formatDate(entry.startTime) }}</td>
                    <td>{{ getProjectName(entry.projectId) }}</td>
                    <td>{{ formatTime(entry.startTime) }}</td>
                    <td>{{ formatTime(entry.endTime) }}</td>
                    <td>{{ formatDuration(entry.durationMinutes) }}</td>
                    <td>{{ entry.description || '-' }}</td>
                    <td class="actions">
                        <NcButton
                            type="button"
                            @click="openEditModal(entry)"
                            :title="canEdit(entry) ? t('timetracking', 'Bearbeiten') : t('timetracking', 'Einträge aus vergangenen Monaten können nicht bearbeitet werden')"
                            :disabled="!canEdit(entry)">
                            <template #icon>
                                <Pencil :size="20" />
                            </template>
                        </NcButton>
                        <NcButton
                            type="button"
                            @click="handleDelete(entry.id)"
                            :title="canEdit(entry) ? t('timetracking', 'Löschen') : t('timetracking', 'Einträge aus vergangenen Monaten können nicht gelöscht werden')"
                            :disabled="!canEdit(entry)">
                            <template #icon>
                                <Delete :size="20" />
                            </template>
                        </NcButton>
                    </td>
                </tr>
            </tbody>
        </table>
        <p v-else>{{ t('timetracking', 'Keine Einträge vorhanden') }}</p>

        <!-- Edit Entry Modal -->
        <NcModal v-if="showEditModal" @close="closeEditModal" size="normal">
            <div class="modal-content">
                <h2>{{ t('timetracking', 'Eintrag bearbeiten') }}</h2>

                <form @submit.prevent="handleSave">
                    <div class="form-group">
                        <label>{{ t('timetracking', 'Projekt') }} *</label>
                        <select v-model="editForm.projectId" required>
                            <option value="">{{ t('timetracking', 'Bitte wählen') }}</option>
                            <option v-for="project in sortedProjects" :key="project.id" :value="project.id">
                                {{ project.name }} ({{ getCustomerName(project.customerId) }})
                            </option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>{{ t('timetracking', 'Datum') }} *</label>
                        <input v-model="editForm.date" type="date" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>{{ t('timetracking', 'Start') }} *</label>
                            <input v-model="editForm.startTime" type="time" required>
                        </div>
                        <div class="form-group">
                            <label>{{ t('timetracking', 'Ende') }} *</label>
                            <input v-model="editForm.endTime" type="time" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>{{ t('timetracking', 'Beschreibung') }}{{ isDescriptionRequired(editForm.projectId) ? ' *' : '' }}</label>
                        <input v-model="editForm.description" type="text" :required="isDescriptionRequired(editForm.projectId)">
                    </div>

                    <div class="form-group">
                        <label>
                            <input :checked="editForm.billable" type="checkbox" style="margin: 0 0.5rem 0 0" @change="onEditBillableChange($event)">
                            {{ t('timetracking', 'Abrechenbar') }}
                        </label>
                    </div>

                    <div class="modal-actions">
                        <NcButton type="button" @click="closeEditModal">
                            {{ t('timetracking', 'Abbrechen') }}
                        </NcButton>
                        <NcButton type="submit">
                            {{ t('timetracking', 'Speichern') }}
                        </NcButton>
                    </div>
                </form>
            </div>
        </NcModal>
    </div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcButton, NcModal } from '@nextcloud/vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'

export default {
    name: 'TimeEntryList',
    components: {
        NcButton,
        NcModal,
        Delete,
        Pencil,
    },
    props: {
        entries: {
            type: Array,
            default: () => [],
        },
        projects: {
            type: Array,
            default: () => [],
        },
        customers: {
            type: Array,
            default: () => [],
        },
        /** When true, entries from past months can also be edited/deleted */
        canEditAll: {
            type: Boolean,
            default: false,
        },
        loading: {
            type: Boolean,
            default: false,
        },
        /**
         * Async function({ id, form }) to persist an edited entry.
         * Must throw on failure (after showing an error message) so the modal stays open.
         */
        saveFn: {
            type: Function,
            required: true,
        },
        /**
         * Async function(id) to delete an entry.
         * Must throw on failure (after showing an error message).
         */
        deleteFn: {
            type: Function,
            required: true,
        },
    },
    emits: ['filter-change'],
    data() {
        const today = new Date().toISOString().split('T')[0]
        const weekAgo = new Date()
        weekAgo.setDate(weekAgo.getDate() - 7)

        return {
            filterStartDate: weekAgo.toISOString().split('T')[0],
            filterEndDate: today,
            showEditModal: false,
            editingEntry: null,
            editForm: {
                projectId: '',
                date: '',
                startTime: '',
                endTime: '',
                description: '',
                billable: true,
            },
        }
    },
    computed: {
        sortedProjects() {
            return [...this.projects]
                .sort((a, b) => a.name.localeCompare(b.name, undefined, { sensitivity: 'base' }))
        },
    },
    methods: {
        t,
        emitFilterChange() {
            this.$emit('filter-change', {
                startDate: this.filterStartDate,
                endDate: this.filterEndDate,
            })
        },
        canEdit(entry) {
            if (this.canEditAll) return true
            if (!entry.startTime) return false
            const entryDate = new Date(entry.startTime)
            const now = new Date()
            return entryDate.getFullYear() === now.getFullYear() &&
                   entryDate.getMonth() === now.getMonth()
        },
        openEditModal(entry) {
            this.editingEntry = entry
            const startDate = new Date(entry.startTime)
            const endDate = entry.endTime ? new Date(entry.endTime) : null

            this.editForm.projectId = entry.projectId || ''
            const year = startDate.getFullYear()
            const month = String(startDate.getMonth() + 1).padStart(2, '0')
            const day = String(startDate.getDate()).padStart(2, '0')
            this.editForm.date = `${year}-${month}-${day}`
            this.editForm.startTime = startDate.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit', hour12: false })
            this.editForm.endTime = endDate
                ? endDate.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit', hour12: false })
                : ''
            this.editForm.description = entry.description || ''
            this.editForm.billable = entry.billable ?? true

            this.showEditModal = true
        },
        closeEditModal() {
            this.showEditModal = false
            this.editingEntry = null
        },
        async handleSave() {
            try {
                await this.saveFn({
                    id: this.editingEntry.id,
                    form: { ...this.editForm },
                })
                this.closeEditModal()
            } catch {
                // saveFn already showed an error message — keep modal open for correction
            }
        },
        async handleDelete(id) {
            if (!confirm(t('timetracking', 'Möchten Sie diesen Eintrag wirklich löschen?'))) {
                return
            }
            try {
                await this.deleteFn(id)
            } catch {
                // deleteFn already showed an error message
            }
        },
        getProjectName(projectId) {
            const project = this.projects.find(p => p.id === projectId)
            return project ? project.name : t('timetracking', 'Unbekannt')
        },
        getCustomerName(customerId) {
            const customer = this.customers.find(c => c.id === customerId)
            return customer ? customer.name : t('timetracking', 'Unbekannt')
        },
        formatDate(isoDateTimeStr) {
            if (!isoDateTimeStr) return '-'
            return new Date(isoDateTimeStr).toLocaleDateString(undefined)
        },
        formatTime(isoDateTimeStr) {
            if (!isoDateTimeStr) return '-'
            return new Date(isoDateTimeStr).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })
        },
        formatDuration(minutes) {
            if (!minutes) return '-'
            const hours = Math.floor(minutes / 60)
            const mins = minutes % 60
            return `${hours}:${mins.toString().padStart(2, '0')}`
        },
        isDescriptionRequired(projectId) {
            if (!projectId) return false
            const project = this.projects.find(p => p.id === parseInt(projectId))
            return project ? !!project.requireDescription : false
        },
        onEditBillableChange(event) {
            if (!event.target.checked) {
                if (!confirm(t('timetracking', 'Sind Sie sicher, dass dieser Eintrag nicht abrechenbar sein soll?'))) {
                    event.target.checked = true
                    return
                }
            }
            this.editForm.billable = event.target.checked
        },
    },
}
</script>

<style scoped>
.entries-section h2 {
    margin-bottom: 16px;
}

.past-month {
    opacity: 0.6;
    background-color: var(--color-background-dark);
}

.past-month td {
    color: var(--color-text-maxcontrast);
}

.actions {
    white-space: nowrap;
}

.actions :deep(button) {
    display: inline-block;
    margin-right: 4px;
}

.actions :deep(button:last-child) {
    margin-right: 0;
}

.actions :deep(button:disabled) {
    opacity: 0.4;
    cursor: not-allowed;
}

.modal-content {
    padding: 20px;
}

.modal-content h2 {
    margin-top: 0;
    margin-bottom: 20px;
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
}
</style>
