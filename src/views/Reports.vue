<template>
    <div class="reports">
        <div class="page-header">
            <h1>{{ t('timetracking', 'Berichte') }}</h1>
        </div>
        
        <div class="tabs">
            <button
                type="button"
                @click="activeTab = 'customer'" 
                :class="{ active: activeTab === 'customer' }"
                class="tab-button">
                {{ t('timetracking', 'Kunden-Berichte') }}
            </button>
            <button
                type="button"
                @click="activeTab = 'project'" 
                :class="{ active: activeTab === 'project' }"
                class="tab-button">
                {{ t('timetracking', 'Projekt-Berichte') }}
            </button>
            <button
                type="button"
                @click="activeTab = 'employee'" 
                :class="{ active: activeTab === 'employee' }"
                class="tab-button">
                {{ t('timetracking', 'Mitarbeiter-Berichte') }}
            </button>
            <button
                type="button"
                @click="activeTab = 'compliance'" 
                :class="{ active: activeTab === 'compliance' }"
                class="tab-button">
                {{ t('timetracking', 'Arbeitszeitgesetz-Prüfung') }}
            </button>
            <button
                type="button"
                @click="activeTab = 'overview'" 
                :class="{ active: activeTab === 'overview' }"
                class="tab-button">
                {{ t('timetracking', 'Übersicht') }}
            </button>
        </div>
        
        <!-- Customer Report -->
        <div v-if="activeTab === 'customer'" class="report-section">
            <h2>{{ t('timetracking', 'Kundenbericht') }}</h2>
            <form @submit.prevent="loadCustomerReport" class="report-form">
                <div class="form-group">
                    <label>{{ t('timetracking', 'Kunde') }}</label>
                    <select v-model="customerReportForm.customerId" @change="customerReport = null; allCustomersReport = null">
                        <option value="">{{ t('timetracking', 'Alle Kunden') }}</option>
                        <option v-for="customer in sortedCustomers" :key="customer.id" :value="customer.id">
                            {{ customer.name }}
                        </option>
                    </select>
                </div>
                <div class="form-group">
                    <label>{{ t('timetracking', 'Zeitraum') }}</label>
                    <select v-model="customerReportForm.periodType" @change="customerReport = null; allCustomersReport = null">
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
                        <option value="1">Q1 ({{ getMonthName(1).substring(0,3) }}-{{ getMonthName(3).substring(0,3) }})</option>
                        <option value="2">Q2 ({{ getMonthName(4).substring(0,3) }}-{{ getMonthName(6).substring(0,3) }})</option>
                        <option value="3">Q3 ({{ getMonthName(7).substring(0,3) }}-{{ getMonthName(9).substring(0,3) }})</option>
                        <option value="4">Q4 ({{ getMonthName(10).substring(0,3) }}-{{ getMonthName(12).substring(0,3) }})</option>
                    </select>
                </div>
                <div class="form-group form-group-button">
                    <label>&nbsp;</label>
                    <NcButton type="submit">{{ t('timetracking', 'Bericht Erstellen') }}</NcButton>
                </div>
            </form>
            
            <div v-if="customerReport" class="report-result">
                <h3>{{ customerReport.customer.name }} - {{ customerReport.period.label }}</h3>
                
                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="summary-label">{{ t('timetracking', 'Stunden (Ist)') }}</div>
                        <div class="summary-value">{{ customerReport.totals.actualHours || customerReport.totals.hours }} h</div>
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
                            <th>{{ t('timetracking', 'Stunden (Ist)') }}</th>
                            <th>{{ t('timetracking', 'Abrechenbar') }}</th>
                            <th>{{ t('timetracking', 'Stundensatz') }}</th>
                            <th>{{ t('timetracking', 'Betrag') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in customerReport.projects" :key="item.project.id">
                            <td>{{ item.project.name }}</td>
                            <td>{{ item.actualHours || item.hours }} h</td>
                            <td>{{ item.billableHours }} h</td>
                            <td>{{ item.hourlyRate !== null && item.hourlyRate !== undefined ? item.hourlyRate + ' ' + getCurrencySymbol(customerReport.customer.currency) : '-' }}</td>
                            <td>{{ item.amount }} {{ getCurrencySymbol(customerReport.customer.currency) }}</td>
                        </tr>
                    </tbody>
                </table>
                <p v-else>{{ t('timetracking', 'Keine Daten vorhanden') }}</p>
            </div>
            
            <!-- All Customers Report -->
            <div v-if="allCustomersReport" class="report-result">
                <h3>{{ t('timetracking', 'Alle Kunden') }} - {{ allCustomersReport.period.label }}</h3>
                
                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="summary-label">{{ t('timetracking', 'Stunden (Ist)') }}</div>
                        <div class="summary-value">{{ allCustomersReport.totals.actualHours }} h</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">{{ t('timetracking', 'Abrechenbare Stunden') }}</div>
                        <div class="summary-value">{{ allCustomersReport.totals.billableHours }} h</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">{{ t('timetracking', 'Gesamtbetrag') }}</div>
                        <div class="summary-value">{{ allCustomersReport.totals.amount }} €</div>
                    </div>
                </div>
                
                <table v-if="allCustomersReport.customers.length > 0" class="all-customers-table">
                    <thead>
                        <tr>
                            <th>{{ t('timetracking', 'Kunde') }} / {{ t('timetracking', 'Projekt') }}</th>
                            <th class="text-right">{{ t('timetracking', 'Stunden (Ist)') }}</th>
                            <th class="text-right">{{ t('timetracking', 'Abrechenbar') }}</th>
                            <th class="text-right">{{ t('timetracking', 'Stundensatz') }}</th>
                            <th class="text-right">{{ t('timetracking', 'Betrag') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="item in allCustomersReport.customers" :key="item.customer.id">
                            <tr class="customer-row">
                                <td><strong>{{ item.customer.name }}</strong></td>
                                <td class="text-right"><strong>{{ item.actualHours }} h</strong></td>
                                <td class="text-right"><strong>{{ item.billableHours }} h</strong></td>
                                <td></td>
                                <td class="text-right"><strong>{{ item.amount }} {{ getCurrencySymbol(item.customer.currency) }}</strong></td>
                            </tr>
                            <tr v-for="proj in item.projects" :key="proj.project.id" class="project-row">
                                <td class="indent">{{ proj.project.name }}</td>
                                <td class="text-right">{{ proj.actualHours }} h</td>
                                <td class="text-right">{{ proj.billableHours }} h</td>
                                <td class="text-right">{{ proj.hourlyRate !== null && proj.hourlyRate !== undefined ? proj.hourlyRate + ' ' + getCurrencySymbol(item.customer.currency) : '-' }}</td>
                                <td class="text-right">{{ proj.amount }} {{ getCurrencySymbol(item.customer.currency) }}</td>
                            </tr>
                        </template>
                    </tbody>
                    <tfoot>
                        <tr class="grand-total-row">
                            <td><strong>{{ t('timetracking', 'Gesamt') }}</strong></td>
                            <td class="text-right"><strong>{{ allCustomersReport.totals.actualHours }} h</strong></td>
                            <td class="text-right"><strong>{{ allCustomersReport.totals.billableHours }} h</strong></td>
                            <td></td>
                            <td class="text-right"><strong>{{ allCustomersReport.totals.amount }} €</strong></td>
                        </tr>
                    </tfoot>
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
                        <option v-for="project in sortedProjects" :key="project.id" :value="project.id">
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
                        <option value="1">Q1 ({{ getMonthName(1).substring(0,3) }}-{{ getMonthName(3).substring(0,3) }})</option>
                        <option value="2">Q2 ({{ getMonthName(4).substring(0,3) }}-{{ getMonthName(6).substring(0,3) }})</option>
                        <option value="3">Q3 ({{ getMonthName(7).substring(0,3) }}-{{ getMonthName(9).substring(0,3) }})</option>
                        <option value="4">Q4 ({{ getMonthName(10).substring(0,3) }}-{{ getMonthName(12).substring(0,3) }})</option>
                    </select>
                </div>
                <div class="form-group form-group-button">
                    <label>&nbsp;</label>
                    <NcButton type="submit">{{ t('timetracking', 'Bericht Erstellen') }}</NcButton>
                </div>
            </form>
            
            <div v-if="projectReport" class="report-result">
                <h3>{{ projectReport.project.name }} - {{ projectReport.period.label }}</h3>
                <p>{{ t('timetracking', 'Kunde') }}: {{ projectReport.customer.name }}</p>
                
                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="summary-label">{{ t('timetracking', 'Stunden (Ist)') }}</div>
                        <div class="summary-value">{{ projectReport.totals.actualHours }} h</div>
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
                            <th>{{ t('timetracking', 'Stunden (Ist)') }}</th>
                            <th>{{ t('timetracking', 'Abrechenbare Stunden') }}</th>
                            <th>{{ t('timetracking', 'Einträge') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="user in projectReport.userSummary" :key="user.userId">
                            <td>{{ user.displayName }}</td>
                            <td>{{ user.actualHours }} h</td>
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
                    <label>{{ t('timetracking', 'Mitarbeiter') }}</label>
                    <select v-model="employeeReportForm.userId" @change="employeeReport = null">
                        <option value="">{{ t('timetracking', 'Alle Mitarbeiter') }}</option>
                        <option v-for="employee in sortedEmployees" :key="employee.id" :value="employee.id">
                            {{ employee.displayName }}
                        </option>
                    </select>
                </div>
                <div class="form-group">
                    <label>{{ t('timetracking', 'Zeitraum') }}</label>
                    <select v-model="employeeReportForm.periodType" @change="employeeReport = null">
                        <option value="month">{{ t('timetracking', 'Monat') }}</option>
                        <option value="quarter">{{ t('timetracking', 'Quartal') }}</option>
                        <option value="year">{{ t('timetracking', 'Jahr') }}</option>
                        <option value="custom">{{ t('timetracking', 'Benutzerdefiniert') }}</option>
                    </select>
                </div>
                <div class="form-group" v-if="employeeReportForm.periodType !== 'custom'">
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
                        <option value="1">Q1 ({{ getMonthName(1).substring(0,3) }}-{{ getMonthName(3).substring(0,3) }})</option>
                        <option value="2">Q2 ({{ getMonthName(4).substring(0,3) }}-{{ getMonthName(6).substring(0,3) }})</option>
                        <option value="3">Q3 ({{ getMonthName(7).substring(0,3) }}-{{ getMonthName(9).substring(0,3) }})</option>
                        <option value="4">Q4 ({{ getMonthName(10).substring(0,3) }}-{{ getMonthName(12).substring(0,3) }})</option>
                    </select>
                </div>
                <div class="form-group" v-if="employeeReportForm.periodType === 'custom'">
                    <label>{{ t('timetracking', 'Startdatum (inkl.)') }}</label>
                    <input v-model="employeeReportForm.startDate" type="date" required>
                </div>
                <div class="form-group" v-if="employeeReportForm.periodType === 'custom'">
                    <label>{{ t('timetracking', 'Enddatum (inkl.)') }}</label>
                    <input v-model="employeeReportForm.endDate" type="date" required>
                </div>
                <div class="form-group form-group-button">
                    <label>&nbsp;</label>
                    <NcButton type="submit">{{ t('timetracking', 'Bericht Erstellen') }}</NcButton>
                </div>
            </form>
            
            <!-- Single Employee Report -->
            <div v-if="employeeReport && !employeeReport.employees" class="report-result">
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
                    
                    <!-- Credited hours for vacation, sick days and public holidays -->
                    <template v-if="employeeReport.totals.creditedHours">
                        <br><br>
                        <strong>{{ t('timetracking', 'Gutgeschriebene Stunden:') }}</strong><br>
                        <span class="indent">
                            {{ t('timetracking', 'Urlaubstage') }}: {{ employeeReport.totals.creditedHours.vacationDays }} 
                            ({{ employeeReport.totals.creditedHours.vacationHours }} h)
                        </span><br>
                        <span class="indent">
                            {{ t('timetracking', 'Krankheitstage') }}: {{ employeeReport.totals.creditedHours.sickDays }} 
                            ({{ employeeReport.totals.creditedHours.sickDayHours }} h)
                        </span><br>
                        <span class="indent">
                            {{ t('timetracking', 'Feiertage') }}: {{ employeeReport.totals.creditedHours.publicHolidayDays }} 
                            ({{ employeeReport.totals.creditedHours.publicHolidayHours }} h)
                        </span><br>
                        <span class="indent">
                            <strong>{{ t('timetracking', 'Gesamt gutgeschrieben') }}:</strong> {{ employeeReport.totals.creditedHours.totalCreditedHours }} h
                        </span><br><br>
                        <strong>{{ t('timetracking', 'Effektive Stunden') }}:</strong> {{ employeeReport.totals.effectiveHours }} h
                        <span :class="{ 'positive': employeeReport.totals.balance >= 0, 'negative': employeeReport.totals.balance < 0 }">
                            ({{ employeeReport.totals.balance >= 0 ? '+' : '' }}{{ employeeReport.totals.balance }} h)
                        </span>
                    </template>
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
            
            <!-- All Employees Report -->
            <div v-if="employeeReport && employeeReport.employees" class="report-result">
                <h3>{{ t('timetracking', 'Mitarbeiter-Gesamtübersicht') }} {{ employeeReport.period.label }}</h3>
                
                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="summary-label">{{ t('timetracking', 'Gesamtstunden') }}</div>
                        <div class="summary-value">{{ employeeReport.totals.hours }} h</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">{{ t('timetracking', 'Arbeitstage') }}</div>
                        <div class="summary-value">{{ employeeReport.totals.workDays }}</div>
                    </div>
                    <div v-if="employeeReport.totals.revenue" class="summary-card">
                        <div class="summary-label">{{ t('timetracking', 'Gesamtumsatz') }}</div>
                        <div class="summary-value success">{{ employeeReport.totals.revenue }} €</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">{{ t('timetracking', 'Mitarbeiter') }}</div>
                        <div class="summary-value">{{ employeeReport.employees.length }}</div>
                    </div>
                </div>
                
                <h4>{{ t('timetracking', 'Mitarbeiter-Details') }}</h4>
                <table v-if="employeeReport.employees.length > 0">
                    <thead>
                        <tr>
                            <th>{{ t('timetracking', 'Mitarbeiter') }}</th>
                            <th>{{ t('timetracking', 'Stunden') }}</th>
                            <th>{{ t('timetracking', 'Arbeitstage') }}</th>
                            <th>{{ t('timetracking', 'Einträge') }}</th>
                            <th>{{ t('timetracking', 'Stundensatz') }}</th>
                            <th>{{ t('timetracking', 'Umsatz') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="emp in employeeReport.employees" :key="emp.userId">
                            <td>{{ emp.displayName }}</td>
                            <td>{{ emp.hours }} h</td>
                            <td>{{ emp.workDays }}</td>
                            <td>{{ emp.entryCount }}</td>
                            <td>{{ emp.hourlyRate ? emp.hourlyRate + ' €' : '-' }}</td>
                            <td>{{ emp.revenue ? emp.revenue + ' €' : '-' }}</td>
                        </tr>
                    </tbody>
                </table>
                <p v-else>{{ t('timetracking', 'Keine Mitarbeiter mit Einträgen in diesem Zeitraum') }}</p>
            </div>
            
            <!-- PDF Export Section -->
            <div class="export-section">
                <h3>{{ t('timetracking', 'PDF-Export (Arbeitszeitnachweis)') }}</h3>
                <p class="info-text">
                    {{ t('timetracking', 'Erstellen Sie einen PDF-Arbeitszeitnachweis zur Unterschrift für die Einhaltung des Arbeitszeitgesetzes (ArbZG).') }}
                </p>
                <form @submit.prevent="exportTimesheet" class="report-form">
                    <div class="form-group">
                        <label>{{ t('timetracking', 'Mitarbeiter') }}</label>
                        <select v-model="exportForm.userId" required>
                            <option value="">{{ t('timetracking', 'Bitte wählen') }}</option>
                            <option v-for="employee in sortedEmployees" :key="employee.id" :value="employee.id">
                                {{ employee.displayName }}
                            </option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>{{ t('timetracking', 'Zeitraum') }}</label>
                        <select v-model="exportForm.periodType">
                            <option value="month">{{ t('timetracking', 'Monat') }}</option>
                            <option value="year">{{ t('timetracking', 'Jahr') }}</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>{{ t('timetracking', 'Jahr') }}</label>
                        <input v-model.number="exportForm.year" type="number" :min="2020" :max="2030" required>
                    </div>
                    <div class="form-group" v-if="exportForm.periodType === 'month'">
                        <label>{{ t('timetracking', 'Monat') }}</label>
                        <select v-model.number="exportForm.month" required>
                            <option v-for="month in 12" :key="month" :value="month">{{ getMonthName(month) }}</option>
                        </select>
                    </div>
                    <div class="form-group form-group-button">
                        <label>&nbsp;</label>
                        <NcButton type="submit">
                            <template #icon>
                                <Download :size="20" />
                            </template>
                            {{ t('timetracking', 'PDF Herunterladen') }}
                        </NcButton>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Compliance Check -->
        <div v-if="activeTab === 'compliance'" class="report-section">
            <h2>{{ t('timetracking', 'Arbeitszeitgesetz-Prüfung') }}</h2>
            <p class="info-text">
                {{ t('timetracking', 'Prüfung der Einhaltung des deutschen Arbeitszeitgesetzes (ArbZG) für alle Mitarbeiter') }}:<br>
                • {{ t('timetracking', 'Max. 8 Stunden täglich (Regelarbeitszeit)') }}<br>
                • {{ t('timetracking', 'Max. 10 Stunden täglich (mit Ausgleich)') }}<br>
                • {{ t('timetracking', 'Max. 48 Stunden wöchentlich') }}<br>
                • {{ t('timetracking', 'Min. 11 Stunden Ruhezeit zwischen Arbeitstagen') }}
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
                    <NcButton type="submit">{{ t('timetracking', 'Prüfung Starten') }}</NcButton>
                </div>
            </form>
            
            <div v-if="complianceReport" class="report-result">
                <h3>{{ complianceReport.period.label }}</h3>
                
                <!-- Summary cards -->
                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="summary-label">{{ t('timetracking', 'Mitarbeiter geprüft') }}</div>
                        <div class="summary-value">{{ complianceReport.summary.totalEmployees }}</div>
                    </div>
                    <div class="summary-card" :class="{ 'card-success': complianceReport.summary.allCompliant }">
                        <div class="summary-label">{{ t('timetracking', 'Konform') }}</div>
                        <div class="summary-value">{{ complianceReport.summary.compliantCount }}</div>
                    </div>
                    <div class="summary-card" :class="{ 'card-danger': complianceReport.summary.nonCompliantCount > 0 }">
                        <div class="summary-label">{{ t('timetracking', 'Verstöße') }}</div>
                        <div class="summary-value">{{ complianceReport.summary.nonCompliantCount }}</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">{{ t('timetracking', 'Befreit') }}</div>
                        <div class="summary-value">{{ complianceReport.summary.exemptCount }}</div>
                    </div>
                </div>
                
                <!-- Employee list -->
                <div class="compliance-employees">
                    <div v-for="employee in complianceReport.employees" :key="employee.userId" class="compliance-employee">
                        <div class="compliance-employee-header" @click="toggleComplianceEmployee(employee.userId)">
                            <span class="toggle-icon">{{ expandedComplianceEmployees[employee.userId] ? '⮟' : '⮞' }}</span>
                            <span v-if="employee.exempt" class="status-icon exempt">ℹ️</span>
                            <span v-else-if="employee.compliant" class="status-icon compliant">✅</span>
                            <span v-else class="status-icon non-compliant">❌</span>
                            <strong>{{ employee.displayName }}</strong>
                            <span v-if="employee.exempt" class="status-badge exempt">{{ t('timetracking', 'Befreit') }}</span>
                            <span v-else-if="employee.compliant" class="status-badge compliant">{{ t('timetracking', 'Konform') }}</span>
                            <span v-else class="status-badge non-compliant">
                                {{ employee.violationCount }} {{ t('timetracking', 'Verstöße') }}, {{ employee.warningCount }} {{ t('timetracking', 'Warnungen') }}
                            </span>
                        </div>
                        
                        <div v-if="expandedComplianceEmployees[employee.userId]" class="compliance-employee-content">
                            <!-- Exempt info -->
                            <div v-if="employee.exempt" class="exempt-info">
                                <p>{{ employee.exemptReason }}</p>
                            </div>
                            
                            <!-- Statistics -->
                            <div v-else class="employee-statistics">
                                <div class="stat-row">
                                    <span>{{ t('timetracking', 'Gesamtstunden') }}:</span>
                                    <strong>{{ employee.statistics.totalHours }} h</strong>
                                </div>
                                <div class="stat-row">
                                    <span>{{ t('timetracking', 'Ø Stunden/Tag') }}:</span>
                                    <strong>{{ employee.statistics.averageDailyHours }} h</strong>
                                </div>
                                <div class="stat-row">
                                    <span>{{ t('timetracking', 'Max. Stunden/Tag') }}:</span>
                                    <strong>{{ employee.statistics.maxDailyHours }} h</strong>
                                </div>
                                
                                <!-- Violations -->
                                <div v-if="employee.violations && employee.violations.length > 0" class="violations-section">
                                    <h5>⚠️ {{ t('timetracking', 'Verstöße') }}</h5>
                                    <div v-for="(violation, index) in employee.violations" :key="'v' + index" class="violation-item high">
                                        <strong>{{ violation.type }}</strong><br>
                                        {{ violation.message }}<br>
                                        <small>
                                            <span v-if="violation.date">{{ t('timetracking', 'Datum') }}: {{ formatDate(violation.date) }}</span>
                                            <span v-if="violation.weekStart">{{ t('timetracking', 'Woche') }}: {{ formatDate(violation.weekStart) }} - {{ formatDate(violation.weekEnd) }}</span>
                                        </small>
                                    </div>
                                </div>
                                
                                <!-- Warnings -->
                                <div v-if="employee.warnings && employee.warnings.length > 0" class="warnings-section">
                                    <h5>⚡ {{ t('timetracking', 'Warnungen') }}</h5>
                                    <div v-for="(warning, index) in employee.warnings" :key="'w' + index" class="violation-item medium">
                                        <strong>{{ warning.type }}</strong><br>
                                        {{ warning.message }}<br>
                                        <small>
                                            <span v-if="warning.date">{{ t('timetracking', 'Datum') }}: {{ formatDate(warning.date) }}</span>
                                        </small>
                                    </div>
                                </div>
                                
                                <p v-if="employee.compliant && employee.violations.length === 0 && employee.warnings.length === 0" class="no-issues">
                                    ✅ {{ t('timetracking', 'Keine Verstöße oder Warnungen') }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <p v-if="complianceReport.employees.length === 0" class="no-data">
                    {{ t('timetracking', 'Keine Mitarbeiter mit Zeiteinträgen in diesem Zeitraum') }}
                </p>
            </div>
        </div>

        <!-- Overview -->
        <div v-if="activeTab === 'overview'" class="report-section">
            <h2>{{ t('timetracking', 'Übersicht') }}</h2>
            <form @submit.prevent="loadOverview" class="report-form">
                <div class="form-group">
                    <label>{{ t('timetracking', 'Zeitraum') }}</label>
                    <select v-model="overviewForm.periodType" @change="overview = null">
                        <option value="month">{{ t('timetracking', 'Monat') }}</option>
                        <option value="year">{{ t('timetracking', 'Jahr') }}</option>
                        <option value="total">{{ t('timetracking', 'Gesamt') }}</option>
                    </select>
                </div>
                <div class="form-group" v-if="overviewForm.periodType !== 'total'">
                    <label>{{ t('timetracking', 'Jahr') }}</label>
                    <input v-model.number="overviewForm.year" type="number" :min="2020" :max="2030" required>
                </div>
                <div class="form-group" v-if="overviewForm.periodType === 'month'">
                    <label>{{ t('timetracking', 'Monat') }}</label>
                    <select v-model.number="overviewForm.month" required>
                        <option v-for="month in 12" :key="month" :value="month">{{ getMonthName(month) }}</option>
                    </select>
                </div>
                <div class="form-group form-group-button">
                    <label>&nbsp;</label>
                    <NcButton type="submit">{{ t('timetracking', 'Übersicht Laden') }}</NcButton>
                </div>
            </form>
            
            <div v-if="overview" class="report-result">
                <h3>{{ overview.period.label }}</h3>
                
                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="summary-label">{{ t('timetracking', 'Gesamtstunden') }}</div>
                        <div class="summary-value">{{ formatHoursDecimal(overview.totals.hours) }} h</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">{{ t('timetracking', 'Kunden') }}</div>
                        <div class="summary-value">{{ overview.customers.length }}</div>
                    </div>
                </div>
                
                <div v-for="customerData in overview.customers" :key="customerData.customer.id" class="overview-customer">
                    <div class="overview-customer-header" @click="toggleOverviewCustomer(customerData.customer.id)">
                        <span class="toggle-icon">{{ expandedOverviewCustomers[customerData.customer.id] ? '⮟' : '⮞' }}</span>
                        <strong>{{ customerData.customer.name }}</strong>
                        <span class="overview-hours">{{ formatHoursDecimal(customerData.totals.hours) }} h</span>
                    </div>
                    
                    <div v-if="expandedOverviewCustomers[customerData.customer.id]" class="overview-customer-content">
                        <div v-for="projectData in customerData.projects" :key="projectData.project.id" class="overview-project">
                            <div class="overview-project-header" @click="toggleOverviewProject(projectData.project.id)">
                                <span class="toggle-icon">{{ expandedOverviewProjects[projectData.project.id] ? '⮟' : '⮞' }}</span>
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
                
                <p v-if="overview.customers.length === 0" class="no-data">
                    {{ t('timetracking', 'Keine Daten für diesen Zeitraum') }}
                </p>
            </div>
        </div>
    </div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import { getCurrentUser } from '@nextcloud/auth'
import { NcButton } from '@nextcloud/vue'
import Download from 'vue-material-design-icons/Download.vue'

export default {
    name: 'Reports',
    components: {
        NcButton,
        Download,
    },
    data() {
        const now = new Date()
        const currentQuarter = Math.ceil((now.getMonth() + 1) / 3)
        const y = now.getFullYear();
        const m = now.getMonth() + 1;
        return {
            activeTab: 'customer',
            customers: [],
            projects: [],
            employees: [],
            customerReport: null,
            allCustomersReport: null,
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
                userId: '',
                periodType: 'month',
                year: now.getFullYear(),
                month: now.getMonth() + 1,
                quarter: currentQuarter,
                // 15. des Vormonats bis 14. des aktuellen Monats
                startDate: `${m === 1 ? y - 1 : y}-${String(m === 1 ? 12 : m - 1).padStart(2, "0")}-15`,
                endDate:   `${y}-${String(m).padStart(2, "0")}-14`,
            },
            complianceReportForm: {
                periodType: 'month',
                year: now.getFullYear(),
                month: now.getMonth() + 1,
            },
            // Overview
            overviewForm: {
                periodType: 'month',
                year: now.getFullYear(),
                month: now.getMonth() + 1,
            },
            overview: null,
            expandedOverviewCustomers: {},
            expandedOverviewProjects: {},
            expandedComplianceEmployees: {},
            // Export
            exportForm: {
                userId: '',
                periodType: 'month',
                year: now.getFullYear(),
                month: now.getMonth() + 1,
            },
        }
    },
    computed: {
        sortedCustomers() {
            return [...this.customers].sort((a, b) =>
                a.name.localeCompare(b.name, undefined, { sensitivity: 'base' })
            )
        },
        sortedProjects() {
            return [...this.projects].sort((a, b) =>
                a.name.localeCompare(b.name, undefined, { sensitivity: 'base' })
            )
        },
        sortedEmployees() {
            return [...this.employees].sort((a, b) =>
                a.displayName.localeCompare(b.displayName, undefined, { sensitivity: 'base' })
            )
        },
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
        this.loadEmployees()
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
        async loadEmployees() {
            try {
                const response = await axios.get(generateUrl('/apps/timetracking/api/admin/users'))
                this.employees = response.data
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
                    periodType,
                    year,
                })
                if (periodType === 'month') params.append('month', month)
                if (periodType === 'quarter') params.append('quarter', quarter)
                
                let url
                if (customerId) {
                    // Single customer report
                    params.append('customerId', customerId)
                    url = `/apps/timetracking/api/reports/customer?${params.toString()}`
                    const response = await axios.get(generateUrl(url))
                    this.customerReport = response.data
                    this.allCustomersReport = null
                } else {
                    // All customers report
                    url = `/apps/timetracking/api/reports/all-customers?${params.toString()}`
                    const response = await axios.get(generateUrl(url))
                    this.allCustomersReport = response.data
                    this.customerReport = null
                }
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
                const { userId, periodType, year, month, quarter, startDate, endDate } = this.employeeReportForm
                const params = new URLSearchParams({
                    periodType,
                    year,
                })
                if (periodType === 'month') params.append('month', month)
                if (periodType === 'quarter') params.append('quarter', quarter)
                if (periodType === 'custom') {
                    params.append('startDate', startDate)
                    params.append('endDate', endDate)
                }
                
                let url
                if (userId) {
                    // Single employee report
                    params.append('userId', userId)
                    url = `/apps/timetracking/api/reports/employee?${params.toString()}`
                } else {
                    // All employees report
                    url = `/apps/timetracking/api/reports/all-employees?${params.toString()}`
                }
                
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
                const params = new URLSearchParams({
                    periodType,
                    year,
                })
                if (periodType === 'month') params.append('month', month)
                
                const url = `/apps/timetracking/api/reports/compliance?${params.toString()}`
                const response = await axios.get(generateUrl(url))
                this.complianceReport = response.data
                // Expand employees with violations by default
                this.expandedComplianceEmployees = {}
                response.data.employees.forEach(e => {
                    if (!e.compliant && !e.exempt) {
                        this.expandedComplianceEmployees[e.userId] = true
                    }
                })
            } catch (error) {
                showError(this.t('timetracking', 'Fehler beim Laden des Berichts'))
                console.error(error)
            }
        },
        toggleComplianceEmployee(userId) {
            this.expandedComplianceEmployees[userId] = !this.expandedComplianceEmployees[userId]
        },
        getMonthName(month) {
            const monthNames = [
                t('timetracking', 'Januar'),
                t('timetracking', 'Februar'),
                t('timetracking', 'März'),
                t('timetracking', 'April'),
                t('timetracking', 'Mai'),
                t('timetracking', 'Juni'),
                t('timetracking', 'Juli'),
                t('timetracking', 'August'),
                t('timetracking', 'September'),
                t('timetracking', 'Oktober'),
                t('timetracking', 'November'),
                t('timetracking', 'Dezember'),
            ]
            return monthNames[month - 1]
        },
        formatDate(dateStr) {
            return new Date(dateStr).toLocaleDateString()
        },
        getCurrencySymbol(code) {
            const currency = this.currencies.find(c => c.code === code)
            return currency ? currency.symbol : (code || '€')
        },
        async loadOverview() {
            try {
                const { periodType, year, month } = this.overviewForm
                const params = new URLSearchParams({ periodType })
                
                if (periodType !== 'total') {
                    params.append('year', year)
                }
                if (periodType === 'month') {
                    params.append('month', month)
                }
                
                const response = await axios.get(
                    generateUrl(`/apps/timetracking/api/reports/overview?${params.toString()}`)
                )
                this.overview = response.data
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
        async exportTimesheet() {
            if (!this.exportForm.userId) {
                showError(this.t('timetracking', 'Bitte wählen Sie einen Mitarbeiter'))
                return
            }
            
            try {
                const { userId, periodType, year, month } = this.exportForm
                let url
                
                if (periodType === 'year') {
                    url = generateUrl(`/apps/timetracking/api/export/timesheet/${userId}/${year}`)
                } else {
                    url = generateUrl(`/apps/timetracking/api/export/timesheet/${userId}/${year}/${month}`)
                }
                
                // Create a link and trigger download
                const link = document.createElement('a')
                link.href = url
                link.target = '_blank'
                document.body.appendChild(link)
                link.click()
                document.body.removeChild(link)
                
                showSuccess(this.t('timetracking', 'PDF-Export gestartet'))
            } catch (error) {
                showError(this.t('timetracking', 'Fehler beim Exportieren'))
                console.error(error)
            }
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

.export-section {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid var(--color-border);
}

.export-section h3 {
    margin-top: 0;
    margin-bottom: 10px;
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
    padding: 1rem;
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
    padding: 1rem;
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
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 20px;
    text-align: center;
}

.compliance-status.compliant {
    background: #d4edda;
    color: #155724;
}

.compliance-status.exempt {
    background: #e2e3e5;
    color: #383d41;
    border: 2px solid #6c757d;
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

/* All Customers Report Styles */
.all-customers-table {
    width: 100%;
    border-collapse: collapse;
}

.all-customers-table th,
.all-customers-table td {
    padding: 8px 12px;
    border-bottom: 1px solid var(--color-border);
}

.all-customers-table th {
    text-align: left;
    background: var(--color-background-dark);
    font-weight: 600;
}

.all-customers-table th.text-right,
.all-customers-table td.text-right {
    text-align: right;
}

.all-customers-table .customer-row {
    background: var(--color-background-dark);
}

.all-customers-table .customer-row td {
    padding-top: 12px;
    padding-bottom: 12px;
    border-top: 2px solid var(--color-border);
}

.all-customers-table .project-row td {
    background: var(--color-main-background);
}

.all-customers-table .project-row td.indent {
    padding-left: 30px;
}

.all-customers-table tfoot .grand-total-row td {
    background: var(--color-primary-element-light);
    border-top: 2px solid var(--color-border);
    font-size: 1.05em;
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
    padding: 1rem;
}

.indent {
    margin-left: 15px;
}

.positive {
    color: #28a745;
    font-weight: bold;
}

.negative {
    color: #dc3545;
    font-weight: bold;
}

/* Compliance Employee List Styles */
.compliance-employees {
    margin-top: 20px;
}

.compliance-employee {
    margin-bottom: 8px;
    border: 1px solid var(--color-border);
    border-radius: 8px;
    overflow: hidden;
}

.compliance-employee-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 15px;
    background: var(--color-main-background);
    cursor: pointer;
}

.compliance-employee-header:hover {
    background: var(--color-background-hover);
}

.compliance-employee-content {
    padding: 15px;
    background: var(--color-background-dark);
}

.status-icon {
    font-size: 16px;
}

.status-badge {
    margin-left: auto;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: normal;
}

.status-badge.compliant {
    background: #d4edda;
    color: #155724;
}

.status-badge.non-compliant {
    background: #f8d7da;
    color: #721c24;
}

.status-badge.exempt {
    background: #e2e3e5;
    color: #383d41;
}

.exempt-info {
    padding: 10px;
    background: var(--color-main-background);
    border-radius: 4px;
    color: var(--color-text-maxcontrast);
    font-style: italic;
}

.employee-statistics {
    padding: 10px;
    background: var(--color-main-background);
    border-radius: 4px;
}

.stat-row {
    display: flex;
    justify-content: space-between;
    padding: 5px 0;
    border-bottom: 1px solid var(--color-border);
}

.stat-row:last-child {
    border-bottom: none;
}

.employee-statistics .violations-section,
.employee-statistics .warnings-section {
    margin-top: 15px;
}

.employee-statistics h5 {
    margin: 0 0 10px 0;
    font-size: 14px;
}

.no-issues {
    color: #155724;
    margin: 10px 0 0 0;
    padding: 10px;
    background: #d4edda;
    border-radius: 4px;
}

.summary-card.card-success {
    border-color: #28a745;
    background: #d4edda;
}

.summary-card.card-success .summary-value {
    color: #155724;
}

.summary-card.card-danger {
    border-color: #dc3545;
    background: #f8d7da;
}

.summary-card.card-danger .summary-value {
    color: #721c24;
}
</style>
