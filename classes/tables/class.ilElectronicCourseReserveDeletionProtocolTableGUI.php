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
        $this->setTitle($this->parent_obj->getPluginObject()->txt('adm_ecr_tab_del_protocol'));

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
            'width' => '10%',
        ];
        $columns[++$i] = [
            'field' => 'deletion_mode',
            'txt' => $this->parent_obj->getPluginObject()->txt('adm_ecr_tab_del_column_deletion_mode'),
            'default' => true,
            'optional' => false,
            'sortable' => true,
            'width' => '10%',
        ];
        $columns[++$i] = [
            'field' => 'deletion_message',
            'txt' => $this->parent_obj->getPluginObject()->txt('adm_ecr_tab_del_column_deletion_message'),
            'default' => false,
            'optional' => true,
            'sortable' => false,
            'width' => '20%',
        ];
        $columns[++$i] = [
            'field' => 'result',
            'txt' => $this->parent_obj->getPluginObject()->txt('adm_ecr_tab_del_column_result'),
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
        $crsTitle = new ilTextInputGUI($this->lng->txt('obj_crs'), 'crs_title');
        $crsTitle->setDataSource($this->ctrl->getLinkTarget(
            $this->getParentObject(),
            'fetchCourseTitleAutocompletionResults',
            '',
            true
        ));
        $crsTitle->setSize(20);
        $crsTitle->setSubmitFormOnEnter(true);
        $this->addFilterItem($crsTitle);
        $crsTitle->readFromSession();
        $this->filter['crs_title'] = $crsTitle->getValue();
        
        $foldTitle = new ilTextInputGUI($this->lng->txt('obj_fold'), 'fold_title');
        $foldTitle->setDataSource($this->ctrl->getLinkTarget(
            $this->getParentObject(),
            'fetchFolderTitleAutocompletionResults',
            '',
            true
        ));
        $foldTitle->setSize(20);
        $foldTitle->setSubmitFormOnEnter(true);
        $this->addFilterItem($foldTitle);
        $foldTitle->readFromSession();
        $this->filter['fold_title'] = $foldTitle->getValue();

        $this->tpl->addJavaScript("./Services/Form/js/Form.js");
        $duration = new ilDateDurationInputGUI($this->parent_obj->getPluginObject()->txt('period'), 'period');
        $duration->setRequired(true);
        $duration->setStartText($this->parent_obj->getPluginObject()->txt('period_from'));
        $duration->setEndText($this->parent_obj->getPluginObject()->txt('period_until'));
        $duration->setStart(new ilDateTime(strtotime('-1 year', time()), IL_CAL_UNIX));
        $duration->setEnd(new ilDateTime(time(), IL_CAL_UNIX));
        $duration->setShowTime(true);
        $this->addFilterItem($duration, true);
        $duration->readFromSession();
        $this->optional_filter['period'] = $duration->getValue();
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
            $mode = $value;
            $value = $this->parent_obj->getPluginObject()->txt('adm_ecr_tab_del_column_deletion_mode_imported');
            if ('all' === strtolower($mode)) {
                $value = $this->parent_obj->getPluginObject()->txt('adm_ecr_tab_del_column_deletion_mode_all');
            }
        } elseif ('crs_title' === $column) {
            if ($row['is_crs_deleted']) {
                $value = $this->lng->txt('deleted');
            } else {
                $value = $this->uiRenderer->render($this->uiFactory->link()->standard(
                    $row[$column],
                    ilLink::_getLink($row['crs_ref_id'], 'crs')
                ));
            }
        } elseif ('fold_title' === $column) {
            if ($row['is_fold_deleted']) {
                $value = $this->lng->txt('deleted');
            } else {
                $value = $this->uiRenderer->render($this->uiFactory->link()->standard(
                    $row[$column],
                    ilLink::_getLink($row['folder_ref_id'], 'fold')
                ));
            }
        } elseif ('result' === $column) {
            if ((string) $value !== '') {
                global $DIC;

                $result = json_decode($value);

                $value = $DIC->ui()->renderer()->render($DIC->ui()->factory()->listing()->descriptive([
                    $this->parent_obj->getPluginObject()->txt('del_folder_metric_num_obj_bd') => (string) count($result->itemsBeforeDeletion),
                    $this->parent_obj->getPluginObject()->txt('del_folder_metric_num_obj_ad') => (string) count($result->itemsAfterDeletion),
                    $this->parent_obj->getPluginObject()->txt('del_folder_metric_num_obj_ad') => (string) (count($result->itemsBeforeDeletion) - count($result->itemsAfterDeletion)),
                ]));
            }
        }

        ilDatePresentation::setUseRelativeDates($usedRelativeDates);

        return $value;
    }
}
