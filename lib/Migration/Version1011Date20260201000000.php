<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Create normalized table for employee category multipliers per project.
 * This allows flexible addition/removal of employment categories without schema changes.
 * 
 * Employment types: director, contract, freelance, intern, student
 * Multipliers must be > 0 and <= 2, default is 1.
 */
class Version1011Date20260201000000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        // Create normalized table for project multipliers
        if (!$schema->hasTable('tt_project_multipliers')) {
            $table = $schema->createTable('tt_project_multipliers');
            
            $table->addColumn('id', Types::BIGINT, [
                'autoincrement' => true,
                'notnull' => true,
                'unsigned' => true,
            ]);
            
            $table->addColumn('project_id', Types::BIGINT, [
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
            
            // Unique constraint: one multiplier per project per employment type
            $table->addUniqueIndex(['project_id', 'employment_type'], 'tt_proj_mult_unique');
            
            // Index for faster lookups by project
            $table->addIndex(['project_id'], 'tt_proj_mult_project_idx');
        }

        return $schema;
    }
}
