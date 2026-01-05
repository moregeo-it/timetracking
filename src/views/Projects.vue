<template>
    <div class="projects">
        <NcAppContentDetails>
            <template #title>
                {{ t('timetracking', 'Projekte') }}
            </template>
        </NcAppContentDetails>
        <div class="header-row">
            <button v-if="isAdmin" @click="showAddDialog = true" class="button primary">
                <span class="icon-add"></span> {{ t('timetracking', 'Neues Projekt') }}
            </button>
            <div v-else class="info-message">
                {{ t('timetracking', 'Nur Administratoren können Projekte verwalten') }}
            </div>
        </div>
        
        <div class="filters">
            <label>
                {{ t('timetracking', 'Kunde filtern') }}:
                <select v-model="filterCustomerId" @change="loadProjects">
                    <option value="">{{ t('timetracking', 'Alle Kunden') }}</option>
                    <option v-for="customer in customers" :key="customer.id" :value="customer.id">
                        {{ customer.name }}
                    </option>
                </select>
            </label>
        </div>
        
        <div v-if="loading" class="loading">{{ t('timetracking', 'Laden...') }}</div>
        
        <table v-else-if="projects.length > 0">
            <thead>
                <tr>
                    <th>{{ t('timetracking', 'Name') }}</th>
                    <th>{{ t('timetracking', 'Kunde') }}</th>
                    <th>{{ t('timetracking', 'Stundensatz') }}</th>
                    <th>{{ t('timetracking', 'Budget (Stunden)') }}</th>
                    <th>{{ t('timetracking', 'Status') }}</th>
                    <th v-if="isAdmin">{{ t('timetracking', 'Aktionen') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="project in projects" :key="project.id">
                    <td>{{ project.name }}</td>
                    <td>{{ getCustomerName(project.customerId) }}</td>
                    <td>{{ project.hourlyRate ? project.hourlyRate + ' €' : '-' }}</td>
                    <td>{{ project.budgetHours ? project.budgetHours + ' h' : '-' }}</td>
                    <td>
                        <span :class="project.active ? 'status-active' : 'status-inactive'">
                            {{ project.active ? t('timetracking', 'Aktiv') : t('timetracking', 'Inaktiv') }}
                        </span>
                    </td>
                    <td v-if="isAdmin" class="actions">
                        <button @click="editProject(project)" class="icon-rename" :title="t('timetracking', 'Bearbeiten')"></button>
                        <button @click="deleteProject(project.id)" class="icon-delete" :title="t('timetracking', 'Löschen')"></button>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <p v-else>{{ t('timetracking', 'Keine Projekte vorhanden') }}</p>
        
        <!-- Add/Edit Dialog -->
        <div v-if="showAddDialog || editingProject" class="dialog-overlay" @click.self="closeDialog">
            <div class="dialog">
                <h2>{{ editingProject ? t('timetracking', 'Projekt bearbeiten') : t('timetracking', 'Neues Projekt') }}</h2>
                <form @submit.prevent="saveProject">
                    <div class="form-group">
                        <label>{{ t('timetracking', 'Kunde') }} *</label>
                        <select v-model="form.customerId" required>
                            <option value="">{{ t('timetracking', 'Bitte wählen') }}</option>
                            <option v-for="customer in customers" :key="customer.id" :value="customer.id">
                                {{ customer.name }}
                            </option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>{{ t('timetracking', 'Projektname') }} *</label>
                        <input v-model="form.name" type="text" required>
                    </div>
                    <div class="form-group">
                        <label>{{ t('timetracking', 'Beschreibung') }}</label>
                        <textarea v-model="form.description" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label>{{ t('timetracking', 'Stundensatz (€)') }}</label>
                        <input v-model.number="form.hourlyRate" type="number" step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label>{{ t('timetracking', 'Budget (Stunden)') }}</label>
                        <input v-model.number="form.budgetHours" type="number" step="0.5" min="0">
                    </div>
                    <div class="form-group" v-if="editingProject">
                        <label>
                            <input v-model="form.active" type="checkbox">
                            {{ t('timetracking', 'Aktiv') }}
                        </label>
                    </div>
                    <div class="dialog-actions">
                        <button type="button" @click="closeDialog" class="button">{{ t('timetracking', 'Abbrechen') }}</button>
                        <button type="submit" class="button primary">{{ t('timetracking', 'Speichern') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { getCurrentUser } from '@nextcloud/auth'
import { translate as t } from '@nextcloud/l10n'
import { NcAppContentDetails } from '@nextcloud/vue'

export default {
    name: 'Projects',
    components: {
        NcAppContentDetails,
    },
    data() {
        return {
            projects: [],
            customers: [],
            loading: true,
            showAddDialog: false,
            editingProject: null,
            filterCustomerId: '',
            isAdmin: getCurrentUser()?.isAdmin || false,
            form: {
                customerId: '',
                name: '',
                description: '',
                hourlyRate: null,
                budgetHours: null,
                active: true,
            },
        }
    },
    mounted() {
        this.loadCustomers()
        this.loadProjects()
    },
    methods: {
        async loadCustomers() {
            try {
                const response = await axios.get(generateUrl('/apps/timetracking/api/customers'))
                this.customers = response.data
            } catch (error) {
                console.error(error)
            }
        },
        async loadProjects() {
            try {
                this.loading = true
                let url = '/apps/timetracking/api/projects'
                if (this.filterCustomerId) {
                    url += '?customerId=' + this.filterCustomerId
                }
                const response = await axios.get(generateUrl(url))
                this.projects = response.data
            } catch (error) {
                showError(this.t('timetracking', 'Fehler beim Laden der Projekte'))
                console.error(error)
            } finally {
                this.loading = false
            }
        },
        getCustomerName(customerId) {
            const customer = this.customers.find(c => c.id === customerId)
            return customer ? customer.name : this.t('timetracking', 'Unbekannt')
        },
        t,
        editProject(project) {
            this.editingProject = project
            this.form = {
                customerId: project.customerId,
                name: project.name,
                description: project.description || '',
                hourlyRate: project.hourlyRate,
                budgetHours: project.budgetHours,
                active: project.active,
            }
        },
        async saveProject() {
            try {
                if (this.editingProject) {
                    await axios.put(
                        generateUrl('/apps/timetracking/api/projects/' + this.editingProject.id),
                        this.form
                    )
                    showSuccess(this.t('timetracking', 'Projekt aktualisiert'))
                } else {
                    await axios.post(
                        generateUrl('/apps/timetracking/api/projects'),
                        this.form
                    )
                    showSuccess(this.t('timetracking', 'Projekt erstellt'))
                }
                this.closeDialog()
                this.loadProjects()
            } catch (error) {
                showError(this.t('timetracking', 'Fehler beim Speichern'))
                console.error(error)
            }
        },
        async deleteProject(id) {
            if (!confirm(this.t('timetracking', 'Möchten Sie dieses Projekt wirklich löschen?'))) {
                return
            }
            
            try {
                await axios.delete(generateUrl('/apps/timetracking/api/projects/' + id))
                showSuccess(this.t('timetracking', 'Projekt gelöscht'))
                this.loadProjects()
            } catch (error) {
                showError(this.t('timetracking', 'Fehler beim Löschen'))
                console.error(error)
            }
        },
        closeDialog() {
            this.showAddDialog = false
            this.editingProject = null
            this.form = {
                customerId: '',
                name: '',
                description: '',
                hourlyRate: null,
                budgetHours: null,
                active: true,
            }
        },
    },
}
</script>

<style scoped>
.header-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.info-message {
    padding: 8px 16px;
    background-color: var(--color-info);
    color: var(--color-info-text);
    border-radius: 4px;
    font-size: 14px;
}

.filters {
    margin-bottom: 20px;
}

.filters select {
    margin-left: 10px;
    padding: 8px;
    border: 1px solid var(--color-border);
    border-radius: 4px;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid var(--color-border);
}

th {
    font-weight: bold;
    background-color: var(--color-background-dark);
}

.actions button {
    background: none;
    border: none;
    cursor: pointer;
    padding: 8px;
    opacity: 0.7;
}

.actions button:hover {
    opacity: 1;
}

.status-active {
    color: var(--color-success);
    font-weight: bold;
}

.status-inactive {
    color: var(--color-error);
}

.dialog-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
}

.dialog {
    background: var(--color-main-background);
    border-radius: 8px;
    padding: 20px;
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
}

.form-group input[type="text"],
.form-group input[type="number"],
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 8px;
    border: 1px solid var(--color-border);
    border-radius: 4px;
}

.dialog-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
}

.button {
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    background-color: var(--color-background-dark);
}

.button.primary {
    background-color: var(--color-primary);
    color: white;
}

.button:hover {
    opacity: 0.8;
}
</style>
