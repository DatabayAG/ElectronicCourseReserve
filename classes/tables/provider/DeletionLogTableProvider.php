<?php declare(strict_types=1);
/* Copyright (c) 1998-2021 ILIAS open source, Extended GPL, see docs/LICENSE */

use ILIAS\Plugin\ElectronicCourseReserve\UI\Table\Data\DatabaseProvider;

/**
 * Class DeletionLogTableProvider
 * @author Michael Jansen <mjansen@databay.de>
 */
class DeletionLogTableProvider extends DatabaseProvider
{
    /**
     * @inheritDoc
     */
    protected function getSelectPart(array $params, array $filter) : string
    {
        $fields = [
            'del_log.deletion_mode',
            'del_log.deletion_timestamp',
            'del_log.deletion_message',
            'del_log.metadata result',
            'del_log.crs_ref_id',
            'del_log.folder_ref_id',
            'crs_od.title crs_title',
            'fold_od.title fold_title',
            'IF(crs_tree.tree = 1 AND crs_ref.deleted IS NULL, 0, 1) is_crs_deleted',
            'IF(fold_tree.tree = 1 AND fold_ref.deleted IS NULL, 0, 1) is_fold_deleted',
        ];

        return implode(', ', $fields);
    }

    /**
     * @inheritDoc
     */
    protected function getFromPart(array $params, array $filter) : string
    {
        $joins = [];

        $joins[] = 'LEFT JOIN object_reference crs_ref ON crs_ref.ref_id = del_log.crs_ref_id';
        $joins[] = 'LEFT JOIN object_data crs_od ON crs_od.obj_id = crs_ref.obj_id';
        $joins[] = 'LEFT JOIN tree crs_tree ON crs_tree.child = crs_ref.ref_id';

        $joins[] = 'LEFT JOIN object_reference fold_ref ON fold_ref.ref_id = del_log.folder_ref_id';
        $joins[] = 'LEFT JOIN object_data fold_od ON fold_od.obj_id = fold_ref.obj_id';
        $joins[] = 'LEFT JOIN tree fold_tree ON fold_tree.child = crs_ref.ref_id';

        return 'ecr_deletion_log del_log ' . implode(' ', $joins);
    }

    /**
     * @inheritDoc
     */
    protected function getWherePart(array $params, array $filter) : string
    {
        $where = [];

        if (isset($filter['period']) && is_array($filter['period'])) {
            $where[] = '(' . implode(' AND ', [
                'del_log.deletion_timestamp >= ' . $this->db->quote($filter['period']['start'], 'integer'),
                'del_log.deletion_timestamp <= ' . $this->db->quote($filter['period']['end'], 'integer')
            ]) . ')';
        }

        $crsFilterSet = (
            isset($filter['crs_title']) &&
            is_string($filter['crs_title']) &&
            strlen($filter['crs_title']) > 0
        );
        if ($crsFilterSet) {
            $where[] = $this->db->like('crs_od.title', 'text', '%%' . $filter['crs_title'] . '%%');
        }

        $foldFilterSet = (
            isset($filter['fold_title']) &&
            is_string($filter['fold_title']) &&
            strlen($filter['fold_title']) > 0
        );
        if ($foldFilterSet) {
            $where[] = $this->db->like('fold_od.title', 'text', '%%' . $filter['fold_title'] . '%%');
        }

        return implode(' AND ', $where);
    }

    /**
     * @inheritDoc
     */
    protected function getGroupByPart(array $params, array $filter) : string
    {
        return '';
    }

    /**
     * @inheritDoc
     */
    protected function getHavingPart(array $params, array $filter) : string
    {
        return '';
    }

    /**
     * @inheritDoc
     */
    protected function getOrderByPart(array $params, array $filter) : string
    {
        if (isset($params['order_field'])) {
            if (!is_string($params['order_field'])) {
                throw new InvalidArgumentException('Please provide a valid order field.');
            }

            if (
                !in_array($params['order_field'],
                ['crs_title', 'fold_title', 'deletion_timestamp', 'deletion_mode'])
            ) {
                $params['order_field'] = 'deletion_timestamp';
            }

            if ('crs_title' === $params['order_field']) {
                $params['order_field'] = 'crs_od.title';
            } elseif ('fold_title' === $params['order_field']) {
                $params['order_field'] = 'fold_od.title';
            }

            if (!isset($params['order_direction'])) {
                $params['order_direction'] = 'ASC';
            } else {
                if (!in_array(strtolower($params['order_direction']), ['asc', 'desc'])) {
                    throw new InvalidArgumentException('Please provide a valid order direction.');
                }
            }

            return $params['order_field'] . ' ' . strtoupper($params['order_direction']);
        }

        return '';
    }

    /**
     * @param string $term
     * @param string $objectType
     * @return string[]
     */
    public function getListOfLoggedObjectTitles(string $term, string $objectType) : array
    {
        $joinColumn = 'ecr_deletion_log.crs_ref_id ';
        if ('fold' === $objectType) {
            $joinColumn = 'ecr_deletion_log.folder_ref_id ';
        }

        $query = '
			SELECT DISTINCT(od.title)
			FROM ecr_deletion_log
			INNER JOIN object_reference oref ON oref.ref_id = ' . $joinColumn . ' AND oref.deleted IS NULL
			INNER JOIN object_data od ON od.obj_id = oref.obj_id AND od.type = %s
			INNER JOIN tree ON tree.child = oref.ref_id AND tree = 1
			WHERE 1 = 1 AND
		';
        $query .= $this->db->like('od.title', 'text', '%%' . $term . '%%');
        $query .= ' ORDER BY od.title DESC';

        $res = $this->db->queryF(
            $query,
            ['text'],
            [$objectType]
        );

        $values = [];
        while ($row = $this->db->fetchAssoc($res)) {
            $values[] = $row['title'];
        }

        return $values;
    }
}
