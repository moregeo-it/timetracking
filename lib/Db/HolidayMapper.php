<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class HolidayMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'tt_holidays', Holiday::class);
    }

    public function find(int $id): Holiday {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
        return $this->findEntity($qb);
    }

    public function findAll(?string $region = null): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->orderBy('date', 'ASC');
        
        if ($region !== null) {
            $qb->where($qb->expr()->eq('region', $qb->createNamedParameter($region)));
        }
        
        return $this->findEntities($qb);
    }

    public function findByYear(int $year, ?string $region = null): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->like('date', $qb->createNamedParameter($year . '%')))
            ->orderBy('date', 'ASC');
        
        if ($region !== null) {
            $qb->andWhere($qb->expr()->eq('region', $qb->createNamedParameter($region)));
        }
        
        return $this->findEntities($qb);
    }
}
