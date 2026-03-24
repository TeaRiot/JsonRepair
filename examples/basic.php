<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Teariot\JsonRepair\JsonRepair;

echo "=== teariot/json-repair — Basic Examples ===\n\n";

// 1. Simple repair
echo "1. Unquoted keys, single quotes:\n";
$broken = "{name: 'John', age: 30}";
echo "   In:  {$broken}\n";
echo "   Out: " . JsonRepair::fix($broken) . "\n\n";

// 2. Trailing commas
echo "2. Trailing commas:\n";
$broken = '{"items": [1, 2, 3,], "active": true,}';
echo "   In:  {$broken}\n";
echo "   Out: " . JsonRepair::fix($broken) . "\n\n";

// 3. Comments
echo "3. Strip comments:\n";
$broken = '{"name": "John", /* inline */ "age": 30 // line comment
}';
echo "   Out: " . JsonRepair::fix($broken) . "\n\n";

// 4. Python constants
echo "4. Python constants:\n";
$broken = '{active: True, deleted: False, notes: None}';
echo "   In:  {$broken}\n";
echo "   Out: " . JsonRepair::fix($broken) . "\n\n";

// 5. Truncated JSON
echo "5. Truncated JSON:\n";
$broken = '{"users": [{"name": "John"';
echo "   In:  {$broken}\n";
echo "   Out: " . JsonRepair::fix($broken) . "\n\n";

// 6. MongoDB types
echo "6. MongoDB types:\n";
$broken = '{"_id": ObjectId("507f1f77"), "date": ISODate("2023-01-01")}';
echo "   Out: " . JsonRepair::fix($broken) . "\n\n";

// 7. Decode directly
echo "7. Repair + decode:\n";
$data = JsonRepair::decode("{name: 'John', scores: [95, 87,]}");
echo "   name: {$data['name']}, scores: " . implode(', ', $data['scores']) . "\n\n";

// 8. Safe try-fix
echo "8. tryFix (never throws):\n";
echo "   " . JsonRepair::tryFix("{broken: data}") . "\n\n";

// 9. Check needs repair
echo "9. needsRepair:\n";
echo "   '{\"ok\":1}' → " . (JsonRepair::needsRepair('{"ok":1}') ? 'yes' : 'no') . "\n";
echo "   '{ok:1}'     → " . (JsonRepair::needsRepair('{ok:1}') ? 'yes' : 'no') . "\n\n";
