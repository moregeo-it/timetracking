<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Migration to remove old columns after data migration
 */
class Version1005Date20260119000002 extends SimpleMigrationStep {
    
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('tt_entries')) {
            $table = $schema->getTable('tt_entries');
            
            // Remove old columns
            if ($table->hasColumn('date')) {
                $table->dropColumn('date');
            }
            
            if ($table->hasColumn('start_time')) {
                $table->dropColumn('start_time');
            }
            
            if ($table->hasColumn('end_time')) {
                $table->dropColumn('end_time');
            }
            
            // Remove old date index if it exists
            if ($table->hasIndex('idx_entry_date')) {
                $table->dropIndex('idx_entry_date');
            }
            
            // Add index on start_timestamp for efficient date range queries
            if (!$table->hasIndex('idx_entry_start_ts')) {
                $table->addIndex(['start_timestamp'], 'idx_entry_start_ts');
            }
        }

        return $schema;
    }
}
