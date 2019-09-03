<?php

/**
 * Class ilElectronicCourseReserveAgreementTableProvider
 * @author Nadia Matuschek <nmatuschek@databay.de>
 */
class ilElectronicCourseReserveAgreementTableProvider
{
    protected $db;

    public function __construct()
    {
        global $DIC;

        $this->db = $DIC->database();
    }

    public function getTableData()
    {
        $res = $this->db->queryF('SELECT * FROM ecr_lang_agreements WHERE is_active = %s ORDER BY time_created DESC',
            array('integer'), array(1));

        while ($row = $this->db->fetchAssoc($res)) {
            $data[] = $row;
        }

        return $data;
    }
}