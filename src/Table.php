<?php
namespace samson\cms\export;

use samson\activerecord\Condition;
use samson\activerecord\Argument;
use samson\activerecord\dbRelation;

class Table extends \samson\cms\table\Table
{
    /** Default table template file */
    public $table_tmpl = 'www/table/index';

    /** Default table row template */
    public $row_tmpl = 'www/table/row/index';

    /** Default table notfound row template */
    public $notfound_tmpl = 'www/table/row/notfound';

    /** Default table empty row template */
    public $empty_tmpl = 'www/table/row/empty';

    /** @var string Table header html */
    protected $headerRows = '';

    /** @var array Collection of material fields */
    protected $fields = array();

    /** @var array Collection of structures related to materials */
    protected $materialStructures = array();

    /** @var int Catalog structure levels count */
    protected $structureCount = 0;

    /**
     * Constructor
     * @var $structures
     */
    public function __construct($structures = '')
    {
        // Try to find structure in DB
        $dbStructures = array();
        if (dbQuery('structure')->exec($dbStructures)) {
            // Get material ids by structure
            $materialIDs = dbQuery('structurematerial')->StructureID($structures)->fieldsNew('MaterialID');

            // Create DB query object
            $this->query = cmsquery()->id($materialIDs);//->own_limit(0);

            // Get all fields for structures(explode if they are splitted with comma)
            $fields = array();
            if (dbQuery('structurefield')
                ->StructureID(explode(',', $structures))
                ->group_by('FieldID')
                ->fieldsNew('FieldID', $fields)) {
                // Get all field objects
                if (dbQuery('field')->id($fields)->exec($this->fields)) {
                    // Render all fields as table headers
                    $num = 0;
                    foreach ($this->fields as $field) {
                        $this->headerRows .= m()->view('www/table/thead/th')->num($num++)->field($field)->output();
                    }
                }
            }

            // TODO: Create SQL request for this it will be usefull in Navigation
            $this->structureCount = 0;
            // Find all other structures that is connected with current materials
            $sms = array();
            if (dbQuery('structurematerial')->MaterialID($materialIDs)->exec($sms)) {
                foreach ($sms as $sm) {
                    // Set pointer to material record or create new array for it
                    $pointer = & $this->materialStructures[$sm->MaterialID];
                    if (!isset($pointer)) {
                        $pointer = array();
                    }

                    // Store material relation to a structure in array
                    if (!isset($pointer[$sm->StructureID]) && isset($dbStructures[$sm->StructureID])) {
                        $pointer[$sm->StructureID] = $dbStructures[$sm->StructureID]->Name;
                        // Analyze this material structures count
                        if (sizeof($pointer) > $this->structureCount) {
                            // Store maximum depth
                            $this->structureCount = sizeof($pointer);
                        }
                    }
                }

                // Sort all categories to be the same
                foreach ($this->materialStructures as &$sm) {
                    ksort($sm, SORT_NUMERIC);
                }
            }
        }
        // Call parent constructor
        parent::__construct($this->query);
    }

    /**
     * Convert table to array. First row contains column names
     * @return array Collection of rows of columns values
     */
    public function & toArray()
    {
        // Create column headers
        $cols = array();

        // Fill material categories columns
        for ($i = 0; $i < $this->structureCount; $i++) {
            $cols[] = 'Категория №'.$i;
        }

        // Add standard
        $cols = array_merge($cols, array('ID', 'Name', 'URL'));

        foreach ($this->fields as $field) {
            $cols[] = trim($field->Name);
        }

        $result = array($cols);

        // Iterate db data and perform rendering
        foreach ($this->query->exec() as & $db_row) {
            // Fill all row columns
            $cols = array();

            // Fill material categories columns
            $count = 0;
            foreach ($this->materialStructures[$db_row->id] as $structures) {
                $cols[] = $structures;
                $count++;
            }
            // Fill empty categories
            for ($i = $count; $i < $this->structureCount; $i++) {
                $cols[] = '';
            }

            // Add standard material data
            $cols[] = $db_row->id;
            $cols[] = $db_row->Name;
            $cols[] = $db_row->Url;

            foreach ($this->fields as $field) {
                $cols[] = trim($db_row[$field->Name]);
            }
            $result[] = $cols;
        }

        return $result;
    }

    /**
     * Universal SamsonCMS table render
     * @return string HTML SamsonCMS table
     */
    public function render( array $db_rows = null)
    {
        // Rows HTML
        $rows = '';

        // if no rows data is passed - perform db request
        if (!isset($db_rows)) {
            $db_rows = $this->query->exec();
        }

        // If we have table rows data
        if (is_array($db_rows)) {
            // Save quantity of rendering rows
            $this->last_render_count = sizeof($db_rows);

            // Debug info
            $rn = 0;
            $rc = sizeof($db_rows);

            // Iterate db data and perform rendering
            foreach ($db_rows as & $db_row) {
                if ($this->debug) {
                    elapsed('Rendering row ' . $rn++ . ' of ' . $rc . '(#' . $db_row->id . ')');
                }
                $rows .= $this->row($db_row, $this->pager);
                //catch(\Exception $e){ return e('Error rendering row#'.$rn.' of '.$rc.'(#'.$db_row->id.')'); }
            }
        } else {
            // No data found after query, external render specified
            $rows .= $this->emptyrow($this->query, $this->pager);
        }

        //elapsed('render pages: '.$this->pager->total);

        // Render table view
        return m()
            ->view($this->table_tmpl)
            ->headerRows($this->headerRows)
            ->set($this->pager)
            ->rows($rows)
            ->output();
    }

    /** @see \samson\cms\table\Table::row() */
    public function row(& $db_material, \samson\pager\Pager & $pager = null)
    {
        $cols = '';
        $num = 0;
        foreach ($this->fields as $field) {
            $cols .= m()->view('www/table/row/td')->field_Value($db_material[$field->Name])->num($num++)->output();
        }

        // Render row template
        return m()
            ->view($this->row_tmpl)
            ->cols($cols)
            ->material($db_material)
            ->pager($this->pager)
            ->output();
    }
}
