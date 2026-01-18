<template>
    <div class="reports">
        <div class="page-header">
            <h1>{{ t('timetracking', 'Berichte') }}</h1>
        </div>
        
        <div class="tabs">
            <button 
                @click="activeTab = 'customer'" 
                :class="{ active: activeTab === 'customer' }"
                class="tab-button">
                {{ t('timetracking', 'Kunden-Berichte') }}
            </button>
            <button 
                @click="activeTab = 'project'" 
                :class="{ active: activeTab === 'project' }"
                class="tab-button">
                {{ t('timetracking', 'Projekt-Berichte') }}
            </button>
            <button 
                @click="activeTab = 'employee'" 
                :class="{ active: activeTab === 'employee' }"
                class="tab-button">
                {{ t('timetracking', 'Mitarbeiter-Berichte') }}
            </button>
            <button 
                @click="activeTab = 'compliance'" 
                :class="{ active: activeTab === 'compliance' }"
                class="tab-button">
                {{ t('timetracking', 'Arbeitszeitgesetz-Prüfung') }}
            </button>
            <button 
                @click="activeTab = 'overview'" 
                :class="{ active: activeTab === 'overview' }"
                class="tab-button">
                {{ t('timetracking', 'Monatsübersicht') }}
            </button>
        </div>
        
        <!-- Customer Report -->
        <div v-if="activeTab === 'customer'" class="report-section">
            <h2>{{ t('timetracking', 'Kundenbericht') }}</h2>
            <form @submit.prevent="loadCustomerReport" class="report-form">
                <div class="form-group">
                    <label>{{ t('timetracking', 'Kunde') }}</label>
                    <select v-model="customerReportForm.customerId" required>
                        <option value="">{{ t('timetracking', 'Bitte wählen') }}</option>
                        <option v-for="customer in customers" :key="customer.id" :value="customer.id">
                            {{ customer.name }}
                        </option>
                    </select>
                </div>
                <div class="form-group">
                    <label>{{ t('timetracking', 'Zeitraum') }}</label>
                    <select v-model="customerReportForm.periodType" @change="customerReport = null">
                        <option value="month">{{ t('timetracking', 'Monat') }}</option>
                        <option value="quarter">{{ t('timetracking', 'Quartal') }}</option>
                        <option value="year">{{ t('timetracking', 'Jahr') }}</option>
                        <option value="total">{{ t('timetracking', 'Gesamt') }}</option>
                    </select>
                </div>
                <div class="form-group" v-if="customerReportForm.periodType !== 'total'">
                    <label>{{ t('timetracking', 'Jahr') }}</label>
                    <input v-model.number="customerReportForm.year" type="number" :min="2020" :max="2030" required>
                </div>
                <div class="form-group" v-if="customerReportForm.periodType === 'month'">
                    <label>{{ t('timetracking', 'Monat') }}</label>
                    <select v-model.number="customerReportForm.month" required>
                        <option v-for="month in 12" :key="month" :value="month">{{ getMonthName(month) }}</option>
                    </select>
                </div>
                <div class="form-group" v-if="customerReportForm.periodType === 'quarter'">
                    <label>{{ t('timetracking', 'Quartal') }}</label>
                    <select v-model.number="customerReportForm.quarter" required>
                        <option value="1">Q1 (Jan-Mär)</option>
                        <option value="2">Q2 (Apr-Jun)</option>
                        <option value="3">Q3 (Jul-Sep)</option>
                        <option value="4">Q4 (Okt-Dez)</option>
                    </select>
                </div>
                <div class="form-group form-group-button">
                    <label>&nbsp;</label>
                    <NcButton type="primary" native-type="submit">{{ t('timetracking', 'Bericht Erstellen') }}</NcButton>
                </div>
            </form>
            
            <div v-if="customerReport" class="report-result">
                <h3>{{ customerReport.customer.name }} - {{ customerReport.period.label }}</h3>
                
                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="summary-label">{{ t('timetracking', 'Gesamtstunden') }}</div>
                        <div class="summary-value">{{ customerReport.totals.hours }} h</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">{{ t('timetracking', 'Abrechenbare Stunden') }}</div>
                        <div class="summary-value">{{ customerReport.totals.billableHours }} h</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">{{ t('timetracking', 'Gesamtbetrag') }}</div>
                        <div class="summary-value">{{ customerReport.totals.amount }} {{ getCurrencySymbol(customerReport.customer.currency) }}</div>
                    </div>
                </div>
                
                <h4>{{ t('timetracking', 'Projekte') }}</h4>
                <table v-if="customerReport.projects.length > 0">
                    <thead>
                        <tr>
                            <th>{{ t('timetracking', 'Projekt') }}</th>
                            <th>{{ t('timetracking', 'Stunden') }}</th>
                            <th>{{ t('timetracking', 'Abrechenbar') }}</th>
                            <th>{{ t('timetracking', 'Stundensatz') }}</th>
                            <th>{{ t('timetracking', 'Betrag') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in customerReport.projects" :key="item.project.id">
                            <td>{{ item.project.name }}</td>
                            <td>{{ item.hours }} h</td>
                            <td>{{ item.billableHours }} h</td>
                            <td>{{ item.hourlyRate !== null && item.hourlyRate !== undefined ? item.hourlyRate + ' ' + getCurrencySymbol(customerReport.customer.currency) : '-' }}</td>
                            <td>{{ item.amount }} {{ getCurrencySymbol(customerReport.customer.currency) }}</td>
                        </tr>
                    </tbody>
                </table>
                <p v-else>{{ t('timetracking', 'Keine Daten vorhanden') }}</p>
            </div>
        </div>
        
        <!-- Project Report -->
        <div v-if="activeTab === 'project'" class="report-section">
            <h2>{{ t('timetracking', 'Projektbericht') }}</h2>
            <form @submit.prevent="loadProjectReport" class="report-form">
                <div class="form-group">
                    <label>{{ t('timetracking', 'Projekt') }}</label>
                    <select v-model="projectReportForm.projectId" required @change="onProjectChange">
                        <option value="">{{ t('timetracking', 'Bitte wählen') }}</option>
                        <option v-for="project in projects" :key="project.id" :value="project.id">
                            {{ project.name }}
                        </option>
                    </select>
                </div>
                <div class="form-group">
                    <label>{{ t('timetracking', 'Zeitraum') }}</label>
                    <select v-model="projectReportForm.periodType" @change="projectReport = null">
                        <option value="month">{{ t('timetracking', 'Monat') }}</option>
                        <option value="quarter">{{ t('timetracking', 'Quartal') }}</option>
                        <option value="year">{{ t('timetracking', 'Jahr') }}</option>
                        <option value="project_period" :disabled="!hasProjectDates">{{ t('timetracking', 'Projektzeitraum') }}</option>
                        <option value="total">{{ t('timetracking', 'Gesamt') }}</option>
                    </select>
                </div>
                <div class="form-group" v-if="projectReportForm.periodType !== 'total' && projectReportForm.periodType !== 'project_period'">
                    <label>{{ t('timetracking', 'Jahr') }}</label>
                    <input v-model.number="projectReportForm.year" type="number" :min="2020" :max="2030" required>
                </div>
                <div class="form-group" v-if="projectReportForm.periodType === 'month'">
                    <label>{{ t('timetracking', 'Monat') }}</label>
                    <select v-model.number="projectReportForm.month" required>
                        <option v-for="month in 12" :key="month" :value="month">{{ getMonthName(month) }}</option>
                    </select>
                </div>
                <div class="form-group" v-if="projectReportForm.periodType === 'quarter'">
                    <label>{{ t('timetracking', 'Quartal') }}</label>
                    <select v-model.number="projectReportForm.quarter" required>
                        <option value="1">Q1 (Jan-Mär)</option>
                        <option value="2">Q2 (Apr-Jun)</option>
                        <option value="3">Q3 (Jul-Sep)</option>
                        <option value="4">Q4 (Okt-Dez)</option>
                    </select>
                </div>
                <div class="form-group form-group-button">
                    <label>&nbsp;</label>
                    <NcButton type="primary" native-type="submit">{{ t('timetracking', 'Bericht Erstellen') }}</NcButton>
                </div>
            </form>
            
            <div v-if="projectReport" class="report-result">
                <h3>{{ projectReport.project.name }} - {{ projectReport.period.label }}</h3>
                <p>{{ t('timetracking', 'Kunde') }}: {{ projectReport.customer.name }}</p>
                
                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="summary-label">{{ t('timetracking', 'Gesamtstunden') }}</div>
                        <div class="summary-value">{{ projectReport.totals.hours }} h</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">{{ t('timetracking', 'Abrechenbare Stunden') }}</div>
                        <div class="summary-value">{{ projectReport.totals.billableHours }} h</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">{{ t('timetracking', 'Gesamtbetrag') }}</div>
                        <div class="summary-value">{{ projectReport.totals.amount }} {{ getCurrencySymbol(projectReport.customer.currency) }}</div>
                    </div>
                </div>
                
                <!-- Budget Usage -->
                <div v-if="projectReport.budget" class="info-box">
                    <strong>{{ t('timetracking', 'Budget') }}:</strong> {{ projectReport.budget.budgetHours }} h<br>
                    <strong>{{ t('timetracking', 'Verwendet') }}:</strong> {{ projectReport.budget.usedHours }} h ({{ projectReport.budget.usagePercent }}%)<br>
                    <strong>{{ t('timetracking', 'Verbleibend') }}:</strong> {{ projectReport.budget.remainingHours }} h
                    <div class="progress-bar">
                        <div class="progress-fill" :style="{ width: Math.min(projectReport.budget.usagePercent, 100) + '%' }" 
                             :class="{ 'over-budget': projectReport.budget.usagePercent > 100 }"></div>
                    </div>
                </div>
                
                <h4>{{ t('timetracking', 'Mitarbeiter') }}</h4>
                <table v-if="projectReport.userSummary.length > 0">
                    <thead>
                        <tr>
                            <th>{{ t('timetracking', 'Benutzer') }}</th>
                            <th>{{ t('timetracking', 'Stunden') }}</th>
                            <th>{{ t('timetracking', 'Abrechenbare Stunden') }}</th>
                            <th>{{ t('timetracking', 'Einträge') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="user in projectReport.userSummary" :key="user.userId">
                            <td>{{ user.userId }}</td>
                            <td>{{ user.hours }} h</td>
                            <td>{{ user.billableHours }} h</td>
                            <td>{{ user.entryCount }}</td>
                        </tr>
                    </tbody>
                </table>
                <p v-else>{{ t('timetracking', 'Keine Daten vorhanden') }}</p>
            </div>
        </div>
        
        <!-- Employee Report -->
        <div v-if="activeTab === 'employee'" class="report-section">
            <h2>{{ t('timetracking', 'Mitarbeiter Arbeitszeitbericht') }}</h2>
            <form @submit.prevent="loadEmployeeReport" class="report-form">
                <div class="form-group">
                    <label>{{ t('timetracking', 'Zeitraum') }}</label>
                    <select v-model="employeeReportForm.periodType" @change="employeeReport = null">
                        <option value="month">{{ t('timetracking', 'Monat') }}</option>
                        <option value="quarter">{{ t('timetracking', 'Quartal') }}</option>
                        <option value="year">{{ t('timetracking', 'Jahr') }}</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>{{ t('timetracking', 'Jahr') }}</label>
                    <input v-model.number="employeeReportForm.year" type="number" :min="2020" :max="2030" required>
                </div>
                <div class="form-group" v-if="employeeReportForm.periodType === 'month'">
                    <label>{{ t('timetracking', 'Monat') }}</label>
                    <select v-model.number="employeeReportForm.month" required>
                        <option v-for="month in 12" :key="month" :value="month">{{ getMonthName(month) }}</option>
                    </select>
                </div>
                <div class="form-group" v-if="employeeReportForm.periodType === 'quarter'">
                    <label>{{ t('timetracking', 'Quartal') }}</label>
                    <select v-model.number="employeeReportForm.quarter" required>
                        <option value="1">Q1 (Jan-Mär)</option>
                        <option value="2">Q2 (Apr-Jun)</option>
                        <option value="3">Q3 (Jul-Sep)</option>
                        <option value="4">Q4 (Okt-Dez)</option>
                    </select>
                </div>
                <div class="form-group form-group-button">
                    <label>&nbsp;</label>
                    <NcButton type="primary" native-type="submit">{{ t('timetracking', 'Bericht Erstellen') }}</NcButton>
                </div>
            </form>
            
            <div v-if="employeeReport" class="report-result">
                <h3>{{ t('timetracking', 'Arbeitszeit') }} {{ employeeReport.period.label }}</h3>
                
                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="summary-label">{{ t('timetracking', 'Gesamtstunden') }}</div>
                        <div class="summary-value">{{ employeeReport.totals.hours }} h</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">{{ t('timetracking', 'Arbeitstage') }}</div>
                        <div class="summary-value">{{ employeeReport.totals.workDays }}</div>
                    </div>
                    <div v-if="employeeReport.totals.hourlyRate" class="summary-card">
                        <div class="summary-label">{{ t('timetracking', 'Stundensatz') }}</div>
                        <div class="summary-value">{{ employeeReport.totals.hourlyRate }} €</div>
                    </div>
                    <div v-if="employeeReport.totals.revenue" class="summary-card">
                        <div class="summary-label">{{ t('timetracking', 'Umsatz') }}</div>
                        <div class="summary-value success">{{ employeeReport.totals.revenue }} €</div>
                    </div>
                </div>
                
                <!-- Additional info for contract employees -->
                <div v-if="employeeReport.totals.expectedHours" class="info-box">
                    <strong>{{ t('timetracking', 'Vertragliche Wochenstunden') }}:</strong> {{ employeeReport.totals.weeklyHours }} h<br>
                    <strong>{{ t('timetracking', 'Erwartete Stunden') }}:</strong> {{ employeeReport.totals.expectedHours }} h<br>
                    <strong>{{ t('timetracking', 'Erfasste Stunden') }}:</strong> {{ employeeReport.totals.hours }} h
                </div>
                
                <!-- Additional info for freelancers -->
                <div v-if="employeeReport.totals.maxTotalHours" class="info-box">
                    <strong>{{ t('timetracking', 'Gesamtkontingent') }}:</strong> {{ employeeReport.totals.maxTotalHours }} h<br>
                    <strong>{{ t('timetracking', 'Bereits erfasst') }}:</strong> {{ employeeReport.totals.totalHoursAllTime }} h<br>
                    <strong>{{ t('timetracking', 'Verbleibend') }}:</strong> {{ employeeReport.totals.remainingHours }} h<br>
                    <strong>{{ t('timetracking', 'Auslastung') }}:</strong> {{ employeeReport.totals.percentageUsed }}%
                </div>
                
                <h4>{{ t('timetracking', 'Tägliche Übersicht') }}</h4>
                <table v-if="employeeReport.dailySummary.length > 0">
                    <thead>
                        <tr>
                            <th>{{ t('timetracking', 'Datum') }}</th>
                            <th>{{ t('timetracking', 'Stunden') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="day in employeeReport.dailySummary" :key="day.date">
                            <td>{{ formatDate(day.date) }}</td>
                            <td>{{ day.hours }} h</td>
                        </tr>
                    </tbody>
                </table>
                <p v-else>{{ t('timetracking', 'Keine Daten vorhanden') }}</p>
                
                <h4>{{ t('timetracking', 'Projekt-Übersicht') }}</h4>
                <table v-if="employeeReport.projectSummary.length > 0">
                    <thead>
                        <tr>
                            <th>{{ t('timetracking', 'Projekt') }}</th>
                            <th>{{ t('timetracking', 'Stunden') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in employeeReport.projectSummary" :key="item.project.id">
                            <td>{{ item.project.name }}</td>
                            <td>{{ item.hours }} h</td>
                        </tr>
                    </tbody>
                </table>
                <p v-else>{{ t('timetracking', 'Keine Daten vorhanden') }}</p>
            </div>
        </div>
        
        <!-- Compliance Check -->
        <div v-if="activeTab === 'compliance'" class="report-section">
            <h2>{{ t('timetracking', 'Arbeitszeitgesetz-Prüfung') }}</h2>
            <p class="info-text">
                {{ t('timetracking', 'Prüfung der Einhaltung des deutschen Arbeitszeitgesetzes (ArbZG)') }}:<br>
                • Max. 8 Stunden täglich (Regelarbeitszeit)<br>
                • Max. 10 Stunden täglich (mit Ausgleich)<br>
                • Max. 48 Stunden wöchentlich<br>
                • Min. 11 Stunden Ruhezeit zwischen Arbeitstagen
            </p>
            
            <form @submit.prevent="loadComplianceReport" class="report-form">
                <div class="form-group">
                    <label>{{ t('timetracking', 'Zeitraum') }}</label>
                    <select v-model="complianceReportForm.periodType" @change="complianceReport = null">
                        <option value="month">{{ t('timetracking', 'Monat') }}</option>
                        <option value="year">{{ t('timetracking', 'Jahr') }}</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>{{ t('timetracking', 'Jahr') }}</label>
                    <input v-model.number="complianceReportForm.year" type="number" :min="2020" :max="2030" required>
                </div>
                <div class="form-group" v-if="complianceReportForm.periodType === 'month'">
                    <label>{{ t('timetracking', 'Monat') }}</label>
                    <select v-model.number="complianceReportForm.month" required>
                        <option v-for="month in 12" :key="month" :value="month">{{ getMonthName(month) }}</option>
                    </select>
                </div>
                <div class="form-group form-group-button">
                    <label>&nbsp;</label>
                    <NcButton type="primary" native-type="submit">{{ t('timetracking', 'Prüfung Starten') }}</NcButton>
                </div>
            </form>
            
            <div v-if="complianceReport" class="report-result">
                <div :class="['compliance-status', complianceReport.compliant ? 'compliant' : 'non-compliant']">
                    <h3>
                        <span v-if="complianceReport.compliant" class="icon-checkmark"></span>
                        <span v-else class="icon-close"></span>
                        {{ complianceReport.compliant ? t('timetracking', 'Konform') : t('timetracking', 'Verstöße festgestellt') }}
                    </h3>
                    <p>{{ complianceReport.period.label }}</p>
                </div>
                
                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="summary-label">{{ t('timetracking', 'Gesamtstunden') }}</div>
                        <div class="summary-value">{{ complianceReport.statistics.totalHours }} h</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">{{ t('timetracking', 'Ø Stunden/Tag') }}</div>
                        <div class="summary-value">{{ complianceReport.statistics.averageDailyHours }} h</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">{{ t('timetracking', 'Max. Stunden/Tag') }}</div>
                        <div class="summary-value">{{ complianceReport.statistics.maxDailyHours }} h</div>
                    </div>
                </div>
                
                <div v-if="complianceReport.violations && complianceReport.violations.length > 0" class="violations-section">
                    <h4>⚠️ {{ t('timetracking', 'Verstöße') }} ({{ complianceReport.violationCount }})</h4>
                    <div v-for="(violation, index) in complianceReport.violations" :key="index" class="violation-item high">
                        <strong>{{ violation.type }}</strong><br>
                        {{ violation.message }}<br>
                        <small>
                            <span v-if="violation.date">{{ t('timetracking', 'Datum') }}: {{ formatDate(violation.date) }}</span>
                            <span v-if="violation.weekStart">{{ t('timetracking', 'Woche') }}: {{ formatDate(violation.weekStart) }} - {{ formatDate(violation.weekEnd) }}</span>
                        </small>
                    </div>
                </div>
                
                <div v-if="complianceReport.warnings && complianceReport.warnings.length > 0" class="warnings-section">
                    <h4>⚡ {{ t('timetracking', 'Warnungen') }} ({{ complianceReport.warningCount }})</h4>
                    <div v-for="(warning, index) in complianceReport.warnings" :key="index" class="violation-item medium">
                        <strong>{{ warning.type }}</strong><br>
                        {{ warning.message }}<br>
                        <small>
                            <span v-if="warning.date">{{ t('timetracking', 'Datum') }}: {{ formatDate(warning.date) }}</span>
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Monthly Overview -->
        <div v-if="activeTab === 'overview'" class="report-section">
            <h2>{{ t('timetracking', 'Monatsübersicht') }}</h2>
            <form @submit.prevent="loadMonthlyOverview" class="report-form">
                <div class="form-group">
                    <label>{{ t('timetracking', 'Jahr') }}</label>
                    <input v-model.number="overviewForm.year" type="number" :min="2020" :max="2030" required>
                </div>
                <div class="form-group">
                    <label>{{ t('timetracking', 'Monat') }}</label>
                    <select v-model.number="overviewForm.month" required>
                        <option v-for="month in 12" :key="month" :value="month">{{ getMonthName(month) }}</option>
                    </select>
                </div>
                <div class="form-group form-group-button">
                    <label>&nbsp;</label>
                    <NcButton type="primary" native-type="submit">{{ t('timetracking', 'Übersicht Laden') }}</NcButton>
                </div>
            </form>
            
            <div v-if="monthlyOverview" class="report-result">
                <h3>{{ getMonthName(monthlyOverview.period.month) }} {{ monthlyOverview.period.year }}</h3>
                
                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="summary-label">{{ t('timetracking', 'Gesamtstunden') }}</div>
                        <div class="summary-value">{{ formatHoursDecimal(monthlyOverview.totals.hours) }} h</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">{{ t('timetracking', 'Kunden') }}</div>
                        <div class="summary-value">{{ monthlyOverview.customers.length }}</div>
                    </div>
                </div>
                
                <div v-for="customerData in monthlyOverview.customers" :key="customerData.customer.id" class="overview-customer">
                    <div class="overview-customer-header" @click="toggleOverviewCustomer(customerData.customer.id)">
                        <span class="toggle-icon">{{ expandedOverviewCustomers[customerData.customer.id] ? '▼' : '▶' }}</span>
                        <strong>{{ customerData.customer.name }}</strong>
                        <span class="overview-hours">{{ formatHoursDecimal(customerData.totals.hours) }} h</span>
                    </div>
                    
                    <div v-if="expandedOverviewCustomers[customerData.customer.id]" class="overview-customer-content">
                        <div v-for="projectData in customerData.projects" :key="projectData.project.id" class="overview-project">
                            <div class="overview-project-header" @click="toggleOverviewProject(projectData.project.id)">
                                <span class="toggle-icon">{{ expandedOverviewProjects[projectData.project.id] ? '▼' : '▶' }}</span>
                                {{ projectData.project.name }}
                                <span class="overview-hours">{{ formatHoursDecimal(projectData.totals.hours) }} h</span>
                            </div>
                            
                            <div v-if="expandedOverviewProjects[projectData.project.id]" class="overview-project-content">
                                <div v-for="employee in projectData.employees" :key="employee.userId" class="overview-employee">
                                    <span>{{ employee.displayName }}</span>
                                    <span class="overview-hours">{{ formatHoursDecimal(employee.hours) }} h</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <p v-if="monthlyOverview.customers.length === 0" class="no-data">
                    {{ t('timetracking', 'Keine Daten für diesen Zeitraum') }}
                </p>
            </div>
        </div>
    </div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import { getCurrentUser } from '@nextcloud/auth'
import { NcButton } from '@nextcloud/vue'

export default {
    name: 'Reports',
    components: {
        NcButton,
    },
    data() {
        const now = new Date()
        const currentQuarter = Math.ceil((now.getMonth() + 1) / 3)
        return {
            activeTab: 'customer',
            customers: [],
            projects: [],
            customerReport: null,
            projectReport: null,
            employeeReport: null,
            complianceReport: null,
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
            customerReportForm: {
                customerId: '',
                periodType: 'month',
                year: now.getFullYear(),
                month: now.getMonth() + 1,
                quarter: currentQuarter,
            },
            projectReportForm: {
                projectId: '',
                periodType: 'month',
                year: now.getFullYear(),
                month: now.getMonth() + 1,
                quarter: currentQuarter,
            },
            employeeReportForm: {
                periodType: 'month',
                year: now.getFullYear(),
                month: now.getMonth() + 1,
                quarter: currentQuarter,
            },
            complianceReportForm: {
                periodType: 'month',
                year: now.getFullYear(),
                month: now.getMonth() + 1,
            },
            // Monthly overview
            overviewForm: {
                year: now.getFullYear(),
                month: now.getMonth() + 1,
            },
            monthlyOverview: null,
            expandedOverviewCustomers: {},
            expandedOverviewProjects: {},
        }
    },
    computed: {
        hasProjectDates() {
            if (!this.projectReportForm.projectId) return false
            const project = this.projects.find(p => p.id === this.projectReportForm.projectId)
            return project && project.startDate && project.endDate
        },
    },
    mounted() {
        // Check admin access
        if (!getCurrentUser()?.isAdmin) {
            this.$router.push('/')
            return
        }
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
                const response = await axios.get(generateUrl('/apps/timetracking/api/projects'))
                this.projects = response.data
            } catch (error) {
                console.error(error)
            }
        },
        onProjectChange() {
            this.projectReport = null
            // Reset to month if project_period was selected but new project has no dates
            if (this.projectReportForm.periodType === 'project_period' && !this.hasProjectDates) {
                this.projectReportForm.periodType = 'month'
            }
        },
        async loadCustomerReport() {
            try {
                const { customerId, periodType, year, month, quarter } = this.customerReportForm
                const params = new URLSearchParams({
                    customerId,
                    periodType,
                    year,
                })
                if (periodType === 'month') params.append('month', month)
                if (periodType === 'quarter') params.append('quarter', quarter)
                
                const url = `/apps/timetracking/api/reports/customer?${params.toString()}`
                const response = await axios.get(generateUrl(url))
                this.customerReport = response.data
            } catch (error) {
                showError(this.t('timetracking', 'Fehler beim Laden des Berichts'))
                console.error(error)
            }
        },
        async loadProjectReport() {
            try {
                const { projectId, periodType, year, month, quarter } = this.projectReportForm
                const params = new URLSearchParams({
                    projectId,
                    periodType,
                    year,
                })
                if (periodType === 'month') params.append('month', month)
                if (periodType === 'quarter') params.append('quarter', quarter)
                
                const url = `/apps/timetracking/api/reports/project?${params.toString()}`
                const response = await axios.get(generateUrl(url))
                this.projectReport = response.data
            } catch (error) {
                showError(this.t('timetracking', 'Fehler beim Laden des Berichts'))
                console.error(error)
            }
        },
        async loadEmployeeReport() {
            try {
                const { periodType, year, month, quarter } = this.employeeReportForm
                const userId = OC.getCurrentUser().uid
                const params = new URLSearchParams({
                    userId,
                    periodType,
                    year,
                })
                if (periodType === 'month') params.append('month', month)
                if (periodType === 'quarter') params.append('quarter', quarter)
                
                const url = `/apps/timetracking/api/reports/employee?${params.toString()}`
                const response = await axios.get(generateUrl(url))
                this.employeeReport = response.data
            } catch (error) {
                showError(this.t('timetracking', 'Fehler beim Laden des Berichts'))
                console.error(error)
            }
        },
        async loadComplianceReport() {
            try {
                const { periodType, year, month } = this.complianceReportForm
                const userId = OC.getCurrentUser().uid
                const params = new URLSearchParams({
                    userId,
                    periodType,
                    year,
                })
                if (periodType === 'month') params.append('month', month)
                
                const url = `/apps/timetracking/api/reports/compliance?${params.toString()}`
                const response = await axios.get(generateUrl(url))
                this.complianceReport = response.data
            } catch (error) {
                showError(this.t('timetracking', 'Fehler beim Laden des Berichts'))
                console.error(error)
            }
        },
        getMonthName(month) {
            const months = [
                'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni',
                'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'
            ]
            return months[month - 1]
        },
        formatDate(dateStr) {
            return new Date(dateStr).toLocaleDateString('de-DE')
        },
        getCurrencySymbol(code) {
            const currency = this.currencies.find(c => c.code === code)
            return currency ? currency.symbol : (code || '€')
        },
        async loadMonthlyOverview() {
            try {
                const { year, month } = this.overviewForm
                const response = await axios.get(
                    generateUrl(`/apps/timetracking/api/reports/monthly-overview/${year}/${month}`)
                )
                this.monthlyOverview = response.data
                // Expand all customers by default
                this.expandedOverviewCustomers = {}
                this.expandedOverviewProjects = {}
                response.data.customers.forEach(c => {
                    this.expandedOverviewCustomers[c.customer.id] = true
                })
            } catch (error) {
                showError(this.t('timetracking', 'Fehler beim Laden des Berichts'))
                console.error(error)
            }
        },
        toggleOverviewCustomer(customerId) {
            this.expandedOverviewCustomers[customerId] = !this.expandedOverviewCustomers[customerId]
        },
        toggleOverviewProject(projectId) {
            this.expandedOverviewProjects[projectId] = !this.expandedOverviewProjects[projectId]
        },
        formatHoursDecimal(hours) {
            return hours.toFixed(2)
        },
        t,
    },
}
</script>

<style scoped>
/* Component-specific styles only - common styles are in App.vue */
.reports {
    max-width: 1200px;
}

.report-section {
    margin-top: 20px;
}

.info-text {
    background: var(--color-background-dark);
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.report-form {
    display: flex;
    gap: 15px;
    align-items: flex-end;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.report-form .form-group {
    flex: 1;
    min-width: 150px;
}

.report-form .form-group-button {
    flex: 0 0 auto;
    min-width: auto;
}

.report-result {
    background: var(--color-background-dark);
    padding: 20px;
    border-radius: 8px;
    margin-top: 20px;
}

.summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin: 20px 0;
}

.summary-card {
    background: var(--color-main-background);
    border: 1px solid var(--color-border);
    border-radius: 8px;
    padding: 20px;
    text-align: center;
}

.summary-label {
    font-size: 14px;
    color: var(--color-text-maxcontrast);
    margin-bottom: 10px;
}

.summary-value {
    font-size: 28px;
    font-weight: bold;
    color: var(--color-primary);
}

.summary-value.success {
    color: #2e7d32;
}

.info-box {
    background: var(--color-main-background);
    border: 1px solid var(--color-border);
    border-radius: 8px;
    padding: 15px;
    margin: 20px 0;
    line-height: 1.8;
}

.progress-bar {
    height: 10px;
    background: var(--color-border);
    border-radius: 5px;
    margin-top: 10px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: #2e7d32;
    border-radius: 5px;
    transition: width 0.3s ease;
}

.progress-fill.over-budget {
    background: #c62828;
}

.compliance-status {
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    text-align: center;
}

.compliance-status.compliant {
    background: #d4edda;
    color: #155724;
}

.compliance-status.non-compliant {
    background: #f8d7da;
    color: #721c24;
}

.violations-section,
.warnings-section {
    margin-top: 20px;
}

.violation-item {
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 10px;
}

.violation-item.high {
    background: #f8d7da;
    border-left: 4px solid #dc3545;
}

.violation-item.medium {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
}

/* Monthly Overview Styles */
.overview-customer {
    margin-bottom: 10px;
    border: 1px solid var(--color-border);
    border-radius: 8px;
    overflow: hidden;
}

.overview-customer-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 15px;
    background: var(--color-background-dark);
    cursor: pointer;
}

.overview-customer-header:hover {
    background: var(--color-background-hover);
}

.overview-customer-content {
    padding: 10px 15px;
}

.overview-project {
    margin-bottom: 8px;
}

.overview-project-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    background: var(--color-main-background);
    border: 1px solid var(--color-border-dark);
    border-radius: 4px;
    cursor: pointer;
}

.overview-project-header:hover {
    background: var(--color-background-hover);
}

.overview-project-content {
    padding: 8px 10px 8px 30px;
}

.overview-employee {
    display: flex;
    justify-content: space-between;
    padding: 5px 0;
    border-bottom: 1px solid var(--color-border);
}

.overview-employee:last-child {
    border-bottom: none;
}

.overview-hours {
    margin-left: auto;
    font-weight: bold;
    color: var(--color-primary);
}

.toggle-icon {
    font-size: 10px;
    width: 12px;
}

.no-data {
    color: var(--color-text-maxcontrast);
    text-align: center;
    padding: 20px;
}
</style>
