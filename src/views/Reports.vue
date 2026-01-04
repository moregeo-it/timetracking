<template>
    <div class="reports">
        <h1>Berichte</h1>
        
        <div class="report-tabs">
            <button 
                @click="activeTab = 'customer'" 
                :class="{ active: activeTab === 'customer' }"
                class="tab-button">
                Kunden-Berichte
            </button>
            <button 
                @click="activeTab = 'project'" 
                :class="{ active: activeTab === 'project' }"
                class="tab-button">
                Projekt-Berichte
            </button>
            <button 
                @click="activeTab = 'employee'" 
                :class="{ active: activeTab === 'employee' }"
                class="tab-button">
                Mitarbeiter-Berichte
            </button>
            <button 
                @click="activeTab = 'compliance'" 
                :class="{ active: activeTab === 'compliance' }"
                class="tab-button">
                Arbeitszeitgesetz-Prüfung
            </button>
        </div>
        
        <!-- Customer Report -->
        <div v-if="activeTab === 'customer'" class="report-section">
            <h2>Monatsbericht pro Kunde</h2>
            <form @submit.prevent="loadCustomerReport" class="report-form">
                <div class="form-group">
                    <label>Kunde</label>
                    <select v-model="customerReportForm.customerId" required>
                        <option value="">Bitte wählen</option>
                        <option v-for="customer in customers" :key="customer.id" :value="customer.id">
                            {{ customer.name }}
                        </option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Jahr</label>
                    <input v-model.number="customerReportForm.year" type="number" :min="2020" :max="2030" required>
                </div>
                <div class="form-group">
                    <label>Monat</label>
                    <select v-model.number="customerReportForm.month" required>
                        <option v-for="month in 12" :key="month" :value="month">{{ getMonthName(month) }}</option>
                    </select>
                </div>
                <button type="submit" class="button primary">Bericht Erstellen</button>
            </form>
            
            <div v-if="customerReport" class="report-result">
                <h3>{{ customerReport.customer.name }} - {{ getMonthName(customerReport.period.month) }} {{ customerReport.period.year }}</h3>
                
                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="summary-label">Gesamtstunden</div>
                        <div class="summary-value">{{ customerReport.totals.hours }} h</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">Abrechenbare Stunden</div>
                        <div class="summary-value">{{ customerReport.totals.billableHours }} h</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">Gesamtbetrag</div>
                        <div class="summary-value">{{ customerReport.totals.amount }} €</div>
                    </div>
                </div>
                
                <h4>Projekte</h4>
                <table>
                    <thead>
                        <tr>
                            <th>Projekt</th>
                            <th>Stunden</th>
                            <th>Abrechenbar</th>
                            <th>Stundensatz</th>
                            <th>Betrag</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in customerReport.projects" :key="item.project.id">
                            <td>{{ item.project.name }}</td>
                            <td>{{ item.hours }} h</td>
                            <td>{{ item.billableHours }} h</td>
                            <td>{{ item.hourlyRate || '-' }} €</td>
                            <td>{{ item.amount }} €</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Project Report -->
        <div v-if="activeTab === 'project'" class="report-section">
            <h2>Monatsbericht pro Projekt</h2>
            <form @submit.prevent="loadProjectReport" class="report-form">
                <div class="form-group">
                    <label>Projekt</label>
                    <select v-model="projectReportForm.projectId" required>
                        <option value="">Bitte wählen</option>
                        <option v-for="project in projects" :key="project.id" :value="project.id">
                            {{ project.name }}
                        </option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Jahr</label>
                    <input v-model.number="projectReportForm.year" type="number" :min="2020" :max="2030" required>
                </div>
                <div class="form-group">
                    <label>Monat</label>
                    <select v-model.number="projectReportForm.month" required>
                        <option v-for="month in 12" :key="month" :value="month">{{ getMonthName(month) }}</option>
                    </select>
                </div>
                <button type="submit" class="button primary">Bericht Erstellen</button>
            </form>
            
            <div v-if="projectReport" class="report-result">
                <h3>{{ projectReport.project.name }} - {{ getMonthName(projectReport.period.month) }} {{ projectReport.period.year }}</h3>
                <p>Kunde: {{ projectReport.customer.name }}</p>
                
                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="summary-label">Gesamtstunden</div>
                        <div class="summary-value">{{ projectReport.totals.hours }} h</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">Abrechenbare Stunden</div>
                        <div class="summary-value">{{ projectReport.totals.billableHours }} h</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">Gesamtbetrag</div>
                        <div class="summary-value">{{ projectReport.totals.amount }} €</div>
                    </div>
                </div>
                
                <h4>Mitarbeiter</h4>
                <table>
                    <thead>
                        <tr>
                            <th>Benutzer</th>
                            <th>Stunden</th>
                            <th>Abrechenbare Stunden</th>
                            <th>Einträge</th>
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
            </div>
        </div>
        
        <!-- Employee Report -->
        <div v-if="activeTab === 'employee'" class="report-section">
            <h2>Mitarbeiter Arbeitszeitbericht</h2>
            <form @submit.prevent="loadEmployeeReport" class="report-form">
                <div class="form-group">
                    <label>Jahr</label>
                    <input v-model.number="employeeReportForm.year" type="number" :min="2020" :max="2030" required>
                </div>
                <div class="form-group">
                    <label>Monat</label>
                    <select v-model.number="employeeReportForm.month" required>
                        <option v-for="month in 12" :key="month" :value="month">{{ getMonthName(month) }}</option>
                    </select>
                </div>
                <button type="submit" class="button primary">Bericht Erstellen</button>
            </form>
            
            <div v-if="employeeReport" class="report-result">
                <h3>Arbeitszeit {{ getMonthName(employeeReport.period.month) }} {{ employeeReport.period.year }}</h3>
                
                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="summary-label">Gesamtstunden</div>
                        <div class="summary-value">{{ employeeReport.totals.hours }} h</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">Arbeitstage</div>
                        <div class="summary-value">{{ employeeReport.totals.workDays }}</div>
                    </div>
                    <div v-if="employeeReport.totals.hourlyRate" class="summary-card">
                        <div class="summary-label">Stundensatz</div>
                        <div class="summary-value">{{ employeeReport.totals.hourlyRate }} €</div>
                    </div>
                    <div v-if="employeeReport.totals.revenue" class="summary-card">
                        <div class="summary-label">Umsatz (Monat)</div>
                        <div class="summary-value success">{{ employeeReport.totals.revenue }} €</div>
                    </div>
                </div>
                
                <!-- Additional info for contract employees -->
                <div v-if="employeeReport.totals.expectedMonthlyHours" class="info-box">
                    <strong>Vertragliche Wochenstunden:</strong> {{ employeeReport.totals.weeklyHours }} h<br>
                    <strong>Erwartete Monatsstunden:</strong> {{ employeeReport.totals.expectedMonthlyHours }} h<br>
                    <strong>Erfasste Stunden:</strong> {{ employeeReport.totals.hours }} h
                </div>
                
                <!-- Additional info for freelancers -->
                <div v-if="employeeReport.totals.maxTotalHours" class="info-box">
                    <strong>Gesamtkontingent:</strong> {{ employeeReport.totals.maxTotalHours }} h<br>
                    <strong>Bereits erfasst (alle Zeit):</strong> {{ employeeReport.totals.totalHoursAllTime }} h<br>
                    <strong>Verbleibend:</strong> {{ employeeReport.totals.remainingHours }} h<br>
                    <strong>Auslastung:</strong> {{ employeeReport.totals.percentageUsed }}%
                </div>
                
                <h4>Tägliche Übersicht</h4>
                <table>
                    <thead>
                        <tr>
                            <th>Datum</th>
                            <th>Stunden</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="day in employeeReport.dailySummary" :key="day.date">
                            <td>{{ formatDate(day.date) }}</td>
                            <td>{{ day.hours }} h</td>
                        </tr>
                    </tbody>
                </table>
                
                <h4>Projekt-Übersicht</h4>
                <table>
                    <thead>
                        <tr>
                            <th>Projekt</th>
                            <th>Stunden</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in employeeReport.projectSummary" :key="item.project.id">
                            <td>{{ item.project.name }}</td>
                            <td>{{ item.hours }} h</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Compliance Check -->
        <div v-if="activeTab === 'compliance'" class="report-section">
            <h2>Arbeitszeitgesetz-Prüfung</h2>
            <p class="info-text">
                Prüfung der Einhaltung des deutschen Arbeitszeitgesetzes (ArbZG):<br>
                • Max. 8 Stunden täglich (Regelarbeitszeit)<br>
                • Max. 10 Stunden täglich (mit Ausgleich)<br>
                • Max. 48 Stunden wöchentlich<br>
                • Min. 11 Stunden Ruhezeit zwischen Arbeitstagen
            </p>
            
            <form @submit.prevent="loadComplianceReport" class="report-form">
                <div class="form-group">
                    <label>Jahr</label>
                    <input v-model.number="complianceReportForm.year" type="number" :min="2020" :max="2030" required>
                </div>
                <div class="form-group">
                    <label>Monat</label>
                    <select v-model.number="complianceReportForm.month" required>
                        <option v-for="month in 12" :key="month" :value="month">{{ getMonthName(month) }}</option>
                    </select>
                </div>
                <button type="submit" class="button primary">Prüfung Starten</button>
            </form>
            
            <div v-if="complianceReport" class="report-result">
                <div :class="['compliance-status', complianceReport.compliant ? 'compliant' : 'non-compliant']">
                    <h3>
                        <span v-if="complianceReport.compliant" class="icon-checkmark"></span>
                        <span v-else class="icon-close"></span>
                        {{ complianceReport.compliant ? 'Konform' : 'Verstöße festgestellt' }}
                    </h3>
                </div>
                
                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="summary-label">Gesamtstunden</div>
                        <div class="summary-value">{{ complianceReport.statistics.totalHours }} h</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">Ø Stunden/Tag</div>
                        <div class="summary-value">{{ complianceReport.statistics.averageDailyHours }} h</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">Max. Stunden/Tag</div>
                        <div class="summary-value">{{ complianceReport.statistics.maxDailyHours }} h</div>
                    </div>
                </div>
                
                <div v-if="complianceReport.violations.length > 0" class="violations-section">
                    <h4>⚠️ Verstöße ({{ complianceReport.violationCount }})</h4>
                    <div v-for="(violation, index) in complianceReport.violations" :key="index" class="violation-item high">
                        <strong>{{ violation.type }}</strong><br>
                        {{ violation.message }}<br>
                        <small>
                            <span v-if="violation.date">Datum: {{ formatDate(violation.date) }}</span>
                            <span v-if="violation.weekStart">Woche: {{ formatDate(violation.weekStart) }} - {{ formatDate(violation.weekEnd) }}</span>
                        </small>
                    </div>
                </div>
                
                <div v-if="complianceReport.warnings.length > 0" class="warnings-section">
                    <h4>⚡ Warnungen ({{ complianceReport.warningCount }})</h4>
                    <div v-for="(warning, index) in complianceReport.warnings" :key="index" class="violation-item medium">
                        <strong>{{ warning.type }}</strong><br>
                        {{ warning.message }}<br>
                        <small>
                            <span v-if="warning.date">Datum: {{ formatDate(warning.date) }}</span>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'

export default {
    name: 'Reports',
    data() {
        const now = new Date()
        return {
            activeTab: 'customer',
            customers: [],
            projects: [],
            customerReport: null,
            projectReport: null,
            employeeReport: null,
            complianceReport: null,
            customerReportForm: {
                customerId: '',
                year: now.getFullYear(),
                month: now.getMonth() + 1,
            },
            projectReportForm: {
                projectId: '',
                year: now.getFullYear(),
                month: now.getMonth() + 1,
            },
            employeeReportForm: {
                year: now.getFullYear(),
                month: now.getMonth() + 1,
            },
            complianceReportForm: {
                year: now.getFullYear(),
                month: now.getMonth() + 1,
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
                const response = await axios.get(generateUrl('/apps/timetracking/api/projects'))
                this.projects = response.data
            } catch (error) {
                console.error(error)
            }
        },
        async loadCustomerReport() {
            try {
                const { customerId, year, month } = this.customerReportForm
                const url = `/apps/timetracking/api/reports/customer/${customerId}/${year}/${month}`
                const response = await axios.get(generateUrl(url))
                this.customerReport = response.data
            } catch (error) {
                showError('Fehler beim Laden des Berichts')
                console.error(error)
            }
        },
        async loadProjectReport() {
            try {
                const { projectId, year, month } = this.projectReportForm
                const url = `/apps/timetracking/api/reports/project/${projectId}/${year}/${month}`
                const response = await axios.get(generateUrl(url))
                this.projectReport = response.data
            } catch (error) {
                showError('Fehler beim Laden des Berichts')
                console.error(error)
            }
        },
        async loadEmployeeReport() {
            try {
                const { year, month } = this.employeeReportForm
                const userId = OC.getCurrentUser().uid
                const url = `/apps/timetracking/api/reports/employee/${userId}/${year}/${month}`
                const response = await axios.get(generateUrl(url))
                this.employeeReport = response.data
            } catch (error) {
                showError('Fehler beim Laden des Berichts')
                console.error(error)
            }
        },
        async loadComplianceReport() {
            try {
                const { year, month } = this.complianceReportForm
                const userId = OC.getCurrentUser().uid
                const url = `/apps/timetracking/api/reports/compliance/${userId}/${year}/${month}`
                const response = await axios.get(generateUrl(url))
                this.complianceReport = response.data
            } catch (error) {
                showError('Fehler beim Laden des Berichts')
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
    },
}
</script>

<style scoped>
.report-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 30px;
    border-bottom: 2px solid var(--color-border);
}

.tab-button {
    padding: 12px 24px;
    background: none;
    border: none;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    font-size: 16px;
    color: var(--color-main-text);
    transition: all 0.2s;
}

.tab-button:hover {
    background: var(--color-background-hover);
}

.tab-button.active {
    border-bottom-color: var(--color-primary);
    font-weight: bold;
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

.form-group {
    flex: 1;
    min-width: 150px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 8px;
    border: 1px solid var(--color-border);
    border-radius: 4px;
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
    color: var(--color-success);
}

.info-box {
    background: var(--color-main-background);
    border: 1px solid var(--color-border);
    border-radius: 8px;
    padding: 15px;
    margin: 20px 0;
    line-height: 1.8;
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

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

th, td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid var(--color-border);
}

th {
    font-weight: bold;
    background-color: var(--color-main-background);
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
