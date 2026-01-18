<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Migration to add start_date and end_date fields to projects table
 */
class Version1002Date20260118000001 extends SimpleMigrationStep {
    
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        // Add start_date and end_date columns to projects table
        if ($schema->hasTable('tt_projects')) {
            $table = $schema->getTable('tt_projects');
            
            if (!$table->hasColumn('start_date')) {
                $table->addColumn('start_date', Types::STRING, [
                    'notnull' => false,
                    'length' => 10,
                    'default' => null,
                ]);
            }
            
            if (!$table->hasColumn('end_date')) {
                $table->addColumn('end_date', Types::STRING, [
                    'notnull' => false,
                    'length' => 10,
                    'default' => null,
                ]);
            }
        }

        return $schema;
    }
}
