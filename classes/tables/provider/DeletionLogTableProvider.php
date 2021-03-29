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
}
