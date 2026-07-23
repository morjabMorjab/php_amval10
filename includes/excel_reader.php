<?php
class ExcelReader {
    
    public static function read($file_path) {
        if (!file_exists($file_path)) return [];
        
        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        
        // XLSX با SimpleXLSX
        if ($ext === 'xlsx') {
            return self::readXLSX($file_path);
        }
        
        // CSV/TSV
        return self::readCSV($file_path);
    }
    
    private static function readXLSX($file_path) {
        // روش ۱: SimpleXLSX
        $lib = __DIR__ . '/SimpleXLSX.php';
        if (file_exists($lib)) {
            require_once $lib;
            if (class_exists('SimpleXLSX')) {
                try {
                    $xlsx = SimpleXLSX::parse($file_path);
                    if ($xlsx && $xlsx->rows()) {
                        return $xlsx->rows();
                    }
                } catch (Exception $e) {}
            }
        }
        
        // روش ۲: ZipArchive ساده
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($file_path) === TRUE) {
                $sharedStrings = [];
                $ss = $zip->getFromName('xl/sharedStrings.xml');
                if ($ss) {
                    $xml = simplexml_load_string($ss);
                    if ($xml) {
                        foreach ($xml->si as $si) {
                            $t = '';
                            if (isset($si->t)) $t = (string)$si->t;
                            elseif (isset($si->r)) { foreach ($si->r as $r) $t .= (string)$r->t; }
                            $sharedStrings[] = $t;
                        }
                    }
                }
                
                $rows = [];
                $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
                if ($sheet) {
                    $xml = simplexml_load_string($sheet);
                    if ($xml && isset($xml->sheetData->row)) {
                        foreach ($xml->sheetData->row as $row) {
                            $rowData = [];
                            foreach ($row->c as $cell) {
                                $v = '';
                                if (isset($cell->v)) {
                                    $v = (string)$cell->v;
                                    if (isset($cell['t']) && (string)$cell['t'] === 's') {
                                        $idx = (int)$v;
                                        $v = $sharedStrings[$idx] ?? '';
                                    }
                                }
                                $rowData[] = trim($v);
                            }
                            if (!empty(array_filter($rowData))) {
                                $rows[] = $rowData;
                            }
                        }
                    }
                }
                $zip->close();
                if (!empty($rows)) return $rows;
            }
        }
        
        return [];
    }
    
    private static function readCSV($file_path) {
        $rows = [];
        $handle = @fopen($file_path, "r");
        if (!$handle) return [];
        
        // تشخیص BOM
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") rewind($handle);
        
        // تشخیص delimiter
        $first = fgets($handle);
        rewind($handle);
        $delim = (substr_count($first ?? '', "\t") > substr_count($first ?? '', ',')) ? "\t" : ',';
        
        while (($data = @fgetcsv($handle, 0, $delim, '"', '')) !== FALSE) {
            $clean = [];
            foreach ($data as $val) {
                $val = is_string($val) ? trim($val) : '';
                // فقط اگر UTF-8 نبود تبدیل کن
                if ($val !== '' && !mb_check_encoding($val, 'UTF-8')) {
                    $val = @mb_convert_encoding($val, 'UTF-8', 'auto');
                }
                $clean[] = $val;
            }
            if (!empty(array_filter($clean))) {
                $rows[] = $clean;
            }
        }
        fclose($handle);
        return $rows;
    }
    
    public static function mapColumns($headers) {
        $map = [
            'plate'=>null, 'name'=>null, 'status'=>null, 'type'=>null,
            'floor'=>null, 'location'=>null, 'recipient'=>null, 'center'=>null,
            'date'=>null, 'description'=>null
        ];
        
        $patterns = [
            'plate' => ['پلاک','plate','کد','شماره'],
            'name' => ['نام','name','شرح','عنوان','کالا'],
            'status' => ['وضعیت','status','حالت'],
            'type' => ['نوع','type','گونه'],
            'floor' => ['طبقه','floor'],
            'location' => ['مکان','location','محل'],
            'recipient' => ['تحویل','recipient','گیرنده','جمعدار'],
            'center' => ['مرکز','center','درمانگاه','واحد','شعبه'],
            'date' => ['تاریخ','date'],
            'description' => ['توضیحات','description','یادداشت'],
        ];
        
        foreach ($headers as $index => $header) {
            $h = trim(mb_strtolower((string)$header));
            foreach ($patterns as $key => $searches) {
                foreach ($searches as $search) {
                    if (mb_strpos($h, mb_strtolower($search)) !== false) {
                        $map[$key] = $index;
                        break 2;
                    }
                }
            }
        }
        
        return $map;
    }
}
?>
