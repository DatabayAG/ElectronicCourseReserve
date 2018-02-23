<?php

/**
 * Interface ilECRBaseModifier
 * @author Nadia Matuschek <nmatuschek@databay.de>
 */
interface ilECRBaseModifier
{
	/**
	 * @param $a_comp
	 * @param $a_part
	 * @param $a_par
	 * @return bool
	 */
	public function shouldModifyHtml($a_comp, $a_part, $a_par);
	
	/**
	 * @param $a_comp
	 * @param $a_part
	 * @param $a_par
	 * @return string $html 
	 */
	public function modifyHtml($a_comp, $a_part, $a_par);
	
}