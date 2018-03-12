<?php
include_once  'Services/Table/classes/class.ilTable2GUI.php';

/**
 * Class ilUseAgreementTableGUI
 * @author Nadia Matuschek <nmatuschek@databay.de> 
 */
class ilUseAgreementTableGUI extends ilTable2GUI
{
	public $ctrl;
	
	public $tpl;
	
	public function __construct($a_parent_obj, $a_parent_cmd = "", $a_template_context = "")
	{
		global $DIC;
		
		$this->ctrl = $DIC->ctrl();
		$this->tpl  = $DIC->ui()->mainTemplate();
		$this->lng = $DIC->language();
		
		$this->setId('tbl_ecr_use_agreement');
		
		parent::__construct($a_parent_obj, $a_parent_cmd, $a_template_context);
		
		$this->setTitle('use_agreements');
		
		$this->addColumn($this->lng->txt('language'), 'lang' );
		$this->addColumn($this->lng->txt('actions'), '');
		
	}
	
}