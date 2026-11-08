<?php
// Extract observed runner output without normalizing business values or IDs.
const EXPECTED_OBSERVATIONS = 28;
const EXPECTED_HTTP_RECORDS = 4;
const HISTORICAL_SHA256 = 'd96b3614eac068aec15e8b0e9a3143761c6b83c97fe3988f96b9fd936cd2ac01';
$root = dirname(__DIR__);
$manifest = json_decode(file_get_contents($root.'/reference/characterization/ORDER-SHIPMENT-REF-001-source-hashes.json'), true, 512, JSON_THROW_ON_ERROR);
$log = stream_get_contents(STDIN);
$capture = ['capture_completed_at_utc'=>gmdate('Y-m-d\TH:i:s\Z'),
    'command'=>'./bin/check-shipping', 'project'=>null, 'metadata'=>null,
    'context'=>null, 'source_checks'=>[], 'observations'=>[], 'http'=>[], 'assertions'=>[],
    'functional_passed'=>false, 'cleanup_verified'=>false];
foreach (explode("\n", $log) as $line) {
    if (str_starts_with($line, 'Shipping profile project: ')) {
        $capture['project'] = substr($line, strlen('Shipping profile project: '));
    }
    foreach (['META '=>'metadata', 'CONTEXT '=>'context', 'SOURCE '=>'source_checks', 'OBS '=>'observations', 'HTTP '=>'http'] as $prefix=>$key) {
        if (str_starts_with($line, $prefix)) {
            $value = json_decode(substr($line, strlen($prefix)), true, 512, JSON_THROW_ON_ERROR);
            if (in_array($key, ['metadata','context'], true)) { $capture[$key]=$value; }
            else { $capture[$key][]=$value; }
        }
    }
    if (str_starts_with($line, 'PASS: ')) { $capture['assertions'][]=substr($line, 6); }
    if ($line === 'SHIPPING FUNCTIONAL CHECK PASSED') { $capture['functional_passed']=true; }
    if ($line === 'SHIPPING VALIDATION PASSED (cleanup verified)') { $capture['cleanup_verified']=true; }
}
if (!$capture['functional_passed'] || !$capture['cleanup_verified'] || count($capture['source_checks'])!==count($manifest['files'])
    || count($capture['observations'])!==EXPECTED_OBSERVATIONS || count($capture['http'])!==EXPECTED_HTTP_RECORDS || !$capture['metadata'] || !$capture['context']) {
    throw new RuntimeException('Incomplete capture: do not publish as successful evidence');
}
foreach (['fixture_sha256'=>'scripts/check-shipping.php','runner_sha256'=>'bin/check-shipping'] as $key=>$file) {
    if (!hash_equals($capture['metadata'][$key], hash_file('sha256', $root.'/'.$file))) {
        throw new RuntimeException('Captured script differs from current '.$file);
    }
}
$historical=$root.'/reference/characterization/ORDER-SHIPMENT-REF-001-observed.json';
if (hash_file('sha256',$historical)!==HISTORICAL_SHA256) {
    throw new RuntimeException('Historical evidence changed');
}
$capture['historical_capture']=['path'=>basename($historical),'sha256'=>hash_file('sha256',$historical),'observations'=>21];
echo json_encode($capture, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
