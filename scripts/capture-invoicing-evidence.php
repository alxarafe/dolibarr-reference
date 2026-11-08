<?php
// Parse successful INVOICE-ROUTES-REF-001 output; never overwrite a capture.
$root=dirname(__DIR__);
$capture=['captured_at_utc'=>gmdate('Y-m-d\TH:i:s\Z'),'command'=>'./bin/check-invoicing',
    'project'=>null,'metadata'=>null,'source_checks'=>[],'evidence'=>null,'cleanup_verified'=>false];
$functional=false;
foreach(explode("\n",stream_get_contents(STDIN)) as $line) {
    if(str_starts_with($line,'Invoicing profile project: ')) {$capture['project']=substr($line,strlen('Invoicing profile project: '));}
    foreach(['META '=>'metadata','SOURCE '=>'source_checks','EVIDENCE '=>'evidence'] as $prefix=>$key) {
        if(str_starts_with($line,$prefix)) {
            $value=json_decode(substr($line,strlen($prefix)),true,512,JSON_THROW_ON_ERROR);
            if($key==='source_checks') {$capture[$key][]=$value;} else {$capture[$key]=$value;}
        }
    }
    if($line==='INVOICING FUNCTIONAL CHECK PASSED') {$functional=true;}
    if($line==='INVOICING VALIDATION PASSED (cleanup verified)') {$capture['cleanup_verified']=true;}
}
if(!$functional || !$capture['cleanup_verified'] || !$capture['metadata'] || !$capture['evidence'] || !$capture['project']) {
    throw new RuntimeException('Incomplete or failed run; retain failure separately');
}
foreach(['fixture_sha256'=>'scripts/check-invoicing.php','runner_sha256'=>'bin/check-invoicing'] as $key=>$file) {
    if(!hash_equals($capture['metadata'][$key],hash_file('sha256',$root.'/'.$file))) {throw new RuntimeException('Tested script differs from current '.$file);}
}
$manifest=json_decode(file_get_contents($root.'/reference/characterization/INVOICE-ROUTES-REF-001-source-hashes.json'),true,512,JSON_THROW_ON_ERROR);
$expected=array_column($manifest['files'],'sha256','path');$seen=[];
foreach($capture['source_checks'] as $source) {
    if(($expected[$source['path']]??null)!==$source['image_sha256'] || $source['expected_sha256']!==$source['image_sha256'] || $source['source_commit']!==$manifest['source_commit']) {
        throw new RuntimeException('Source hash evidence mismatch');
    }
    $seen[$source['path']]=true;
}
if(count($seen)!==count($expected)) {throw new RuntimeException('Incomplete source checks');}
foreach(['INV-ROUTE-001','INV-ROUTE-002','INV-ROUTE-003','INV-ROUTE-004','INV-ROUTE-005'] as $route) {
    if(!in_array($route,array_column($capture['evidence']['observations'],'scenario'),true)) {throw new RuntimeException('Missing observed route '.$route);}
}
echo json_encode($capture,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
