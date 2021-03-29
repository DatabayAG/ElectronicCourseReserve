<?php

use ILIAS\Plugin\ElectronicCourseReserve\UI\Table\Data\Provider;

include_once 'Services/Table/classes/class.ilTable2GUI.php';
require_once 'Services/UIComponent/AdvancedSelectionList/classes/class.ilAdvancedSelectionListGUI.php';

/**
 * Class ilElectronicCourseReserveDeletionProtocolTableGUI
 */
class ilElectronicCourseReserveDeletionProtocolTableGUI extends \ILIAS\Plugin\ElectronicCourseReserve\UI\Table\Base
{
    /** @var array<int, array> */
    private $cachedColumnDefinition = [];
    /** @var Provider|null */
    protected $provider;
    /** @var array */
    protected $visibleOptionalColumns = [];
    /** @var array */
    protected $optionalColumns = [];
    /** @var array */
    protected $filter = [];
    /** @var array */
    protected $optional_filter = [];
    /** @var \ILIAS\UI\Renderer */
    private $uiRenderer;
    /** @var \ILIAS\UI\Factory */
    private $uiFactory;

    /**
     * ilElectronicCourseReserveDeletionProtocolTableGUI constructor.
     * @param $a_parent_obj
     * @param string $a_parent_cmd
     */
    public function __construct($a_parent_obj, string $a_parent_cmd)
    {
        global $DIC;

        $this->setId('tbl_ecr_deletion_protocol');
        $this->setFormName($this->getId());
        parent::__construct($a_parent_obj, $a_parent_cmd);
        
        $this->uiFactory = $DIC->ui()->factory();
        $this->uiRenderer = $DIC->ui()->renderer();

        $this->setFormAction($this->ctrl->getFormAction($a_parent_obj, $a_parent_cmd));
        $this->setTitle($this->parent_obj->getPluginObject()->txt('adm_ecr_tab_del_procotol'));

        $this->setRowTemplate($a_parent_obj->getPluginObject()->getDirectory() . '/templates/tpl.row_deletion_protocol.html');

        $this->setDefaultOrderDirection('DESC');
        $this->setDefaultOrderField('next_due_date');
        $this->setExternalSorting(true);
        $this->setExternalSegmentation(true);

        $this->initFilter();
        $this->setDefaultFilterVisiblity(true);
        $this->setFilterCommand('applyFilter');
        $this->setResetCommand('resetFilter');
    }

    /**
     * @inheritDoc#
     */
    protected function getColumnDefinition() : array
    {
        if ($this->cachedColumnDefinition !== []) {
            return $this->cachedColumnDefinition;
        }

        $i = 0;

        $columns = [];

        $columns[++$i] = [
            'field' => 'crs_title',
            'txt' => $this->parent_obj->getPluginObject()->txt('adm_ecr_tab_del_column_crs'),
            'default' => true,
            'optional' => false,
            'sortable' => true,
            'width' => '20%',
        ];
        $columns[++$i] = [
            'field' => 'fold_title',
            'txt' => $this->parent_obj->getPluginObject()->txt('adm_ecr_tab_del_column_fold'),
            'default' => true,
            'optional' => false,
            'sortable' => true,
            'width' => '20%',
        ];
        $columns[++$i] = [
            'field' => 'deletion_timestamp',
            'txt' => $this->parent_obj->getPluginObject()->txt('adm_ecr_tab_del_column_deletion_datetime'),
            'default' => true,
            'optional' => false,
            'sortable' => true,
            'width' => '20%',
        ];
        $columns[++$i] = [
            'field' => 'deletion_mode',
            'txt' => $this->parent_obj->getPluginObject()->txt('adm_ecr_tab_del_column_deletion_mode'),
            'default' => true,
            'optional' => false,
            'sortable' => true,
            'width' => '20%',
        ];
        $columns[++$i] = [
            'field' => 'deletion_message',
            'txt' => $this->parent_obj->getPluginObject()->txt('adm_ecr_tab_del_column_deletion_message'),
            'default' => false,
            'optional' => true,
            'sortable' => false,
            'width' => '20%',
        ];

        $this->cachedColumnDefinition = $columns;

        return $this->cachedColumnDefinition;
    }

    /**
     * @inheritDoc
     */
    public function initFilter()
    {
        
    }

    /**
     * Define a final formatting for a cell value
     * @param string $column
     * @param array  $row
     * @return string
     */
    protected function formatCellValue(string $column, array $row) : string
    {
        $value = parent::formatCellValue($column, $row);

        $usedRelativeDates = ilDatePresentation::useRelativeDates();
        ilDatePresentation::setUseRelativeDates(false);

        if ('deletion_timestamp' === $column) {
            $value = ilDatePresentation::formatDate(new ilDateTime($value, IL_CAL_UNIX));
        } elseif ('deletion_message' === $column) {
            $value = ilUtil::prepareFormOutput($value);
        } elseif ('deletion_mode' === $column) {
            $value = $this->parent_obj->getPluginObject()->txt('adm_ecr_tab_del_column_deletion_mode_imported');
            if ('all' === strtolower($value)) {
                $value = $this->parent_obj->getPluginObject()->txt('adm_ecr_tab_del_column_deletion_mode_all');
            }
        } elseif ('crs_title' === $column) {
            if (!$row['is_crs_deleted']) {
                $value = $this->uiRenderer->render($this->uiFactory->link()->standard(
                    $row[$column],
                    ilLink::_getLink($row['crs_ref_id'], 'crs')
                ));
            }
        } elseif ('fold_title' === $column) {
            if (!$row['is_fold_deleted']) {
                $value = $this->uiRenderer->render($this->uiFactory->link()->standard(
                    $row[$column],
                    ilLink::_getLink($row['folder_ref_id'], 'fold')
                ));
            }
        }

        ilDatePresentation::setUseRelativeDates($usedRelativeDates);

        return $value;
    }
}
