<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Create table for default employee category multipliers.
 * These are system-wide defaults that apply when no project-specific multiplier is set.
 */
class Version1012Date20260201000001 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        // Create table for default multipliers
        if (!$schema->hasTable('tt_default_multipliers')) {
            $table = $schema->createTable('tt_default_multipliers');
            
            $table->addColumn('id', Types::BIGINT, [
                'autoincrement' => true,
                'notnull' => true,
                'unsigned' => true,
            ]);
            
            $table->addColumn('employment_type', Types::STRING, [
                'notnull' => true,
                'length' => 32,
            ]);
            
            $table->addColumn('multiplier', Types::FLOAT, [
                'notnull' => true,
                'default' => 1.0,
            ]);
            
            $table->setPrimaryKey(['id']);
            
            // Unique constraint: one default multiplier per employment type
            $table->addUniqueIndex(['employment_type'], 'tt_default_mult_type_unique');
        }

        return $schema;
    }
}
