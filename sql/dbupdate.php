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
if(!$ilDB->tableExists('ecr_description'))
{}
?>
<#3>
<?php
if(!$ilDB->tableExists('ecr_description'))
{}
?>
<#4>
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

