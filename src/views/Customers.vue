<template>
    <div class="customers">
        <div class="header-row">
            <h2>{{ t('timetracking', 'Kunden') }}</h2>
            <NcButton v-if="isAdmin" type="primary" @click="showAddDialog = true">
                <template #icon>
                    <Plus :size="20" />
                </template>
                {{ t('timetracking', 'Neuer Kunde') }}
            </NcButton>
            <div v-else class="info-message">
                {{ t('timetracking', 'Nur Administratoren können Kunden verwalten') }}
            </div>
        </div>
        
        <div v-if="loading" class="loading">Laden...</div>
        
        <table v-else-if="customers.length > 0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Status</th>
                    <th v-if="isAdmin">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="customer in customers" :key="customer.id">
                    <td>{{ customer.name }}</td>
                    <td>
                        <span :class="customer.active ? 'status-active' : 'status-inactive'">
                            {{ customer.active ? 'Aktiv' : 'Inaktiv' }}
                        </span>
                    </td>
                    <td v-if="isAdmin" class="actions">
                        <NcButton type="tertiary" @click="editCustomer(customer)">
                            Bearbeiten
                        </NcButton>
                        <NcButton type="error" @click="deleteCustomer(customer.id)">
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
                    <div class="form-group" v-if="editingCustomer">
                        <label>
                            <input v-model="form.active" type="checkbox">
                            Aktiv
                        </label>
                    </div>
                    <div class="modal-actions">
                        <NcButton type="tertiary" @click="closeDialog">
                            {{ t('timetracking', 'Abbrechen') }}
                        </NcButton>
                        <NcButton type="primary" native-type="submit">
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
            },
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
                showError('Fehler beim Speichern')
                console.error(error)
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
    padding: 12px 16px;
    background-color: var(--color-info);
    border-radius: 4px;
    color: var(--color-text-light);
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

.actions {
    display: flex;
    gap: 8px;
}

.status-active {
    color: var(--color-success);
    font-weight: bold;
}

.status-inactive {
    color: var(--color-error);
}

.modal-content {
    padding: 24px;
}

.modal-content h2 {
    margin-bottom: 24px;
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
.form-group input[type="email"],
.form-group input[type="tel"],
.form-group textarea {
    width: 100%;
    padding: 8px;
    border: 1px solid var(--color-border);
    border-radius: 4px;
    font-family: inherit;
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
}
</style>
