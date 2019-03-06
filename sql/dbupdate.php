<#1>
<?php
$fields = array(
	'ref_id'        => array(
		'type'    => 'integer',
		'length'  => 4,
		'default' => 0,
		'notnull' => true
	),
	'target_ref_id' => array(
		'type'    => 'integer',
		'length'  => 4,
		'default' => 0,
		'notnull' => true
	),
	'ts'            => array(
		'type'    => 'integer',
		'length'  => 4,
		'default' => 0,
		'notnull' => true
	),
	'job_nr'        => array(
		'type'    => 'text',
		'length'  => 255,
		'default' => null,
		'notnull' => false
	)
);

$ilDB->createTable('ecr_import_history', $fields);
$ilDB->addPrimaryKey('ecr_import_history', array('ref_id', 'ts'));
?>
<#2>
<?php
//
?>
<#3>
<?php
if(!$ilDB->tableExists('ecr_lang_agreements'))
{
	$fields = array(
		'agreement_id' => array(
			'type'    => 'integer',
			'length'  => 4,
			'default' => 0,
			'notnull' => true),
		
		'lang' => array(
			'type'    => 'text',
			'length'  => 2,
			'default' => 'en',
			'notnull' => true),
		
		'agreement' => array(
			'type'    => 'clob',
			'default' => null,
			'notnull' => false),
		
		'time_created' => array(
			'type'    => 'integer',
			'length'  => 4,
			'default' => 0,
			'notnull' => true)
	);
	
	$ilDB->createTable('ecr_lang_agreements', $fields);
	$ilDB->addPrimaryKey('ecr_lang_agreements', array('agreement_id', 'lang'));
	
	$ilDB->createSequence('ecr_lang_agreements');
	
}	
?>
<#4>
<?php
if(!$ilDB->tableExists('ecr_user_acceptance'))
{
	$fields = array(
		'ref_id' => array(
			'type'    => 'integer',
			'length'  => 4,
			'default' => 0,
			'notnull' => true
		),
		'user_id' => array(
			'type'    => 'integer',
			'length'  => 4,
			'default' => 0,
			'notnull' => true
		),
		'agreement_id' => array(
			'type'    => 'integer',
			'length'  => 4,
			'default' => 0,
			'notnull' => true
		),
		'time_accepted' => array(
			'type'    => 'integer',
			'length'  => 4,
			'default' => 0,
			'notnull' => true
		),

	);
	
	$ilDB->createTable('ecr_user_acceptance', $fields);
	$ilDB->addPrimaryKey( 'ecr_user_acceptance', array('ref_id', 'user_id'));
}
?>	
<#5>
<?php
	if(!$ilDB->tableColumnExists('ecr_lang_agreements', 'is_active'))
	{
		$ilDB->addTableColumn('ecr_lang_agreements','is_active',  
		array(
		'type'    => 'integer',
		'length'  => 1,
		'default' => 0,
		'notnull' => true)); 	
	}
?>
<#6>
<?php
if(!$ilDB->tableExists('ecr_lang_data')) {
	$fields = array(
		'lang_key'   => array(
			'type'    => 'text',
			'length'  => 2,
			'notnull' => true
		),
		'identifier' => array(
			'type'    => 'text',
			'length'  => 60,
			'notnull' => true
		),
		'value'      => array(
			'type'    => 'text',
			'length'  => 4000,
			'notnull' => false
		)
	);

	$ilDB->createTable('ecr_lang_data', $fields);
	$ilDB->addPrimaryKey('ecr_lang_data', array('lang_key', 'identifier'));
}
?>
<#7>
<?php
if(!$ilDB->tableExists('ecr_description'))
{}
?>
<#8>
<?php
/**
 * @var $ilDB ilDB
 */
if(!$ilDB->tableExists('ecr_description'))
{
	$fields = array(
		'ref_id'      => array(
			'type'    => 'integer',
			'length'  => '4',
			'notnull' => true
		),
		'version'     => array(
			'type'    => 'integer',
			'length'  => '4',
			'notnull' => true
		),
		'description' => array(
			"type"    => "clob",
			"notnull" => false,
			"default" => null
		),
		'icon'        => array(
			'type'    => 'text',
			'length'  => '4000',
			'notnull' => false
		),
		'timestamp'   => array(
			'type'    => 'integer',
			'length'  => '4',
			'notnull' => true
		)
	);

	$ilDB->createTable('ecr_description', $fields);
	$ilDB->addPrimaryKey('ecr_description', array('ref_id', 'version'));
}
?>
<#9>
<?php
/**
 * @var $ilDB ilDB
 */
if($ilDB->tableExists('ecr_description'))
{
	if(!$ilDB->tableColumnExists('ecr_description', 'raw_xml'))
	{
		$ilDB->addTableColumn('ecr_description', 'raw_xml',
									array(
										"type"    => "clob",
										"notnull" => false,
										"default" => null
									));
	}
}
?>
<#10>
<?php
/**
 * @var $ilDB ilDB
 */
if(!$ilDB->tableExists('ecr_folder'))
{
	$fields = array(
		'ref_id'      => array(
			'type'    => 'integer',
			'length'  => '4',
			'notnull' => true
		),
		'obj_id'     => array(
			'type'    => 'integer',
			'length'  => '4',
			'notnull' => true
		),
		'import_id' => array(
			'type'    => 'integer',
			'length'  => '4',
			'notnull' => true
		),
		'crs_ref_id'  => array(
			'type'    => 'integer',
			'length'  => '4',
			'notnull' => true
		)
	);

	$ilDB->createTable('ecr_folder', $fields);
	$ilDB->addPrimaryKey('ecr_folder', array('ref_id', 'crs_ref_id'));
}
?>
<#11>
<?php

if($ilDB->tableExists('ecr_lang_data'))
{
	if(!$ilDB->tableColumnExists('ecr_lang_data', 'ecr_content'))
	{
		$ilDB->addTableColumn('ecr_lang_data', 'ecr_content',
			array('type' => 'clob', 'default' => null, 'notnull' => false));
	}
}
?>
<#12>
<?php
/**
 * @var $ilDB ilDB
 */
if($ilDB->tableColumnExists('ecr_folder', 'obj_id'))
{
	$ilDB->dropTableColumn('ecr_folder', 'obj_id');
}
?>
<#13>
<?php
/**
 * @var $ilDB ilDB
 */
if($ilDB->tableExists('ecr_import_history'))
{
	$ilDB->dropTable('ecr_import_history');
}
?>
<#14>
<?php
/**
 * @var $ilDB ilDB
 */
if(!$ilDB->tableColumnExists('ecr_description', 'folder_ref_id'))
{
	$ilDB->addTableColumn('ecr_description', 'folder_ref_id',
			array(
				'type'    => 'integer',
				'length'  => '4',
				'notnull' => true));
}
?>
<#15>
<?php
/**
 * @var $ilDB ilDB
 */
if(!$ilDB->tableColumnExists('ecr_description', 'show_description'))
{
	$ilDB->addTableColumn('ecr_description', 'show_description',
			array(
				'type'    => 'integer',
				'length'  => '1',
				'default' => '1',
				'notnull' => true));
}
?>
<#16>
<?php
/**
 * @var $ilDB ilDB
 */
if(!$ilDB->tableColumnExists('ecr_description', 'show_image'))
{
	$ilDB->addTableColumn('ecr_description', 'show_image',
			array(
				'type'    => 'integer',
				'length'  => '1',
				'default' => '1',
				'notnull' => true));
}
?>
<#17>
<?php
	/**
	 * @var $ilDB ilDB
	 */
	if(!$ilDB->tableColumnExists('ecr_description', 'icon_type'))
	{
		$ilDB->addTableColumn('ecr_description', 'icon_type',
			array(
				'type'    => 'text',
				'length'  => '2000',
				'notnull' => false));
	}
?>
<#18>
<?php
/**
 * @var $ilDB ilDB
 */
if($ilDB->tableExists('ecr_description'))
{
	if(!$ilDB->tableColumnExists('ecr_description', 'metadata'))
	{
		$ilDB->addTableColumn('ecr_description', 'metadata',
			array(
				"type"    => "clob",
				"notnull" => false,
				"default" => null
			));
	}
}
?>
<#19>
<?php
if($ilDB->tableColumnExists('ecr_folder', 'import_id'))
{
	$ilDB->modifyTableColumn('ecr_folder', 'import_id', array(
		"type" => "text",
		'length'  => '200',
		"notnull" => true,
		"default" => 0));
}

?>
<#20>
<?php
require_once 'Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/ElectronicCourseReserve/classes/class.ilElectronicCourseReserveDigitizedMediaImporter.php';
/**
 * @var $ilDB ilDB
 */
$query = "
	SELECT objr.ref_id, od.obj_id, od.import_id
	FROM object_data od
	INNER JOIN object_reference objr ON objr.obj_id = od.obj_id
	INNER JOIN ecr_folder ON ecr_folder.ref_id = objr.ref_id
	WHERE od.type = " . $ilDB->quote("fold", "text") . " AND (od.import_id IS NOT NULL AND od.import_id != '' AND NOT " . $ilDB->like("od.import_id", "text", "esa_%") . ")";
$res   = $ilDB->query($query);

while($row = $ilDB->fetchAssoc($res))
{

	$ilDB->manipulateF(
		'UPDATE object_data SET import_id = %s WHERE obj_id = %s',
		array('text', 'integer'),
		array(ilElectronicCourseReserveDigitizedMediaImporter::ESA_FOLDER_IMPORT_PREFIX . $row['import_id'],$row['obj_id'])
	);

	$ilDB->manipulateF(
		'UPDATE ecr_folder SET import_id = %s WHERE ref_id = %s',
		array('text', 'integer'),
		array(ilElectronicCourseReserveDigitizedMediaImporter::ESA_FOLDER_IMPORT_PREFIX . $row['import_id'], $row['ref_id'])
	);
}
?>
