<?php
/**
 * Created by PhpStorm.
 * User: nmatuschek
 * Date: 29.05.18
 * Time: 09:35
 */
class ilElectronicCourseReserveLangTableProvider
{
	protected $db;

	public function __construct()
	{
		global $DIC;

		$this->db = $DIC->database();
	}

	public function getTableData()
	{
		$data = array();
		$installed_langs = ilLanguage::_getInstalledLanguages();
		foreach($installed_langs as $lang)
		{
			$data[$lang]['lang_key']	= $lang;
			$data[$lang]['identifier'] = 'ecr_tab_title';
		}

		$query = 'SELECT * FROM ecr_lang_data ';
		$res   = $this->db->query($query);

		while($row = $this->db->fetchAssoc($res))
		{
			if(isset($data[$row['lang_key']]))
			{
				$data[$row['lang_key']] = $row;
			}
		}

		return $data;
	}
}