<template>
    <div class="customers">
        <div class="page-header">
            <h1>{{ t('timetracking', 'Kunden') }}</h1>
            <div class="actions">
                <NcButton v-if="isAdmin" type="button" @click="showAddDialog = true">
                    <template #icon>
                        <Plus :size="20" />
                    </template>
                    {{ t('timetracking', 'Neuer Kunde') }}
                </NcButton>
            </div>
        </div>
        <div v-if="!isAdmin" class="info-message">
            {{ t('timetracking', 'Nur Administratoren können Kunden verwalten') }}
        </div>
        
        <div v-if="loading" class="loading">{{ t('timetracking', 'Laden...') }}</div>
        
        <table v-else-if="customers.length > 0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>{{ t('timetracking', 'Währung') }}</th>
                    <th>Status</th>
                    <th v-if="isAdmin">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="customer in customers" :key="customer.id">
                    <td>{{ customer.name }}</td>
                    <td>{{ getCurrencySymbol(customer.currency) }}</td>
                    <td>
                        <span :class="customer.active ? 'status-active' : 'status-inactive'">
                            {{ customer.active ? 'Aktiv' : 'Inaktiv' }}
                        </span>
                    </td>
                    <td v-if="isAdmin" class="actions">
                        <NcButton type="button" @click="editCustomer(customer)">
                            Bearbeiten
                        </NcButton>
                        <NcButton type="button" @click="deleteCustomer(customer.id)">
                            Löschen
                        </NcButton>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <p v-else>Keine Kunden vorhanden</p>
        
        <!-- Add/Edit Dialog -->
        <NcModal v-if="showAddDialog || editingCustomer" 
                 @close="closeDialog"
                 size="normal">
            <div class="modal-content">
                <h2>{{ editingCustomer ? t('timetracking', 'Kunde bearbeiten') : t('timetracking', 'Neuer Kunde') }}</h2>
                <form @submit.prevent="saveCustomer">
                    <div class="form-group">
                        <label>Name *</label>
                        <input v-model="form.name" type="text" required>
                    </div>
                    <div class="form-group">
                        <label>{{ t('timetracking', 'Währung') }} *</label>
                        <select v-model="form.currency" required>
                            <option v-for="curr in currencies" :key="curr.code" :value="curr.code">
                                {{ curr.symbol }} - {{ curr.name }}
                            </option>
                        </select>
                    </div>
                    <div class="form-group" v-if="editingCustomer">
                        <label>
                            <input v-model="form.active" type="checkbox">
                            Aktiv
                        </label>
                    </div>
                    <div class="modal-actions">
                        <NcButton type="button" @click="closeDialog">
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
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import { getCurrentUser } from '@nextcloud/auth'
import { NcButton, NcModal } from '@nextcloud/vue'
import Plus from 'vue-material-design-icons/Plus.vue'

export default {
    name: 'Customers',
    components: {
        NcButton,
        NcModal,
        Plus,
    },
    data() {
        return {
            customers: [],
            loading: true,
            showAddDialog: false,
            editingCustomer: null,
            form: {
                name: '',
                active: true,
                currency: 'EUR',
            },
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
            isAdmin: getCurrentUser()?.isAdmin || false,
        }
    },
    mounted() {
        this.loadCustomers()
    },
    methods: {
        t,
        async loadCustomers() {
            try {
                this.loading = true
                const response = await axios.get(generateUrl('/apps/timetracking/api/customers'))
                this.customers = response.data
            } catch (error) {
                showError('Fehler beim Laden der Kunden')
                console.error(error)
            } finally {
                this.loading = false
            }
        },
        editCustomer(customer) {
            this.editingCustomer = customer
            this.form = {
                name: customer.name,
                active: customer.active,
                currency: customer.currency || 'EUR',
            }
        },
        async saveCustomer() {
            try {
                if (this.editingCustomer) {
                    await axios.put(
                        generateUrl('/apps/timetracking/api/customers/' + this.editingCustomer.id),
                        this.form
                    )
                    showSuccess('Kunde aktualisiert')
                } else {
                    await axios.post(
                        generateUrl('/apps/timetracking/api/customers'),
                        this.form
                    )
                    showSuccess('Kunde erstellt')
                }
                this.closeDialog()
                this.loadCustomers()
            } catch (error) {
                const errorMsg = error.response?.data?.error || error.response?.data?.message || error.message || 'Unbekannter Fehler'
                showError('Fehler beim Speichern: ' + errorMsg)
                console.error('Save customer error:', error.response?.data || error)
            }
        },
        async deleteCustomer(id) {
            if (!confirm('Möchten Sie diesen Kunden wirklich löschen?')) {
                return
            }
            
            try {
                await axios.delete(generateUrl('/apps/timetracking/api/customers/' + id))
                showSuccess('Kunde gelöscht')
                this.loadCustomers()
            } catch (error) {
                showError('Fehler beim Löschen')
                console.error(error)
            }
        },
        closeDialog() {
            this.showAddDialog = false
            this.editingCustomer = null
            this.form = {
                name: '',
                active: true,
                currency: 'EUR',
            }
        },
        getCurrencySymbol(code) {
            const currency = this.currencies.find(c => c.code === code)
            return currency ? currency.symbol : code
        },
    },
}
</script>

<style scoped>
/* Component-specific styles only - common styles are in App.vue */
.customers {
    max-width: 1200px;
}
</style>
