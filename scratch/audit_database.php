<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$tables = DB::select('SHOW TABLES');
$dbName = config('database.connections.mysql.database');
$keyName = "Tables_in_" . $dbName;

echo "Database: $dbName\n";
echo "Auditing tables for 'reciter_id'...\n";

$referencingTables = [];

foreach ($tables as $tableObj) {
    $tableName = $tableObj->$keyName;
    if (Schema::hasColumn($tableName, 'reciter_id')) {
        $count = DB::table($tableName)->where('reciter_id', 2)->count();
        $count24 = DB::table($tableName)->where('reciter_id', 24)->count();
        echo "Table: {$tableName} | reciter_id=2 count: {$count} | reciter_id=24 count: {$count24}\n";
        $referencingTables[$tableName] = [
            'id_2_count' => $count,
            'id_24_count' => $count24,
        ];
    }
}

// Also check the reciters table
$reciter2Exists = DB::table('reciters')->where('id', 2)->exists();
$reciter24Exists = DB::table('reciters')->where('id', 24)->exists();
echo "\nReciter ID 2 exists in 'reciters': " . ($reciter2Exists ? 'YES' : 'NO') . "\n";
echo "Reciter ID 24 exists in 'reciters': " . ($reciter24Exists ? 'YES' : 'NO') . "\n";

if ($reciter24Exists) {
    $r24 = DB::table('reciters')->where('id', 24)->first();
    echo "Reciter 24 details: Slug: {$r24->slug} | AR: {$r24->name_arabic} | EN: {$r24->name_english} | Source: {$r24->source}\n";
}
if ($reciter2Exists) {
    $r2 = DB::table('reciters')->where('id', 2)->first();
    echo "Reciter 2 details: Slug: {$r2->slug} | AR: {$r2->name_arabic} | EN: {$r2->name_english} | Source: {$r2->source}\n";
}
