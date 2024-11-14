<?php
$debug = true;

$sampleId           = $this['sample_id'];
$analyteId          = $this['analyte_id'];
$panelId            = $this['panel_id'];
$reportableResult   = $this['reportable_result'];

$detected = 'Detected';

$calcName = "Report to State Calculation $sampleId";

/** @var DB $db */
$db = $this->api->db;

if ($debug) $this->log_debug("$calcName: START");

$clientSiteId = $db->dsql()->table('requisition_sample', 'rs')
    ->join('requisition.id','rs.requisition_id','inner','req')
    ->field('client_site_id','req')
    ->where('rs.sample_id',$sampleId)
    ->getOne();

if ($clientSiteId != 12) {
    if ($debug) $this->log_debug("$calcName: Exit 0.5. $clientSiteId not a test client site");
    return;
}

$statusIds = $db->dsql()->table('status')
    ->field(['id', 'name'])
    ->where('name', ['Cancelled', 'New'])
    ->get();

$statusIds = array_column($statusIds, 'id', 'name');
$statusIdCancelled = $statusIds['Cancelled'];
$statusIdNew  = $statusIds['New'];

$woundPanelId   = 1032;
$panels         = [$woundPanelId];

if (!in_array($panelId,$panels)){
    if ($debug) $this->log_debug("$calcName: Exit 1. Result not coming from a valid panel.");
    return;
}

$mappings = [
//---------------------------------------------------------------------------------------
    //mecA
    ['source_id' => 1230, 'gene_id' => 1201],
//---------------------------------------------------------------------------------------
    //blaKPC
    ['source_id' => 1206, 'gene_id' => 1300],
    ['source_id' => 1214, 'gene_id' => 1300],
    ['source_id' => 1295, 'gene_id' => 1300],
    ['source_id' => 1216, 'gene_id' => 1300],
    ['source_id' => 1219, 'gene_id' => 1300],
    ['source_id' => 1220, 'gene_id' => 1300],
    ['source_id' => 1221, 'gene_id' => 1300],
    ['source_id' => 1222, 'gene_id' => 1300],
    ['source_id' => 1223, 'gene_id' => 1300],
    ['source_id' => 1224, 'gene_id' => 1300],
    ['source_id' => 1227, 'gene_id' => 1300],
    ['source_id' => 1228, 'gene_id' => 1300],
//---------------------------------------------------------------------------------------
    //blaNDM
    ['source_id' => 1206, 'gene_id' => 1299],
    ['source_id' => 1214, 'gene_id' => 1299],
    ['source_id' => 1295, 'gene_id' => 1299],
    ['source_id' => 1216, 'gene_id' => 1299],
    ['source_id' => 1219, 'gene_id' => 1299],
    ['source_id' => 1220, 'gene_id' => 1299],
    ['source_id' => 1221, 'gene_id' => 1299],
    ['source_id' => 1222, 'gene_id' => 1299],
    ['source_id' => 1223, 'gene_id' => 1299],
    ['source_id' => 1224, 'gene_id' => 1299],
    ['source_id' => 1227, 'gene_id' => 1299],
    ['source_id' => 1228, 'gene_id' => 1299],
//---------------------------------------------------------------------------------------
    //CTX-M1
    ['source_id' => 1206, 'gene_id' => 1200],
    ['source_id' => 1214, 'gene_id' => 1200],
    ['source_id' => 1295, 'gene_id' => 1200],
    ['source_id' => 1216, 'gene_id' => 1200],
    ['source_id' => 1219, 'gene_id' => 1200],
    ['source_id' => 1220, 'gene_id' => 1200],
    ['source_id' => 1221, 'gene_id' => 1200],
    ['source_id' => 1222, 'gene_id' => 1200],
    ['source_id' => 1223, 'gene_id' => 1200],
    ['source_id' => 1224, 'gene_id' => 1200],
    ['source_id' => 1227, 'gene_id' => 1200],
    ['source_id' => 1228, 'gene_id' => 1200],
//---------------------------------------------------------------------------------------
    //vanA
    ['source_id' => 1296, 'gene_id' => 1202],
//---------------------------------------------------------------------------------------
    //vanB
    ['source_id' => 1296, 'gene_id' => 1301],
];

$woundAnalyteIds = array_unique(array_merge(array_column($mappings, 'source_id'), array_column($mappings, 'gene_id')));

$rows = $db->dsql()->table('result', 'r')
    ->field('id', 'r')
    ->field('analyte_id', 'r')
    ->field('reportable_result', 'r')
    ->where('r.analyte_id','in', array_values($woundAnalyteIds))
    ->where('r.sample_id', $sampleId)
    ->where('r.status_id', '<>', $statusIdCancelled)
    ->where('r.retest_number', $this['retest_number'])
    ->get();

if ($debug) $this->log_debug("$calcName: run 1a", [
    'rows' => $rows
]);

$results = [];
foreach ($rows as $r) {
    $results[$r['analyte_id']] = [
        'id'                => $r['id'],
        'reportable_result' => $r['analyte_id'] == $analyteId ? $reportableResult : $r['reportable_result'],
    ];

    if(!isset($results[$r['analyte_id']]) || $results[$r['analyte_id']] == ''){
        if ($debug) $this->log_debug("$calcName: Exit 2. Not all analytes from Wound panel are in.");
        return;
    }
}


foreach ($mappings as $mapping) {
    $analyteIdSource    = $mapping['source_id'];
    $analyteIdGene      = $mapping['gene_id'];

    $reportableOrganism = $results[$analyteIdSource]['reportable_result'];
    $reportableGene     = $results[$analyteIdGene]['reportable_result'];

    $resultOrganismId   = $results[$analyteIdSource]['id'];

    if ($reportableOrganism == $detected && $reportableGene == $detected){
        $db->dsql()->table('result')
            ->set('reportable_to_state', 1)
            ->where('id', $resultOrganismId)
            ->update();

        if ($debug) $this->log_debug("$calcName: Info. Result reported to state. Organism ID $analyteIdSource");
    } else {
        if ($debug) $this->log_debug("$calcName: Info. Result not reported to state. Organism ID $analyteIdSource");
    }
}

if ($debug) $this->log_debug("$calcName: END");