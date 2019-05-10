<?php

interface kireport_general_Interface {
    public function getPdfSubreportData(kireport_PDF $pdf, $reportName, $args);
    public function executeCalcedFieldFunction($kiReportClass, stdClass $cFld);
}
