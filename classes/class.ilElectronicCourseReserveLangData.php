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
	 * @var null
	 */
	protected $ecr_content = NULL;

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
	 * @param $identifier
	 * @return mixed|string
	 */
 	public function txt($identifier)
 	{
 		global $DIC;
 		$lang_key = $DIC->user()->getLanguage();

 		if(!isset(self::$ecr_lang_data[$lang_key]))
		{
			return ilElectronicCourseReservePlugin::getInstance()->txt($identifier);
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

	public function saveTranslation()
	{
		$this->db->replace('ecr_lang_data',
			array
			(
				'ecr_content' => array('clob', $ecr_content = self::lookupEcrContentByLangKey($this->getLangKey())),
				'value'      => array('text', $this->getValue())
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

	/**
	 * @param $lang_key
	 * @return int
	 */
	public static function lookupObjIdByLangKey($lang_key)
	{
		global $DIC;

		$res = $DIC->database()->queryF('SELECT obj_id FROM object_data WHERE title = %s',
			array('text'), array(trim($lang_key)));

		while($row = $DIC->database()->fetchAssoc($res))
		{
			return $row['obj_id'];
		}

		return 0;
	}

	/**
	 * @param $lang_key
	 * @return string
	 */
	public static function lookupEcrContentByLangKey($lang_key)
	{
		global $DIC;

		$res = $DIC->database()->queryF('SELECT ecr_content FROM ecr_lang_data WHERE lang_key = %s',
			array('text'), array(trim($lang_key)));

		while($row = $DIC->database()->fetchAssoc($res))
		{
			return $row['ecr_content'];
		}

		return '';
	}

	/**
	 * @param $lang_key
	 * @param $ecr_content
	 */
	public static function writeEcrContent($lang_key, $ecr_content )
	{
		global $DIC;

		$DIC->database()->update('ecr_lang_data',
			array
			(
				'ecr_content' => array('clob', $ecr_content)
			),
			array
			(
				'lang_key' => array('text', $lang_key),
			));
	}
}