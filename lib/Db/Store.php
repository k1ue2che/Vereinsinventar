<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
namespace OCA\Vereinsinventar\Db;
use OCP\IDBConnection;
use OCP\DB\QueryBuilder\IQueryBuilder;
class Store {
    public function __construct(private IDBConnection $db) {}
    public function all(string $groupId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from('vi_nodes')->where($qb->expr()->eq('group_id', $qb->createNamedParameter($groupId)))->orderBy('name', 'ASC');
        $result = $qb->executeQuery();
        try {
            return array_map([$this, 'normalize'], $result->fetchAll());
        } finally {
            $result->closeCursor();
        }
    }
    public function get(string $groupId, int $id): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from('vi_nodes')->where($qb->expr()->eq('group_id', $qb->createNamedParameter($groupId)))
            ->andWhere($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
        $result = $qb->executeQuery();
        try {
            $row = $result->fetch();
        } finally {
            $result->closeCursor();
        }
        if ($row === false) {
            throw new \DomainException('Eintrag nicht gefunden.', 404);
        }
        return $this->normalize($row);
    }
    public function save(string $groupId, int $id, array $data): void {
        $qb = $this->db->getQueryBuilder();
        if ($id === 0) {
            $qb->insert('vi_nodes')->setValue('group_id', $qb->createNamedParameter($groupId));
            foreach ($data as $key => $value) {
                $qb->setValue($key, $qb->createNamedParameter($value, is_int($value) ? IQueryBuilder::PARAM_INT : IQueryBuilder::PARAM_STR));
            }
        } else {
            $qb->update('vi_nodes')->where($qb->expr()->eq('group_id', $qb->createNamedParameter($groupId)))
                ->andWhere($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
            foreach ($data as $key => $value) {
                $qb->set($key, $qb->createNamedParameter($value, is_int($value) ? IQueryBuilder::PARAM_INT : IQueryBuilder::PARAM_STR));
            }
        }
        $qb->executeStatement();
    }
    public function remove(string $groupId, int $id): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete('vi_nodes')->where($qb->expr()->eq('group_id', $qb->createNamedParameter($groupId)))
            ->andWhere($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('kind', $qb->createNamedParameter('item')));
        $qb->executeStatement();
    }
    private function normalize(array $row): array {
        foreach (['id', 'parent_id', 'quantity'] as $key) {
            $row[$key] = (int)$row[$key];
        }
        return $row;
    }
}
