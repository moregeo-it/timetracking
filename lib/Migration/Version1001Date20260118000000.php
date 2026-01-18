<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Migration to add currency field to customers table
 */
class Version1001Date20260118000000 extends SimpleMigrationStep {
    
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        // Add currency column to customers table
        if ($schema->hasTable('tt_customers')) {
            $table = $schema->getTable('tt_customers');
            
            if (!$table->hasColumn('currency')) {
                $table->addColumn('currency', Types::STRING, [
                    'notnull' => false,
                    'length' => 3,
                    'default' => 'EUR',
                ]);
            }
        }

        return $schema;
    }
}
