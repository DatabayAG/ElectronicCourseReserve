<?php
include_once  'Services/Table/classes/class.ilTable2GUI.php';

/**
 * Class ilElectronicCourseReserveAgreementTableGUI
 * @author Nadia Matuschek <nmatuschek@databay.de> 
 */
class ilElectronicCourseReserveAgreementTableGUI extends ilTable2GUI
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
		
		$this->setTitle($this->parent_obj->pluginObj->txt('use_agreements'));
		
		$this->addColumn($this->lng->txt('language'), 'lang' );
		$this->addColumn($this->lng->txt('actions'), 'actions', '1%');
		$this->setRowTemplate($a_parent_obj->pluginObj->getDirectory().'/templates/tpl.row_use_agreement.html');
	}
	
	/**
	 * @param array $a_set
	 */
	public function fillRow($a_set)
	{
		$actions = new ilAdvancedSelectionListGUI();
		$actions->setId('action' . $a_set['lang']);
		$actions->setListTitle($this->lng->txt('actions'));
		
		$this->ctrl->setParameter($this->parent_obj, 'ecr_lang', $a_set['lang']);
		$edit_url = $this->ctrl->getLinkTarget($this->parent_obj, 'editUseAgreement');
		$actions->addItem($this->lng->txt('edit'), '', $edit_url);
		
		parent::fillRow($a_set); 
		
		$this->tpl->setVariable("ACTIONS", $actions->getHTML());
		$this->tpl->parseCurrentBlock();
	}
}