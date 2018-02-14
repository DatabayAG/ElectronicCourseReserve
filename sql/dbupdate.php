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