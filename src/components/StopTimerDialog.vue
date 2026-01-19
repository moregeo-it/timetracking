<template>
    <NcModal v-if="show" @close="cancel" size="normal">
        <div class="stop-timer-dialog">
            <h2>{{ t('timetracking', 'Timer Stoppen') }}</h2>
            <p class="timer-info">
                {{ t('timetracking', 'Erfasste Zeit') }}: <strong>{{ timerDisplay }}</strong>
            </p>
            
            <form @submit.prevent="submit">
                <div class="form-group">
                    <label>{{ t('timetracking', 'Projekt') }} *</label>
                    <select v-model="form.projectId" required>
                        <option value="">{{ t('timetracking', 'Bitte wählen') }}</option>
                        <option v-for="project in activeProjects" :key="project.id" :value="project.id">
                            {{ project.name }} ({{ getCustomerName(project.customerId) }})
                        </option>
                    </select>
                </div>
                <div class="form-group">
                    <label>{{ t('timetracking', 'Beschreibung') }}</label>
                    <input v-model="form.description" type="text" :placeholder="t('timetracking', 'Was haben Sie gemacht?')">
                </div>
                <div class="form-group">
                    <label>
                        <input v-model="form.billable" type="checkbox" style="margin: 0 0.5rem 0 0">
                        {{ t('timetracking', 'Abrechenbar') }}
                    </label>
                </div>
                <div class="dialog-actions">
                    <NcButton type="button" @click="cancel">{{ t('timetracking', 'Abbrechen') }}</NcButton>
                    <NcButton type="submit">{{ t('timetracking', 'Speichern') }}</NcButton>
                </div>
            </form>
        </div>
    </NcModal>
</template>

<script>
import { NcModal, NcButton } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'

export default {
    name: 'StopTimerDialog',
    components: {
        NcModal,
        NcButton,
    },
    props: {
        show: {
            type: Boolean,
            default: false,
        },
        timerDisplay: {
            type: String,
            default: '00:00:00',
        },
        projects: {
            type: Array,
            default: () => [],
        },
        customers: {
            type: Array,
            default: () => [],
        },
        initialProjectId: {
            type: [Number, String],
            default: '',
        },
        initialDescription: {
            type: String,
            default: '',
        },
    },
    emits: ['cancel', 'confirm'],
    data() {
        return {
            form: {
                projectId: this.initialProjectId || '',
                description: this.initialDescription || '',
                billable: true,
            },
        }
    },
    computed: {
        activeProjects() {
            return this.projects.filter(p => p.active)
        },
    },
    watch: {
        show(newVal) {
            if (newVal) {
                this.form.projectId = this.initialProjectId || ''
                this.form.description = this.initialDescription || ''
                this.form.billable = true
            }
        },
    },
    methods: {
        t,
        getCustomerName(customerId) {
            const customer = this.customers.find(c => c.id === customerId)
            return customer ? customer.name : 'Unbekannt'
        },
        cancel() {
            this.$emit('cancel')
        },
        submit() {
            if (!this.form.projectId) {
                return
            }
            this.$emit('confirm', {
                projectId: this.form.projectId,
                description: this.form.description,
                billable: this.form.billable,
            })
        },
    },
}
</script>

<style scoped>
.stop-timer-dialog {
    padding: 1rem;
    min-width: 400px;
}

.stop-timer-dialog h2 {
    margin-top: 0;
    margin-bottom: 20px;
}

.timer-info {
    font-size: 18px;
    margin-bottom: 25px;
    padding: 1rem;
    background: var(--color-background-dark);
    border-radius: 8px;
    text-align: center;
}

.timer-info strong {
    display: block;
    font-size: 32px;
    color: #2e7d32;
    margin-top: 8px;
}

.stop-timer-dialog .form-group {
    margin-bottom: 20px;
}

.stop-timer-dialog .form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
}

.stop-timer-dialog .form-group select,
.stop-timer-dialog .form-group input[type="text"] {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--color-border);
    border-radius: 4px;
    font-size: 14px;
}

.stop-timer-dialog .form-group input[type="checkbox"] {
    min-height: auto;
    height: auto;
    margin-right: 8px;
}

.dialog-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 25px;
    padding-top: 20px;
    border-top: 1px solid var(--color-border);
}
</style>
