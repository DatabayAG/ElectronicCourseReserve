<?php

/**
 * Class ilElectronicCourseReserveAcceptance
 * @author Nadia Matuschek <nmatuschek@databay.de>
 */
class ilElectronicCourseReserveAcceptance
{
	protected $ref_id;
	protected $user_id;
	
	protected $agreement_id;
	protected $time_accepted;

	protected $db;
	protected $user;
	
	
	/**
	 * ilElectronicCourseReserveAcceptance constructor.
	 * @param int $ref_id
	 */
	public function __construct($ref_id)
	{
		global $DIC;
		
		$this->user = $DIC->user();
		$this->db = $DIC->database(); 
	
		$this->ref_id = $ref_id;
		$this->user_id = $this->user->getId();
	}
	
	public function hasUserAcceptedAgreement()
	{
		$res = $this->db->queryF(
			'SELECT * FROM ecr_user_acceptance WHERE ref_id = %s AND user_id = %s',
			array('integer', 'integer'), array($this->ref_id, $this->user_id));
		
		if($this->db->numRows($res) > 0)
		{
			return true;
		}
		return false; 
	}
	
	public function saveUserAcceptance()
	{
		$this->db->insert('ecr_user_acceptance',
			array('ref_id' => array('integer', $this->ref_id), 
				'user_id' => array('integer', $this->user_id), 
				'agreement_id' => array('integer', $this->getAgreementId()),
				'time_accepted' => array('integer', time())));
	}
	
	public function getAgreementId()
	{
		$res = $this->db->queryF('SELECT agreement_id FROM ecr_lang_agreements WHERE is_active = %s AND lang = %s', 
			array('integer', 'text'), array(1, $this->user->getLanguage()));
		
		while($row = $this->db->fetchAssoc($res))
		{
			$this->agreement_id = $row['agreement_id'];
		}	
		
		return $this->agreement_id;
	}
}