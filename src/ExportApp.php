<?php
namespace samson\cms\export;

class ExportApp extends \samson\cms\App
{
    /** @var string Application name */
    public $app_name = 'Выгрузка';

    /** @var string	Application identifier */
    protected $id = 'export';

    /** @var bool Hide from menu */
    public $hide = true;

    /** Generic controller
     * @var $structure
     */
    public function __HANDLER($structure = null)
    {
        $this->view('www/index')
            ->table(new Table($structure))
            ->structures($structure)
            ->title('Выгрузка материалов');
    }

    /**
     * Controller to output csv file of all materials for structure
     * @var $structure
     * @var $delimiter
     * @var $enclosure
     */
    public function __tocsv($structure, $delimiter = ',', $enclosure = '"')
    {
        s()->async(true);

        ini_set('memory_limit', '2048M');

        // Output file from browser
        header('Content-Description: File Transfer');
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename=Export'.date('dmY').'.csv');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');

        // Write to php temp because php natively support csv files creation
        $handle = fopen('php://temp', 'r+');
        fputs($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM
        foreach ((new Table($structure))->toArray() as $line) {
            fputcsv($handle, $line, ';');
        }

        // Read file from temp
        rewind($handle);
        $csv = '';
        while (!feof($handle)) {
            $csv .= fread($handle, 8192);
        }
        fclose($handle);

        // Convert to Excel readable format
        echo mb_convert_encoding($csv, 'Windows-1251', 'UTF-8');
    }
}
