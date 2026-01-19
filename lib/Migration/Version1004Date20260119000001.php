<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Data migration to copy datetime values to new timestamp columns
 */
class Version1004Date20260119000001 extends SimpleMigrationStep {
    
    private IDBConnection $db;
    
    public function __construct(IDBConnection $db) {
        $this->db = $db;
    }
    
    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
        // Migrate existing data: convert start_time and end_time to Unix timestamps
        $qb = $this->db->getQueryBuilder();
        $qb->select('id', 'start_time', 'end_time')
            ->from('tt_entries');
        
        $result = $qb->executeQuery();
        
        while ($row = $result->fetch()) {
            $updateQb = $this->db->getQueryBuilder();
            $updateQb->update('tt_entries');
            
            if ($row['start_time']) {
                $startTimestamp = strtotime($row['start_time']);
                if ($startTimestamp !== false) {
                    $updateQb->set('start_timestamp', $updateQb->createNamedParameter($startTimestamp));
                }
            }
            
            if ($row['end_time']) {
                $endTimestamp = strtotime($row['end_time']);
                if ($endTimestamp !== false) {
                    $updateQb->set('end_timestamp', $updateQb->createNamedParameter($endTimestamp));
                }
            }
            
            $updateQb->where($updateQb->expr()->eq('id', $updateQb->createNamedParameter($row['id'])));
            $updateQb->executeStatement();
        }
        
        $result->closeCursor();
    }
}
