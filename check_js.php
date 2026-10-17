<?php
// Walk through the JS tracking brace depth, flagging anomalies
$js = file_get_contents('app.js');
$lines = explode("\n", $js);

$depth = 0;
$minDepth = 0;
$minLine = 0;
$inString = false;
$strChar = '';
$inTemplate = 0; // nesting level
$escaped = false;

// Simple approach: find where depth goes negative (extra close brace)
$problems = [];
$depth = 0;

foreach ($lines as $lineNum => $line) {
    $lineNo = $lineNum + 1;
    // Skip comment lines (simple heuristic)
    $trimmed = ltrim($line);
    if (substr($trimmed, 0, 2) === '//') continue;
    
    $len = strlen($line);
    $inSingleQuote = false;
    $inDoubleQuote = false;
    $inTemplate = false;
    
    for ($i = 0; $i < $len; $i++) {
        $ch = $line[$i];
        $prev = $i > 0 ? $line[$i-1] : '';
        
        if ($prev === '\\') continue; // skip escaped chars (naive)
        
        if (!$inSingleQuote && !$inDoubleQuote && !$inTemplate) {
            if ($ch === "'") { $inSingleQuote = true; continue; }
            if ($ch === '"') { $inDoubleQuote = true; continue; }
            if ($ch === '`') { $inTemplate = true; continue; }
            if ($ch === '{') $depth++;
            if ($ch === '}') {
                $depth--;
                if ($depth < 0) {
                    $problems[] = "Line $lineNo: depth went negative ($depth) - EXTRA CLOSE BRACE: " . trim($line);
                    $depth = 0; // reset to continue scanning
                }
            }
        } elseif ($inSingleQuote && $ch === "'") {
            $inSingleQuote = false;
        } elseif ($inDoubleQuote && $ch === '"') {
            $inDoubleQuote = false;
        } elseif ($inTemplate && $ch === '`') {
            $inTemplate = false;
        }
    }
}

echo "Final depth: $depth\n";
echo "Problems found:\n";
foreach ($problems as $p) echo "  $p\n";
if (empty($problems)) echo "  (none detected at simple line level)\n";

// Also look for lines with just a single } that might be extra
echo "\n--- Searching for suspicious isolated closing braces ---\n";
$prevFnLine = 0;
foreach ($lines as $lineNum => $line) {
    $lineNo = $lineNum + 1;
    $trimmed = trim($line);
    if ($trimmed === '}' || $trimmed === '};') {
        // Check if previous function definition is far away
        // Just list lines 10-20 before as context
        $contextStart = max(0, $lineNum - 3);
        $context = '';
        for ($c = $contextStart; $c <= $lineNum; $c++) {
            $context .= ($c+1) . ': ' . $lines[$c] . "\n";
        }
        // Only flag if depth context seems off - just print them all
    }
}

// Better approach: track running depth and find where it goes to 0 prematurely
echo "\n--- Function boundaries (depth=0 moments) ---\n";
$depth = 0;
$zeroPoints = [];
foreach ($lines as $lineNum => $line) {
    $lineNo = $lineNum + 1;
    $trimmed = ltrim($line);
    if (substr($trimmed, 0, 2) === '//') continue;
    
    $len = strlen($line);
    for ($i = 0; $i < $len; $i++) {
        $ch = $line[$i];
        if ($ch === '{') $depth++;
        if ($ch === '}') {
            $depth--;
            if ($depth === 0) $zeroPoints[] = $lineNo;
            if ($depth < 0) $depth = 0;
        }
    }
}

// Print the last 20 zero-depth moments (end of top-level structures)
$tail = array_slice($zeroPoints, -25);
echo "Last 25 times depth hit 0 (end of top-level blocks):\n";
foreach ($tail as $ln) {
    echo "  Line $ln: " . rtrim($lines[$ln - 1]) . "\n";
}
