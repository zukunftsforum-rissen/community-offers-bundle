<?php
declare(strict_types=1);

/**
 * DCA -> PlantUML generator (text parser, no Contao bootstrap required)
 *
 * Usage:
 *   php tools/generate_data_model_puml.php \
 *      --dca-dir src/Resources/contao/dca \
 *      --out docs/diagrams/data-model.generated.puml
 *
 * Or provide explicit files:
 *   php tools/generate_data_model_puml.php \
 *      --files src/Resources/contao/dca/tl_co_access_request.php,src/Resources/contao/dca/tl_co_device.php,...
 */

final class DcaPumlGenerator
{
    public static function main(array $argv): int
    {
        $args = self::parseArgs($argv);

        $out = $args['out'] ?? 'docs/diagrams/data-model.generated.puml';
        $outPath = self::resolvePath($out);

        $files = [];
        if (!empty($args['files'])) {
            $files = array_filter(array_map('trim', explode(',', (string)$args['files'])));
        } else {
            $dcaDir = $args['dca-dir'] ?? null;
            if (!$dcaDir) {
                fwrite(STDERR, "ERROR: Provide --dca-dir or --files\n");
                return 2;
            }
            $files = self::findDcaFilesRecursive(self::resolvePath((string)$dcaDir));
        }

        $tables = [];
        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }
            $php = file_get_contents($file);
            if ($php === false) {
                continue;
            }

            $tableName = self::extractTableName($php, basename($file, '.php'));
            if ($tableName === null) {
                continue;
            }

            // Only generate for tl_co_* unless you explicitly pass others via --files
            if (!str_starts_with($tableName, 'tl_co_') && empty($args['files'])) {
                continue;
            }

            $fieldsBlock = self::extractBestFieldsBlock($php);
            $fields = $fieldsBlock ? self::parseFieldsSql($fieldsBlock) : [];
            $keys = self::extractKeys($php);

            $tables[$tableName] = [
                'fields' => $fields,
                'keys'   => $keys,
            ];
        }

        if (empty($tables)) {
            fwrite(STDERR, "ERROR: No tables parsed. Check your DCA path.\n");
            return 3;
        }

        ksort($tables);

        $puml = self::renderPlantUml($tables);
        self::ensureDir(dirname($outPath));
        file_put_contents($outPath, $puml);

        fwrite(STDOUT, "OK: wrote {$outPath}\n");
        return 0;
    }

    private static function parseArgs(array $argv): array
    {
        $out = [];
        for ($i = 1; $i < count($argv); $i++) {
            $a = $argv[$i];
            if (!str_starts_with($a, '--')) {
                continue;
            }
            $key = substr($a, 2);
            $val = true;
            if (str_contains($key, '=')) {
                [$key, $val] = explode('=', $key, 2);
            } elseif (($i + 1) < count($argv) && !str_starts_with($argv[$i + 1], '--')) {
                $val = $argv[++$i];
            }
            $out[$key] = $val;
        }
        return $out;
    }

    private static function resolvePath(string $p): string
    {
        // Keep relative paths relative to CWD
        return $p;
    }

    private static function ensureDir(string $dir): void
    {
        if ($dir === '' || $dir === '.' || is_dir($dir)) {
            return;
        }
        mkdir($dir, 0777, true);
    }

    private static function findDcaFilesRecursive(string $dir): array
    {
        $result = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($it as $file) {
            /** @var SplFileInfo $file */
            if (!$file->isFile()) continue;
            $name = $file->getFilename();
            if (preg_match('~^tl_.*\.php$~', $name)) {
                $result[] = $file->getPathname();
            }
        }
        return $result;
    }

    private static function extractTableName(string $php, string $fallback): ?string
    {
        if (preg_match("~\\\$GLOBALS\\['TL_DCA'\\]\\['([^']+)'\\]~", $php, $m)) {
            return $m[1];
        }
        // fallback: derive from filename if it looks like tl_*.php
        if (preg_match('~^tl_[a-z0-9_]+$~', $fallback)) {
            return $fallback;
        }
        return null;
    }

    private static function extractBestFieldsBlock(string $php): ?string
    {
        // Find all "'fields' => [" starts and pick the one with most 'sql' occurrences.
        if (!preg_match_all("~'fields'\\s*=>\\s*\\[~", $php, $mm, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $best = null;
        $bestCount = -1;

        foreach ($mm[0] as $match) {
            $startPos = (int) $match[1];
            $bracketPos = strpos($php, '[', $startPos);
            if ($bracketPos === false) continue;

            $block = self::extractBracketBlock($php, $bracketPos);
            if ($block === null) continue;

            $count = preg_match_all("~'sql'\\s*=>~", $block);
            if ($count > $bestCount) {
                $bestCount = $count;
                $best = $block;
            }
        }

        return $best;
    }

    private static function extractBracketBlock(string $s, int $openBracketPos): ?string
    {
        // Extract inside outermost [ ... ] starting at $openBracketPos (which points to '[')
        $i = $openBracketPos;
        $depth = 0;
        $inStr = false;
        $strCh = '';
        $esc = false;

        $start = $openBracketPos + 1;

        for (; $i < strlen($s); $i++) {
            $ch = $s[$i];

            if ($inStr) {
                if ($esc) {
                    $esc = false;
                    continue;
                }
                if ($ch === '\\') {
                    $esc = true;
                    continue;
                }
                if ($ch === $strCh) {
                    $inStr = false;
                }
                continue;
            }

            if ($ch === "'" || $ch === '"') {
                $inStr = true;
                $strCh = $ch;
                continue;
            }

            if ($ch === '[') {
                $depth++;
                continue;
            }

            if ($ch === ']') {
                $depth--;
                if ($depth === 0) {
                    return substr($s, $start, $i - $start);
                }
            }
        }

        return null;
    }

    private static function parseFieldsSql(string $fieldsBlock): array
    {
        // Parse "'field' => [ ... 'sql' => '...' ... ]"
        $fields = [];
        $offset = 0;

        while (preg_match("~'([a-zA-Z0-9_]+)'\\s*=>\\s*\\[~", $fieldsBlock, $m, PREG_OFFSET_CAPTURE, $offset)) {
            $field = $m[1][0];
            $pos = (int) $m[0][1];

            $arrOpen = strpos($fieldsBlock, '[', $pos);
            if ($arrOpen === false) break;

            $inner = self::extractBracketBlock($fieldsBlock, $arrOpen);
            if ($inner === null) break;

            if (preg_match("~'sql'\\s*=>\\s*(['\"])(.*?)\\1~s", $inner, $sm)) {
                $fields[$field] = trim($sm[2]);
            }

            $offset = $arrOpen + strlen($inner) + 2; // skip past closing ]
        }

        return $fields;
    }

    private static function extractKeys(string $php): array
    {
        $keys = [];
        if (!preg_match("~'keys'\\s*=>\\s*\\[(.*?)\\]\\s*(?:,|\\))~s", $php, $m)) {
            return $keys;
        }
        $inner = $m[1];

        if (preg_match_all("~'([^']+)'\\s*=>\\s*'([^']+)'~", $inner, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $row) {
                $keys[$row[1]] = $row[2];
            }
        }
        return $keys;
    }

    private static function guessRelations(array $tables): array
    {
        // Heuristic: detect relation fields across tables (no FK in schema)
        // - member id fields: memberId, requestedByMemberId
        // - device: dispatchToDeviceId referencing tl_co_device.deviceId
        // - jobId references tl_co_door_job.id
        $rels = [];

        $has = function(string $t, string $f) use ($tables): bool {
            return isset($tables[$t]) && isset($tables[$t]['fields'][$f]);
        };

        if (isset($tables['tl_co_door_job']) && $has('tl_co_door_job', 'requestedByMemberId')) {
            $rels[] = ['member', 'job', 'member.id → job.requestedByMemberId'];
        }
        if (isset($tables['tl_co_door_job']) && $has('tl_co_door_job', 'dispatchToDeviceId')) {
            $rels[] = ['device', 'job', 'device.deviceId → job.dispatchToDeviceId'];
        }
        if (isset($tables['tl_co_door_log']) && $has('tl_co_door_log', 'memberId')) {
            $rels[] = ['member', 'log', 'member.id → log.memberId'];
        }
        if (isset($tables['tl_co_door_log']) && $has('tl_co_door_log', 'jobId')) {
            $rels[] = ['job', 'log', 'job.id → log.jobId'];
        }

        // logical process relation: access_request approval -> member/group
        if (isset($tables['tl_co_access_request'])) {
            $rels[] = ['access_request', 'member', 'approval → member/group assignment (logical)'];
        }

        return $rels;
    }

    private static function renderPlantUml(array $tables): string
    {
        // Simple domain colors (B: subtle)
        $colors = [
            'tl_co_access_request' => '#F4FFF7',
            'tl_co_device'         => '#F7F2FF',
            'tl_co_door_job'       => '#FFF7F0',
            'tl_co_door_log'       => '#F3F8FF',
        ];

        $lines = [];
        $lines[] = "@startuml";
        $lines[] = "hide circle";
        $lines[] = "skinparam linetype ortho";
        $lines[] = "skinparam shadowing false";
        $lines[] = "skinparam roundcorner 12";
        $lines[] = "skinparam entity {";
        $lines[] = "  BorderColor #111111";
        $lines[] = "  FontName Monospace";
        $lines[] = "}";
        $lines[] = "";

        // Add tl_member placeholder (not from DCA)
        $lines[] = "entity \"tl_member (Contao)\" as member #FFFFFF {";
        $lines[] = "  * id : int <<PK>>";
        $lines[] = "  --";
        $lines[] = "  ...";
        $lines[] = "}";
        $lines[] = "";

        // Entities from DCA
        foreach ($tables as $table => $def) {
            $alias = self::aliasFor($table);
            $color = $colors[$table] ?? '#FFFFFF';
            $lines[] = "entity \"{$table}\" as {$alias} {$color} {";

            $fields = $def['fields'];
            // Prefer showing id first
            if (isset($fields['id'])) {
                $lines[] = "  * id : " . self::sqlTypeShort($fields['id']) . " <<PK>>";
                unset($fields['id']);
            }
            foreach ($fields as $name => $sql) {
                $lines[] = "  {$name} : " . self::sqlTypeShort($sql);
            }

            $lines[] = "}";
            $lines[] = "";
        }

        // Relations
        $rels = self::guessRelations($tables);
        foreach ($rels as [$a, $b, $label]) {
            // map pseudo names to aliases
            $aa = match($a) {
                'member' => 'member',
                'job' => self::aliasFor('tl_co_door_job'),
                'device' => self::aliasFor('tl_co_device'),
                'log' => self::aliasFor('tl_co_door_log'),
                'access_request' => self::aliasFor('tl_co_access_request'),
                default => $a
            };
            $bb = match($b) {
                'member' => 'member',
                'job' => self::aliasFor('tl_co_door_job'),
                'device' => self::aliasFor('tl_co_device'),
                'log' => self::aliasFor('tl_co_door_log'),
                'access_request' => self::aliasFor('tl_co_access_request'),
                default => $b
            };

            $isLogical = str_contains($label, '(logical)') || str_contains($label, 'approval');
            if ($isLogical) {
                $lines[] = "{$aa} ..> {$bb} : {$label}";
            } else {
                $lines[] = "{$aa} ||--o{ {$bb} : {$label}";
            }
        }

        $lines[] = "";
        $lines[] = "@enduml";
        $lines[] = "";

        return implode("\n", $lines);
    }

    private static function aliasFor(string $table): string
    {
        return match($table) {
            'tl_co_access_request' => 'access_request',
            'tl_co_device' => 'device',
            'tl_co_door_job' => 'job',
            'tl_co_door_log' => 'log',
            default => preg_replace('~[^a-z0-9_]+~i', '_', $table),
        };
    }

    private static function sqlTypeShort(string $sql): string
    {
        // Keep only the type prefix and maybe NOT NULL/default info (short)
        $s = trim($sql);
        $s = preg_replace('~\s+unsigned\b~i', '', $s);
        // Take up to first "default" or "NOT NULL" etc but keep type
        $s = preg_replace('~\s+NOT NULL\b~i', ' NOT NULL', $s);
        $s = preg_replace("~\s+default\s+('([^']*)'|[0-9]+)\b~i", ' default $1', $s);
        // shorten
        if (strlen($s) > 60) {
            $s = substr($s, 0, 57) . '…';
        }
        return $s;
    }
}

exit(DcaPumlGenerator::main($_SERVER['argv']));