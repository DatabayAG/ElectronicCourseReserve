<?php
/**
 * Created by PhpStorm.
 * User: nmatuschek
 * Date: 28.05.18
 * Time: 10:24
 */

class ilElectronicCourseReserveLangData
{

	/**
	 * @var array
	 */
	public static $ecr_lang_data = array();

	/**
	 * @var ilDB
	 */
	public $db;

	/**
	 * @var string
	 */
	protected $lang_key;

	/**
	 * default
	 * @var string
	 */
	protected $identifier = 'ecr_tab_title';

	/**
	 * @var string
	 */
	protected $value;

	public function __construct()
	{
		global $DIC;

		$this->db = $DIC->database();
		$this->readFromDB();
	}

	/**
	 * @param $lang_key
	 * @return string
	 */
 	public function txt($identifier)
 	{

 		global $DIC;
 		$lang_key = $DIC->user()->getLanguage();
 		if(!isset(self::$ecr_lang_data[$lang_key]))
		{
			$lang_value = $this->readFromDB();
			if(strlen($lang_value))
			{
				return $lang_value;
			}
			return ilElectronicCourseReservePlugin::getInstance()->txt($this->identifier);
		}
		return self::$ecr_lang_data[$lang_key];
 	}

 	private function readFromDB()
	{
		$query = 'SELECT * FROM ecr_lang_data ';
		$res = $this->db->query($query);

		while($row = $this->db->fetchAssoc($res))
		{
			self::$ecr_lang_data[$row['lang_key']] = $row['value'];
		}
	}

	public function save()
	{
		$this->db->replace('ecr_lang_data',
			array
			(
				'value' => array('text', $this->getValue())
			),
			array
			(
				'lang_key' => array('text', $this->getLangKey()),
				'identifier' => array('text', $this->identifier)
			));
	}

	/**
	 * @return string
	 */
	public function getLangKey()
	{
		return $this->lang_key;
	}

	/**
	 * @param string $lang_key
	 */
	public function setLangKey($lang_key)
	{
		$this->lang_key = $lang_key;
	}

	/**
	 * @return string
	 */
	public function getValue()
	{
		return $this->value;
	}

	/**
	 * @param string $value
	 */
	public function setValue($value)
	{
		$this->value = $value;
	}
}