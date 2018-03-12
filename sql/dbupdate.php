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
	