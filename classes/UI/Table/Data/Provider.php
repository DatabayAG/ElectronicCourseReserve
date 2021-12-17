<?php declare(strict_types=1);
/* Copyright (c) 1998-2021 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace ILIAS\Plugin\ElectronicCourseReserve\UI\Table\Data;

/**
 * Interface Provider
 * @package ILIAS\Plugin\ElectronicCourseReserve\UI\Table\Data
 * @author Michael Jansen <mjansen@databay.de>
 */
interface Provider
{
    /**
     * @param array<string, mixed> $params Table parameters like limit or order
     * @param array<string, mixed> $filter Filter settings provided by a ilTable2GUI instance
     * @return array<array<string, mixed>> An associative array with keys 'items' (array of items) and 'cnt' (number of total items)
     */
    public function getList(array $params, array $filter) : array;
}
