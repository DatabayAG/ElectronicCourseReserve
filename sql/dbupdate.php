<#1>
<?php
/**
 * @var $ilDB ilDB|ilDBInterface
 */
$fields = [
    'ref_id' => [
        'type' => 'integer',
        'length' => 4,
        'default' => 0,
        'notnull' => true
    ],
    'target_ref_id' => [
        'type' => 'integer',
        'length' => 4,
        'default' => 0,
        'notnull' => true
    ],
    'ts' => [
        'type' => 'integer',
        'length' => 4,
        'default' => 0,
        'notnull' => true
    ],
    'job_nr' => [
        'type' => 'text',
        'length' => 255,
        'default' => null,
        'notnull' => false
    ]
];

$ilDB->createTable('ecr_import_history', $fields);
$ilDB->addPrimaryKey('ecr_import_history', ['ref_id', 'ts']);
?>
<#2>
<?php
//
?>
<#3>
<?php
if (!$ilDB->tableExists('ecr_lang_agreements')) {
    $fields = [
        'agreement_id' => [
            'type' => 'integer',
            'length' => 4,
            'default' => 0,
            'notnull' => true
        ],

        'lang' => [
            'type' => 'text',
            'length' => 2,
            'default' => 'en',
            'notnull' => true
        ],

        'agreement' => [
            'type' => 'clob',
            'default' => null,
            'notnull' => false
        ],

        'time_created' => [
            'type' => 'integer',
            'length' => 4,
            'default' => 0,
            'notnull' => true
        ]
    ];

    $ilDB->createTable('ecr_lang_agreements', $fields);
    $ilDB->addPrimaryKey('ecr_lang_agreements', ['agreement_id', 'lang']);
    $ilDB->createSequence('ecr_lang_agreements');
}
?>
<#4>
<?php
if (!$ilDB->tableExists('ecr_user_acceptance')) {
    $fields = [
        'ref_id' => [
            'type' => 'integer',
            'length' => 4,
            'default' => 0,
            'notnull' => true
        ],
        'user_id' => [
            'type' => 'integer',
            'length' => 4,
            'default' => 0,
            'notnull' => true
        ],
        'agreement_id' => [
            'type' => 'integer',
            'length' => 4,
            'default' => 0,
            'notnull' => true
        ],
        'time_accepted' => [
            'type' => 'integer',
            'length' => 4,
            'default' => 0,
            'notnull' => true
        ],

    ];

    $ilDB->createTable('ecr_user_acceptance', $fields);
    $ilDB->addPrimaryKey('ecr_user_acceptance', ['ref_id', 'user_id']);
}
?>
<#5>
<?php
if (!$ilDB->tableColumnExists('ecr_lang_agreements', 'is_active')) {
    $ilDB->addTableColumn(
        'ecr_lang_agreements',
        'is_active',
        [
            'type' => 'integer',
            'length' => 1,
            'default' => 0,
            'notnull' => true
        ]
    );
}
?>
<#6>
<?php
if (!$ilDB->tableExists('ecr_lang_data')) {
    $fields = array(
        'lang_key' => array(
            'type' => 'text',
            'length' => 2,
            'notnull' => true
        ),
        'identifier' => array(
            'type' => 'text',
            'length' => 60,
            'notnull' => true
        ),
        'value' => array(
            'type' => 'text',
            'length' => 4000,
            'notnull' => false
        )
    );

    $ilDB->createTable('ecr_lang_data', $fields);
    $ilDB->addPrimaryKey('ecr_lang_data', array('lang_key', 'identifier'));
}
?>
<#7>
<?php
if (!$ilDB->tableExists('ecr_description')) {
}
?>
<#8>
<?php
if (!$ilDB->tableExists('ecr_description')) {
    $fields = [
        'ref_id' => [
            'type' => 'integer',
            'length' => '4',
            'notnull' => true
        ],
        'version' => [
            'type' => 'integer',
            'length' => '4',
            'notnull' => true
        ],
        'description' => [
            "type" => "clob",
            "notnull" => false,
            "default" => null
        ],
        'icon' => [
            'type' => 'text',
            'length' => '4000',
            'notnull' => false
        ],
        'timestamp' => [
            'type' => 'integer',
            'length' => '4',
            'notnull' => true
        ]
    ];

    $ilDB->createTable('ecr_description', $fields);
    $ilDB->addPrimaryKey('ecr_description', ['ref_id', 'version']);
}
?>
<#9>
<?php
if ($ilDB->tableExists('ecr_description')) {
    if (!$ilDB->tableColumnExists('ecr_description', 'raw_xml')) {
        $ilDB->addTableColumn(
            'ecr_description',
            'raw_xml',
            [
                "type" => "clob",
                "notnull" => false,
                "default" => null
            ]
        );
    }
}
?>
<#10>
<?php
if (!$ilDB->tableExists('ecr_folder')) {
    $fields = [
        'ref_id' => [
            'type' => 'integer',
            'length' => '4',
            'notnull' => true
        ],
        'obj_id' => [
            'type' => 'integer',
            'length' => '4',
            'notnull' => true
        ],
        'import_id' => [
            'type' => 'integer',
            'length' => '4',
            'notnull' => true
        ],
        'crs_ref_id' => [
            'type' => 'integer',
            'length' => '4',
            'notnull' => true
        ]
    ];

    $ilDB->createTable('ecr_folder', $fields);
    $ilDB->addPrimaryKey('ecr_folder', ['ref_id', 'crs_ref_id']);
}
?>
<#11>
<?php
if ($ilDB->tableExists('ecr_lang_data')) {
    if (!$ilDB->tableColumnExists('ecr_lang_data', 'ecr_content')) {
        $ilDB->addTableColumn(
            'ecr_lang_data',
            'ecr_content',
            ['type' => 'clob', 'default' => null, 'notnull' => false]
        );
    }
}
?>
<#12>
<?php
if ($ilDB->tableColumnExists('ecr_folder', 'obj_id')) {
    $ilDB->dropTableColumn('ecr_folder', 'obj_id');
}
?>
<#13>
<?php
if ($ilDB->tableExists('ecr_import_history')) {
    $ilDB->dropTable('ecr_import_history');
}
?>
<#14>
<?php
if (!$ilDB->tableColumnExists('ecr_description', 'folder_ref_id')) {
    $ilDB->addTableColumn(
        'ecr_description',
        'folder_ref_id',
        [
            'type' => 'integer',
            'length' => '4',
            'notnull' => true
        ]
    );
}
?>
<#15>
<?php
if (!$ilDB->tableColumnExists('ecr_description', 'show_description')) {
    $ilDB->addTableColumn(
        'ecr_description',
        'show_description',
        [
            'type' => 'integer',
            'length' => '1',
            'default' => '1',
            'notnull' => true
        ]);
}
?>
<#16>
<?php
if (!$ilDB->tableColumnExists('ecr_description', 'show_image')) {
    $ilDB->addTableColumn(
        'ecr_description',
        'show_image',
        [
            'type' => 'integer',
            'length' => '1',
            'default' => '1',
            'notnull' => true
        ]
    );
}
?>
<#17>
<?php
if (!$ilDB->tableColumnExists('ecr_description', 'icon_type')) {
    $ilDB->addTableColumn(
        'ecr_description',
        'icon_type',
        [
            'type' => 'text',
            'length' => '2000',
            'notnull' => false
        ]
    );
}
?>
<#18>
<?php
if ($ilDB->tableExists('ecr_description')) {
    if (!$ilDB->tableColumnExists('ecr_description', 'metadata')) {
        $ilDB->addTableColumn(
            'ecr_description',
            'metadata',
            [
                "type" => "clob",
                "notnull" => false,
                "default" => null
            ]
        );
    }
}
?>
<#19>
<?php
if ($ilDB->tableColumnExists('ecr_folder', 'import_id')) {
    $ilDB->modifyTableColumn(
        'ecr_folder',
        'import_id',
        [
            "type" => "text",
            'length' => '200',
            "notnull" => true,
            "default" => 0
        ]
    );
}

?>
<#20>
<?php
require_once 'Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/ElectronicCourseReserve/classes/class.ilElectronicCourseReserveDigitizedMediaImporter.php';
$query = "
	SELECT objr.ref_id, od.obj_id, od.import_id
	FROM object_data od
	INNER JOIN object_reference objr ON objr.obj_id = od.obj_id
	INNER JOIN ecr_folder ON ecr_folder.ref_id = objr.ref_id
	WHERE od.type = " . $ilDB->quote("fold",
        "text") . " AND (od.import_id IS NOT NULL AND od.import_id != '' AND NOT " . $ilDB->like("od.import_id", "text",
        "esa_%") . ")";
$res = $ilDB->query($query);

while ($row = $ilDB->fetchAssoc($res)) {

    $ilDB->manipulateF(
        'UPDATE object_data SET import_id = %s WHERE obj_id = %s',
        ['text', 'integer'],
        [
            ilElectronicCourseReserveDigitizedMediaImporter::ESA_FOLDER_IMPORT_PREFIX . $row['import_id'],
            $row['obj_id']
        ]
    );

    $ilDB->manipulateF(
        'UPDATE ecr_folder SET import_id = %s WHERE ref_id = %s',
        ['text', 'integer'],
        [
            ilElectronicCourseReserveDigitizedMediaImporter::ESA_FOLDER_IMPORT_PREFIX . $row['import_id'],
            $row['ref_id']
        ]
    );
}
?>
<#21>
<?php
if (!$ilDB->tableExists('ecr_deletion_log')) {
    $fields = [
        'crs_ref_id' => [
            'type' => 'integer',
            'length' => 4,
            'notnull' => true
        ],
        'folder_ref_id' => [
            'type' => 'integer',
            'length' => 4,
            'notnull' => true
        ],
        'deletion_mode' => [
            'type' => 'text',
            'length' => 10, // imported/all
            'notnull' => true
        ],
        'deletion_timestamp' => [
            'type' => 'integer',
            'length' => 8,
            'notnull' => true
        ],
        'deletion_message' => [
            'type' => 'blob',
            'notnull' => false,
            'default' => null
        ]
    ];

    $ilDB->createTable('ecr_deletion_log', $fields);
    $ilDB->addPrimaryKey('ecr_deletion_log', ['crs_ref_id', 'folder_ref_id']);
}
?>
<#22>
<?php
if ($ilDB->tableExists('ecr_deletion_log') && !$ilDB->tableColumnExists('ecr_deletion_log', 'log_id')) {
    $ilDB->addTableColumn(
        'ecr_deletion_log',
        'log_id',
        [
            'type' => 'text',
            'length' => 255,
            'notnull' => false,
            'default' => null
        ]
    );
}
?>
<#23>
<?php
if ($ilDB->tableExists('ecr_deletion_log') && $ilDB->tableColumnExists('ecr_deletion_log', 'log_id')) {
    $uuid = static function() {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),
            // 16 bits for "time_high_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,
            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,
            // 48 bits for "node"
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    };

    $res = $ilDB->query('SELECT * FROM ecr_deletion_log WHERE log_id IS NULL');
    while ($row = $ilDB->fetchAssoc($res)) {
        $ilDB->manipulateF(
            'UPDATE ecr_deletion_log SET log_id = %s WHERE crs_ref_id = %s AND folder_ref_id = %s',
            ['text', 'integer', 'integer'],
            [$uuid(), $row['crs_ref_id'], $row['folder_ref_id']]
        );
    }
}
?>
<#24>
<?php
if ($ilDB->tableExists('ecr_deletion_log')) {
    $ilDB->dropPrimaryKey("ecr_deletion_log");
}
?>
<#25>
<?php
if ($ilDB->tableExists('ecr_deletion_log') && $ilDB->tableColumnExists('ecr_deletion_log', 'log_id')) {
    $ilDB->addPrimaryKey(
        'ecr_deletion_log',
        ['log_id']
    );
}
?>
<#26>
<?php
if ($ilDB->tableExists('ecr_deletion_log') && !$ilDB->tableColumnExists('ecr_deletion_log', 'deletion_timestamp_ms')) {
    $ilDB->addTableColumn(
        'ecr_deletion_log',
        'deletion_timestamp_ms',
        [
            'type' => 'integer',
            'length' => 8,
            'notnull' => true,
            'default' => 0
        ]
    );
    $ilDB->manipulate('UPDATE ecr_deletion_log SET deletion_timestamp_ms = (deletion_timestamp * 100)');
}
?>
<#27>
<?php
$ilDB->addIndex('ecr_deletion_log', ['crs_ref_id'], 'i1');
?>
<#28>
<?php
$ilDB->addIndex('ecr_deletion_log', ['folder_ref_id'], 'i2');
?>
<#29>
<?php
$ilDB->addIndex('ecr_deletion_log', ['folder_ref_id', 'deletion_timestamp_ms'], 'i3');
?>
<#30>
<?php
if ($ilDB->tableExists('ecr_deletion_log')) {
    $ilDB->addTableColumn(
        'ecr_deletion_log',
        'metadata',
        [
            'type' => 'blob',
            'notnull' => false,
            'default' => null,
        ]
    );
}
?>
<#31>
<?php
if ($ilDB->tableExists('ecr_deletion_log') && $ilDB->tableColumnExists('ecr_deletion_log', 'deletion_message')) {
    $ilDB->modifyTableColumn(
        'ecr_deletion_log',
        'deletion_message',
        [
            'type' => 'blob',
            'notnull' => false,
            'default' => null,
        ]
    );
}
?>
