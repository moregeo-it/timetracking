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
                    :to="{ path: '/reports' }"
                    :name="t('timetracking', 'Berichte')"
                    icon="icon-category-monitoring">
                </NcAppNavigationItem>
                <NcAppNavigationItem
                    :to="{ path: '/vacations' }"
                    :name="t('timetracking', 'Urlaub')"
                    icon="icon-calendar-dark">
                </NcAppNavigationItem>
            </NcAppNavigationList>
            
            <template #footer>
                <NcAppNavigationList>
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

export default {
    name: 'App',
    components: {
        NcContent,
        NcAppNavigation,
        NcAppNavigationList,
        NcAppNavigationItem,
        NcAppContent,
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

.tt-app :deep(.header-row) {
    padding-left: 3rem !important;
}
</style>
