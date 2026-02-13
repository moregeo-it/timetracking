<template>
    <NcContent app-name="timetracking" class="tt-app">
        <NcAppNavigation>
            <NcAppNavigationList>
                <NcAppNavigationItem
                    :to="{ path: '/' }"
                    :name="t('timetracking', 'Dashboard')"
                    icon="icon-home">
                </NcAppNavigationItem>
                <NcAppNavigationItem
                    :to="{ path: '/tracking' }"
                    :name="t('timetracking', 'Zeiterfassung')"
                    icon="icon-history">
                </NcAppNavigationItem>
                <NcAppNavigationItem
                    :to="{ path: '/customers' }"
                    :name="t('timetracking', 'Kunden')"
                    icon="icon-user">
                </NcAppNavigationItem>
                <NcAppNavigationItem
                    :to="{ path: '/projects' }"
                    :name="t('timetracking', 'Projekte')"
                    icon="icon-projects">
                </NcAppNavigationItem>
                <NcAppNavigationItem
                    v-if="isAdmin"
                    :to="{ path: '/reports' }"
                    :name="t('timetracking', 'Berichte')"
                    icon="icon-category-monitoring">
                </NcAppNavigationItem>
                <NcAppNavigationItem
                    :to="{ path: '/vacations' }"
                    :name="t('timetracking', 'Urlaub')"
                    icon="icon-calendar-dark">
                </NcAppNavigationItem>
                <NcAppNavigationItem
                    :to="{ path: '/sick-days' }"
                    :name="t('timetracking', 'Krankmeldungen')"
                    icon="icon-error">
                </NcAppNavigationItem>
            </NcAppNavigationList>
            
            <template #footer>
                <NcAppNavigationList>
                    <NcAppNavigationItem
                        :to="{ path: '/public-holidays' }"
                        :name="t('timetracking', 'Feiertage')"
                        icon="icon-star">
                    </NcAppNavigationItem>
                    <NcAppNavigationItem
                        :to="{ path: '/settings' }"
                        :name="t('timetracking', 'Einstellungen')"
                        icon="icon-settings">
                    </NcAppNavigationItem>
                </NcAppNavigationList>
            </template>
        </NcAppNavigation>
        
        <NcAppContent>
            <router-view></router-view>
        </NcAppContent>
    </NcContent>
</template>

<script>
import { NcContent, NcAppNavigation, NcAppNavigationList, NcAppNavigationItem, NcAppContent } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'
import { getCurrentUser } from '@nextcloud/auth'

export default {
    name: 'App',
    components: {
        NcContent,
        NcAppNavigation,
        NcAppNavigationList,
        NcAppNavigationItem,
        NcAppContent,
    },
    data() {
        return {
            isAdmin: getCurrentUser()?.isAdmin || false,
        }
    },
    methods: {
        t,
    },
}
</script>

<style scoped>
.tt-app :deep(.app-content) {
    padding: 1rem !important;
}
</style>

<style>
/* =============================================
   GLOBAL STYLES - TimeTracking App
   ============================================= */

/* Page Header */
.tt-app .page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    padding-left: 40px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--color-border);
}

.tt-app .page-header h1 {
    margin: 0;
    font-size: 24px;
    font-weight: 600;
}

.tt-app .page-header .actions {
    display: flex;
    gap: 12px;
    align-items: center;
}

/* Tables */
.tt-app table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 16px;
}

.tt-app th,
.tt-app td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid var(--color-border);
}

.tt-app th {
    font-weight: bold;
    background-color: var(--color-background-dark);
}

.tt-app tbody tr:hover {
    background-color: var(--color-background-hover);
}

/* Table action cells */
.tt-app td.actions {
    display: flex;
    gap: 8px;
}

/* Forms */
.tt-app .form-group {
    margin-bottom: 16px;
}

.tt-app .form-group label {
    display: block;
    margin-bottom: 6px;
    font-weight: bold;
}

.tt-app .form-group input[type="text"],
.tt-app .form-group input[type="number"],
.tt-app .form-group input[type="email"],
.tt-app .form-group input[type="tel"],
.tt-app .form-group input[type="date"],
.tt-app .form-group input[type="time"],
.tt-app .form-group select,
.tt-app .form-group textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius, 4px);
    font-size: 14px;
    font-family: inherit;
    background-color: var(--color-main-background);
}

.tt-app .form-group input[type="checkbox"],
.tt-app input[type="checkbox"] {
    min-height: auto;
    height: auto;
}

.tt-app .form-group input:focus,
.tt-app .form-group select:focus,
.tt-app .form-group textarea:focus {
    outline: none;
    border-color: var(--color-primary-element);
}

.tt-app .form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
}

.tt-app .hint {
    margin-top: 4px;
    font-size: 12px;
    color: var(--color-text-maxcontrast);
    line-height: 1.4;
}

/* Modal / Dialog - Global styles for NcModal (rendered in portal) */
.modal-container {
    padding: 0 !important;
}

.modal-container__content {
    padding: 0 !important;
}

.modal-wrapper .modal-container {
    padding: 0 !important;
}

.tt-app .modal-content,
.modal-wrapper .modal-content {
    padding: 24px 28px;
    min-width: 420px;
}

.tt-app .modal-content h2,
.modal-wrapper .modal-content h2 {
    margin: 0 0 24px 0;
    font-size: 20px;
    font-weight: bold;
    color: var(--color-main-text);
}

.tt-app .modal-content .form-group,
.modal-wrapper .modal-content .form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-bottom: 16px;
}

.tt-app .modal-content .form-group label,
.modal-wrapper .modal-content .form-group label {
    display: block;
    font-weight: bold;
    margin-bottom: 0;
}

.tt-app .modal-content .form-group input[type="text"],
.tt-app .modal-content .form-group input[type="number"],
.tt-app .modal-content .form-group input[type="date"],
.tt-app .modal-content .form-group select,
.tt-app .modal-content .form-group textarea,
.modal-wrapper .modal-content .form-group input[type="text"],
.modal-wrapper .modal-content .form-group input[type="number"],
.modal-wrapper .modal-content .form-group input[type="date"],
.modal-wrapper .modal-content .form-group select,
.modal-wrapper .modal-content .form-group textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--color-border);
    border-radius: 6px;
    font-size: 14px;
    background-color: var(--color-main-background);
    font-family: inherit;
}

.tt-app .modal-actions,
.tt-app .dialog-actions,
.modal-wrapper .modal-actions {
    display: flex !important;
    flex-direction: row !important;
    flex-wrap: nowrap !important;
    justify-content: flex-end !important;
    align-items: center !important;
    gap: 12px !important;
    margin-top: 28px;
    padding-top: 20px;
    border-top: 1px solid var(--color-border);
}

.tt-app .modal-actions > *,
.tt-app .dialog-actions > *,
.modal-wrapper .modal-actions > * {
    flex-shrink: 0 !important;
    width: auto !important;
}

.tt-app .dialog-overlay {
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

.tt-app .dialog {
    background: var(--color-main-background);
    border-radius: 8px;
    padding: 24px;
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
}

/* Status badges */
.tt-app .status-active,
.tt-app .status-inactive {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 13px;
    font-weight: bold;
}

.tt-app .status-active {
    background: #2e7d32;
    color: #ffffff;
}

.tt-app .status-inactive {
    background: #c62828;
    color: #ffffff;
}

.tt-app .status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: bold;
}

.tt-app .status-badge.pending {
    background: #d4e8ff;
    color: #0082c9;
}

.tt-app .status-badge.approved {
    background: #d4edda;
    color: #155724;
}

.tt-app .status-badge.rejected {
    background: #f8d7da;
    color: #721c24;
}

/* Info message */
.tt-app .info-message {
    padding: 12px 16px;
    background-color: var(--color-background-dark);
    border-radius: 4px;
    color: var(--color-main-text);
}

/* Filters */
.tt-app .filters {
    margin-bottom: 20px;
}

.tt-app .filters select {
    margin-left: 10px;
    padding: 8px 12px;
    border: 1px solid var(--color-border);
    border-radius: 4px;
}

.tt-app .date-filter {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.tt-app .date-filter label {
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Tabs */
.tt-app .tabs {
    display: flex;
    gap: 0;
    margin-bottom: 24px;
    border-bottom: 2px solid var(--color-border);
}

.tt-app .tab-button {
    padding: 12px 24px;
    background: none;
    border: none;
    border-bottom: 3px solid transparent;
    margin-bottom: -2px;
    cursor: pointer;
    font-size: 14px;
    color: var(--color-main-text);
    transition: all 0.2s;
}

.tt-app .tab-button:hover {
    background: var(--color-background-hover);
}

.tt-app .tab-button.active {
    border-bottom-color: var(--color-primary-element);
    font-weight: bold;
}

/* Loading state */
.tt-app .loading {
    text-align: center;
    padding: 40px;
    color: var(--color-text-maxcontrast);
}

/* Empty state */
.tt-app .empty-state {
    text-align: center;
    padding: 40px;
    color: var(--color-text-maxcontrast);
}

/* Toast Notifications - Position bottom-right */
.toastify.dialogs {
    top: unset !important;
    bottom: 20px !important;
}
</style>
