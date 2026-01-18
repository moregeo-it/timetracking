<template>
    <div class="projects">
        <div class="page-header">
            <h1>{{ t('timetracking', 'Projekte') }}</h1>
            <div class="actions">
                <NcButton v-if="isAdmin" type="primary" @click="showAddDialog = true">
                    <template #icon>
                        <Plus :size="20" />
                    </template>
                    {{ t('timetracking', 'Neues Projekt') }}
                </NcButton>
            </div>
        </div>
        <div v-if="!isAdmin" class="info-message">
            {{ t('timetracking', 'Nur Administratoren können Projekte verwalten') }}
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
                        <NcButton type="tertiary" @click="editProject(project)" :title="t('timetracking', 'Bearbeiten')">
                            <template #icon>
                                <Pencil :size="20" />
                            </template>
                        </NcButton>
                        <NcButton type="tertiary" @click="deleteProject(project.id)" :title="t('timetracking', 'Löschen')">
                            <template #icon>
                                <Delete :size="20" />
                            </template>
                        </NcButton>
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
                        <NcButton type="tertiary" native-type="button" @click="closeDialog">{{ t('timetracking', 'Abbrechen') }}</NcButton>
                        <NcButton type="primary" native-type="submit">{{ t('timetracking', 'Speichern') }}</NcButton>
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
import { NcButton } from '@nextcloud/vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import Delete from 'vue-material-design-icons/Delete.vue'

export default {
    name: 'Projects',
    components: {
        NcButton,
        Plus,
        Pencil,
        Delete,
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
/* Component-specific styles only - common styles are in App.vue */
.projects {
    max-width: 1200px;
}
</style>
