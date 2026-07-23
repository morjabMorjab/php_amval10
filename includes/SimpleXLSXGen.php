<?php
class SimpleXLSXGen {
    private $rows = [];
    private $sheetName = "Sheet1";
    
    public function __construct($rows = [], $sheetName = "Sheet1") {
        $this->rows = $rows;
        $this->sheetName = $sheetName;
    }
    
    public function download($filename = "report.xlsx") {
        header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
        header("Content-Disposition: attachment; filename=" . $filename);
        header("Cache-Control: max-age=0");
        header("Pragma: no-cache");
        
        // ساخت فایل‌های XML
        $sharedStrings = [];
        $sheetRows = "";
        
        foreach($this->rows as $row) {
            $sheetRows .= "<row>";
            foreach($row as $cell) {
                $idx = array_search($cell, $sharedStrings);
                if($idx === false) {
                    $idx = count($sharedStrings);
                    $sharedStrings[] = $cell;
                }
                $sheetRows .= '<c t="inlineStr"><is><t>' . htmlspecialchars($cell, ENT_QUOTES, "UTF-8") . '</t></is></c>';
            }
            $sheetRows .= "</row>";
        }
        
        // فایل [Content_Types].xml
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>';
        
        // _rels/.rels
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
        
        // xl/_rels/workbook.xml.rels
        $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
        
        // xl/workbook.xml
        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . htmlspecialchars($this->sheetName) . '" sheetId="1" r:id="rId1"/></sheets></workbook>';
        
        // xl/worksheets/sheet1.xml
        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView tabSelected="1" workbookViewId="0" rightToLeft="1"><pane yOffset="0" xOffset="0" topLeftCell="A1" activePane="bottomRight" state="frozen"/></sheetView></sheetViews><sheetData>' . $sheetRows . '</sheetData></worksheet>';
        
        // xl/sharedStrings.xml
        $ss = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        foreach($sharedStrings as $s) {
            $ss .= '<si><t>' . htmlspecialchars($s, ENT_QUOTES, "UTF-8") . '</t></si>';
        }
        $ss .= '</sst>';
        
        // xl/styles.xml
        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs></styleSheet>';
        
        // ساخت ZIP
        $zip = new ZipArchive();
        $tmp = tempnam(sys_get_temp_dir(), "xlsx");
        
        if($zip->open($tmp, ZipArchive::CREATE) === TRUE) {
            $zip->addFromString("[Content_Types].xml", $contentTypes);
            $zip->addFromString("_rels/.rels", $rels);
            $zip->addFromString("xl/_rels/workbook.xml.rels", $workbookRels);
            $zip->addFromString("xl/workbook.xml", $workbook);
            $zip->addFromString("xl/worksheets/sheet1.xml", $sheet);
            $zip->addFromString("xl/sharedStrings.xml", $ss);
            $zip->addFromString("xl/styles.xml", $styles);
            $zip->close();
            
            readfile($tmp);
            unlink($tmp);
        }
        exit;
    }
}
