<?php
return [
    'routes' => [
        // Page routes
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        
        // Customer routes
        ['name' => 'customer#index', 'url' => '/api/customers', 'verb' => 'GET'],
        ['name' => 'customer#show', 'url' => '/api/customers/{id}', 'verb' => 'GET'],
        ['name' => 'customer#create', 'url' => '/api/customers', 'verb' => 'POST'],
        ['name' => 'customer#update', 'url' => '/api/customers/{id}', 'verb' => 'PUT'],
        ['name' => 'customer#delete', 'url' => '/api/customers/{id}', 'verb' => 'DELETE'],
        
        // Project routes
        ['name' => 'project#index', 'url' => '/api/projects', 'verb' => 'GET'],
        ['name' => 'project#show', 'url' => '/api/projects/{id}', 'verb' => 'GET'],
        ['name' => 'project#create', 'url' => '/api/projects', 'verb' => 'POST'],
        ['name' => 'project#update', 'url' => '/api/projects/{id}', 'verb' => 'PUT'],
        ['name' => 'project#delete', 'url' => '/api/projects/{id}', 'verb' => 'DELETE'],
        
        // Time entry routes
        ['name' => 'time_entry#index', 'url' => '/api/time-entries', 'verb' => 'GET'],
        ['name' => 'time_entry#show', 'url' => '/api/time-entries/{id}', 'verb' => 'GET'],
        ['name' => 'time_entry#create', 'url' => '/api/time-entries', 'verb' => 'POST'],
        ['name' => 'time_entry#update', 'url' => '/api/time-entries/{id}', 'verb' => 'PUT'],
        ['name' => 'time_entry#delete', 'url' => '/api/time-entries/{id}', 'verb' => 'DELETE'],
        ['name' => 'time_entry#start_timer', 'url' => '/api/time-entries/start', 'verb' => 'POST'],
        ['name' => 'time_entry#stop_timer', 'url' => '/api/time-entries/stop', 'verb' => 'POST'],
        
        // Report routes
        ['name' => 'report#customer_monthly', 'url' => '/api/reports/customer/{customerId}/{year}/{month}', 'verb' => 'GET'],
        ['name' => 'report#customer_report', 'url' => '/api/reports/customer', 'verb' => 'GET'],
        ['name' => 'report#project_monthly', 'url' => '/api/reports/project/{projectId}/{year}/{month}', 'verb' => 'GET'],
        ['name' => 'report#project_report', 'url' => '/api/reports/project', 'verb' => 'GET'],
        ['name' => 'report#employee_monthly', 'url' => '/api/reports/employee/{userId}/{year}/{month}', 'verb' => 'GET'],
        ['name' => 'report#employee_report', 'url' => '/api/reports/employee', 'verb' => 'GET'],
        ['name' => 'report#compliance_check', 'url' => '/api/reports/compliance/{userId}/{year}/{month}', 'verb' => 'GET'],
        ['name' => 'report#compliance_report', 'url' => '/api/reports/compliance', 'verb' => 'GET'],
        ['name' => 'report#monthly_overview', 'url' => '/api/reports/monthly-overview/{year}/{month}', 'verb' => 'GET'],
        
        // Vacation routes
        ['name' => 'vacation#index', 'url' => '/api/vacations', 'verb' => 'GET'],
        ['name' => 'vacation#show', 'url' => '/api/vacations/{id}', 'verb' => 'GET'],
        ['name' => 'vacation#create', 'url' => '/api/vacations', 'verb' => 'POST'],
        ['name' => 'vacation#update', 'url' => '/api/vacations/{id}', 'verb' => 'PUT'],
        ['name' => 'vacation#destroy', 'url' => '/api/vacations/{id}', 'verb' => 'DELETE'],
        ['name' => 'vacation#balance', 'url' => '/api/vacations/balance/{year}', 'verb' => 'GET'],
        ['name' => 'vacation#calendar', 'url' => '/api/vacations/calendar/{year}/{month}', 'verb' => 'GET'],
        ['name' => 'vacation#pending', 'url' => '/api/vacations/pending', 'verb' => 'GET'],
        ['name' => 'vacation#approve', 'url' => '/api/vacations/{id}/approve', 'verb' => 'POST'],
        ['name' => 'vacation#reject', 'url' => '/api/vacations/{id}/reject', 'verb' => 'POST'],
        
        // Employee settings routes
        ['name' => 'employee_settings#get', 'url' => '/api/employee-settings', 'verb' => 'GET'],
        ['name' => 'employee_settings#update', 'url' => '/api/employee-settings', 'verb' => 'PUT'],
        ['name' => 'employee_settings#get_user', 'url' => '/api/employee-settings/{userId}', 'verb' => 'GET'],
        
        // Admin routes
        ['name' => 'admin#users', 'url' => '/api/admin/users', 'verb' => 'GET'],
        ['name' => 'admin#time_entries', 'url' => '/api/admin/time-entries', 'verb' => 'GET'],
    ]
];
