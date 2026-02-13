<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Create sick days table (tt_sick_days) for tracking employee sick leave.
 * Under German law (Entgeltfortzahlungsgesetz - EFZG), employers must continue
 * paying wages for up to 6 weeks (42 calendar days) of illness per case.
 */
class Version1011Date20260213000000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('tt_sick_days')) {
            $table = $schema->createTable('tt_sick_days');

            $table->addColumn('id', Types::BIGINT, [
                'autoincrement' => true,
                'notnull' => true,
                'length' => 8,
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
                'default' => 1,
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
            $table->addIndex(['user_id'], 'tt_sick_user_idx');
            $table->addIndex(['start_date'], 'tt_sick_start_idx');
        }

        return $schema;
    }
}
