<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1000Date20260104000000 extends SimpleMigrationStep {
    
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        // Customers table
        if (!$schema->hasTable('tt_customers')) {
            $table = $schema->createTable('tt_customers');
            $table->addColumn('id', Types::BIGINT, [
                'autoincrement' => true,
                'notnull' => true,
                'unsigned' => true,
            ]);
            $table->addColumn('name', Types::STRING, [
                'notnull' => true,
                'length' => 255,
            ]);
            $table->addColumn('active', Types::BOOLEAN, [
			          'notnull' => false,
                'default' => true,
            ]);
            $table->addColumn('created_at', Types::DATETIME, [
                'notnull' => true,
            ]);
            $table->addColumn('updated_at', Types::DATETIME, [
                'notnull' => true,
            ]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['active'], 'idx_customer_active');
        }

        // Projects table
        if (!$schema->hasTable('tt_projects')) {
            $table = $schema->createTable('tt_projects');
            $table->addColumn('id', Types::BIGINT, [
                'autoincrement' => true,
                'notnull' => true,
                'unsigned' => true,
            ]);
            $table->addColumn('customer_id', Types::BIGINT, [
                'notnull' => true,
                'unsigned' => true,
            ]);
            $table->addColumn('name', Types::STRING, [
                'notnull' => true,
                'length' => 255,
            ]);
            $table->addColumn('description', Types::TEXT, [
                'notnull' => false,
            ]);
            $table->addColumn('hourly_rate', Types::DECIMAL, [
                'notnull' => false,
                'precision' => 10,
                'scale' => 2,
            ]);
            $table->addColumn('budget_hours', Types::DECIMAL, [
                'notnull' => false,
                'precision' => 10,
                'scale' => 2,
            ]);
            $table->addColumn('active', Types::BOOLEAN, [
			          'notnull' => false,
                'default' => true,
            ]);
            $table->addColumn('created_at', Types::DATETIME, [
                'notnull' => true,
            ]);
            $table->addColumn('updated_at', Types::DATETIME, [
                'notnull' => true,
            ]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['customer_id'], 'idx_project_customer');
            $table->addIndex(['active'], 'idx_project_active');
        }

        // Time entries table
        if (!$schema->hasTable('tt_entries')) {
            $table = $schema->createTable('tt_entries');
            $table->addColumn('id', Types::BIGINT, [
                'autoincrement' => true,
                'notnull' => true,
                'unsigned' => true,
            ]);
            $table->addColumn('project_id', Types::BIGINT, [
                'notnull' => true,
                'unsigned' => true,
            ]);
            $table->addColumn('user_id', Types::STRING, [
                'notnull' => true,
                'length' => 64,
            ]);
            $table->addColumn('date', Types::DATE, [
                'notnull' => true,
            ]);
            $table->addColumn('start_time', Types::DATETIME, [
                'notnull' => true,
            ]);
            $table->addColumn('end_time', Types::DATETIME, [
                'notnull' => false,
            ]);
            $table->addColumn('duration_minutes', Types::INTEGER, [
                'notnull' => false,
            ]);
            $table->addColumn('description', Types::TEXT, [
                'notnull' => false,
            ]);
            $table->addColumn('billable', Types::BOOLEAN, [
			          'notnull' => false,
                'default' => true,
            ]);
            $table->addColumn('created_at', Types::DATETIME, [
                'notnull' => true,
            ]);
            $table->addColumn('updated_at', Types::DATETIME, [
                'notnull' => true,
            ]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['project_id'], 'idx_entry_project');
            $table->addIndex(['user_id'], 'idx_entry_user');
            $table->addIndex(['date'], 'idx_entry_date');
        }

        // Vacations table
        if (!$schema->hasTable('tt_vacations')) {
            $table = $schema->createTable('tt_vacations');
            $table->addColumn('id', Types::BIGINT, [
                'autoincrement' => true,
                'notnull' => true,
                'unsigned' => true,
            ]);
            $table->addColumn('user_id', Types::STRING, [
                'notnull' => true,
                'length' => 64,
            ]);
            $table->addColumn('start_date', Types::DATE, [
                'notnull' => true,
            ]);
            $table->addColumn('end_date', Types::DATE, [
                'notnull' => true,
            ]);
            $table->addColumn('days', Types::INTEGER, [
                'notnull' => true,
            ]);
            $table->addColumn('status', Types::STRING, [
                'notnull' => true,
                'length' => 20,
                'default' => 'pending',
                'comment' => 'pending, approved, rejected',
            ]);
            $table->addColumn('notes', Types::TEXT, [
                'notnull' => false,
            ]);
            $table->addColumn('created_at', Types::DATETIME, [
                'notnull' => true,
            ]);
            $table->addColumn('updated_at', Types::DATETIME, [
                'notnull' => true,
            ]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['user_id'], 'idx_vacation_user');
            $table->addIndex(['start_date'], 'idx_vacation_start');
            $table->addIndex(['status'], 'idx_vacation_status');
        }

        // Employee settings table (for German labor law compliance)
        if (!$schema->hasTable('tt_emp_settings')) {
            $table = $schema->createTable('tt_emp_settings');
            $table->addColumn('id', Types::BIGINT, [
                'autoincrement' => true,
                'notnull' => true,
                'unsigned' => true,
            ]);
            $table->addColumn('user_id', Types::STRING, [
                'notnull' => true,
                'length' => 64,
            ]);
            $table->addColumn('employment_type', Types::STRING, [
                'notnull' => true,
                'length' => 20,
                'default' => 'contract',
                'comment' => 'director, contract (employee), freelance (hourly contingent), or student',
            ]);
            $table->addColumn('weekly_hours', Types::DECIMAL, [
                'notnull' => true,
                'precision' => 5,
                'scale' => 2,
                'default' => '40.00',
                'comment' => 'Contractual weekly hours for contract employees',
            ]);
            $table->addColumn('max_total_hours', Types::DECIMAL, [
                'notnull' => false,
                'precision' => 10,
                'scale' => 2,
                'comment' => 'Maximum total hours for freelance/contingent workers',
            ]);
            $table->addColumn('vacation_days_per_year', Types::INTEGER, [
                'notnull' => true,
                'default' => 20,
                'comment' => 'Vacation days per year for this employee',
            ]);
            $table->addColumn('hourly_rate', Types::DECIMAL, [
                'notnull' => false,
                'precision' => 10,
                'scale' => 2,
                'comment' => 'Hourly rate for billing customers',
            ]);
            $table->addColumn('federal_state', Types::STRING, [
                'notnull' => false,
                'length' => 2,
                'comment' => 'German federal state for holiday calculation',
            ]);
            $table->addColumn('employment_start', Types::DATE, [
                'notnull' => false,
            ]);
            $table->addColumn('created_at', Types::DATETIME, [
                'notnull' => true,
            ]);
            $table->addColumn('updated_at', Types::DATETIME, [
                'notnull' => true,
            ]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['user_id'], 'idx_employee_user_unique');
        }

        return $schema;
    }
}

