<?php

/**
 * Class ilUseAgreement
 * @author Nadia Matuschek <nmatuschek@databay.de>
 */
class ilUseAgreement
{
	/**
	 * @var ilDB
	 */
	public $db;
	/**
	 * @var \ILIAS\DI\LoggingServices
	 */
	public $log;
	/**
	 * @var ilObjUser
	 */
	public $user;
	
	
	protected $agreement_id;
	
	protected $agreement;
	
	protected $lang;
	
	protected $time_created;
	

	
	public function __construct()
	{
		global $DIC;
		
		$this->db = $DIC->database();
		$this->log = $DIC->logger();
		$this->user = $DIC->user();
	}
	
	/**
	 * @param $lang
	 */
	public function loadByLang($lang)
	{
		$this->db->setLimit(1);
		$res = $this->db->queryF(
			'SELECT * FROM ecr_lang_agreements WHERE lang = %s ORDER BY time_created DESC'
		);
		
		if($row = $this->db->fetchAssoc($res))
		{
			$this->agreement_id = $row['agreement_id'];
			$this->lang         = $row['lang'];
			$this->agreement    = $row['agreement'];
			$this->time_created = $row['time_created'];
		}
	}
	
	public function saveAgreement()
	{
		$next_id = $this->db->nextId('ecr_lang_agreements');
		$this->db->insert('ecr_lang_agreements',
			array(
				'agreement_id' => array('integer', $next_id),
				'lang'         => array('text', $this->getLang()),
				'agreement'    => array('clob', $this->getAgreement()),
				'time_created' => array('integer', time())
			));
		
		$this->log->write('ecr_lang_agreements: User-id ('.$this->user->getId().') created agreement_id ('. $this->getAgreementId().')');
	}
	
	
	/**
	 * @return mixed
	 */
	public function getAgreementId()
	{
		return $this->agreement_id;
	}
	
	/**
	 * @param int $agreement_id
	 */
	public function setAgreementId($agreement_id)
	{
		$this->agreement_id = $agreement_id;
	}
	
	/**
	 * @return mixed
	 */
	public function getLang()
	{
		return $this->lang;
	}
	
	/**
	 * @param string $lang ISO 639-1 two-letter code
	 */
	public function setLang($lang)
	{
		$this->lang = $lang;
	}
	
	/**
	 * @return string
	 */
	public function getAgreement()
	{
		return $this->agreement;
	}
	
	/**
	 * @param string $agreement
	 */
	public function setAgreement($agreement)
	{
		$this->agreement = $agreement; 
	}
	
	/**
	 * @return int
	 */
	public function getTimeCreated()
	{
		return $this->time_created;
	}
	
}