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
                    <th v-if="isAdmin">{{ t('timetracking', 'Stundensatz') }}</th>
                    <th>{{ t('timetracking', 'Budget (Stunden)') }}</th>
                    <th>{{ t('timetracking', 'Status') }}</th>
                    <th v-if="isAdmin">{{ t('timetracking', 'Aktionen') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="project in sortedProjects" :key="project.id">
                    <td>{{ project.name }}</td>
                    <td>{{ getCustomerName(project.customerId) }}</td>
                    <td v-if="isAdmin">{{ project.hourlyRate !== null && project.hourlyRate !== undefined ? project.hourlyRate + ' ' + getCustomerCurrency(project.customerId) : '-' }}</td>
                    <td>{{ project.budgetHours !== null && project.budgetHours !== undefined ? project.budgetHours + ' h' : '-' }}</td>
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
                        <label>{{ t('timetracking', 'Stundensatz') }} ({{ getSelectedCustomerCurrency() }})</label>
                        <input v-model="form.hourlyRate" type="number" step="0.01" min="0" placeholder="-">
                    </div>
                    <div class="form-group">
                        <label>{{ t('timetracking', 'Budget (Stunden)') }}</label>
                        <input v-model="form.budgetHours" type="number" step="0.5" min="0" placeholder="-">
                    </div>
                    <div class="form-group" v-if="editingProject">
                        <label>
                            <input v-model="form.active" type="checkbox">
                            {{ t('timetracking', 'Aktiv') }}
                        </label>
                    </div>
                    <div class="dialog-actions">
                        <button type="button" class="btn-secondary" @click="closeDialog">{{ t('timetracking', 'Abbrechen') }}</button>
                        <button type="submit" class="btn-primary">{{ t('timetracking', 'Speichern') }}</button>
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
            currencies: [
                { code: 'EUR', symbol: '€', name: 'Euro' },
                { code: 'USD', symbol: '$', name: 'US Dollar' },
                { code: 'GBP', symbol: '£', name: 'Britisches Pfund' },
                { code: 'CHF', symbol: 'CHF', name: 'Schweizer Franken' },
                { code: 'JPY', symbol: '¥', name: 'Japanischer Yen' },
                { code: 'CAD', symbol: 'C$', name: 'Kanadischer Dollar' },
                { code: 'AUD', symbol: 'A$', name: 'Australischer Dollar' },
                { code: 'SEK', symbol: 'kr', name: 'Schwedische Krone' },
                { code: 'NOK', symbol: 'kr', name: 'Norwegische Krone' },
                { code: 'DKK', symbol: 'kr', name: 'Dänische Krone' },
                { code: 'PLN', symbol: 'zł', name: 'Polnischer Zloty' },
                { code: 'CZK', symbol: 'Kč', name: 'Tschechische Krone' },
            ],
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
    computed: {
        sortedProjects() {
            return [...this.projects].sort((a, b) => {
                // 1. Sort by active status (active first)
                if (a.active !== b.active) {
                    return a.active ? -1 : 1
                }
                // 2. Sort by customer name
                const customerA = this.getCustomerName(a.customerId).toLowerCase()
                const customerB = this.getCustomerName(b.customerId).toLowerCase()
                if (customerA !== customerB) {
                    return customerA.localeCompare(customerB, 'de')
                }
                // 3. Sort by project name alphabetically
                return a.name.toLowerCase().localeCompare(b.name.toLowerCase(), 'de')
            })
        },
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
        getCustomerCurrency(customerId) {
            const customer = this.customers.find(c => c.id === customerId)
            if (customer && customer.currency) {
                const currency = this.currencies.find(c => c.code === customer.currency)
                return currency ? currency.symbol : customer.currency
            }
            return '€'
        },
        getSelectedCustomerCurrency() {
            if (this.form.customerId) {
                return this.getCustomerCurrency(this.form.customerId)
            }
            return '€'
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
                // Convert empty strings to null for optional numeric fields
                const data = {
                    ...this.form,
                    hourlyRate: this.form.hourlyRate === '' || this.form.hourlyRate === null ? null : parseFloat(this.form.hourlyRate),
                    budgetHours: this.form.budgetHours === '' || this.form.budgetHours === null ? null : parseFloat(this.form.budgetHours),
                }
                
                if (this.editingProject) {
                    await axios.put(
                        generateUrl('/apps/timetracking/api/projects/' + this.editingProject.id),
                        data
                    )
                    showSuccess(this.t('timetracking', 'Projekt aktualisiert'))
                } else {
                    await axios.post(
                        generateUrl('/apps/timetracking/api/projects'),
                        data
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
