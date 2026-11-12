<?php
/**
 * A real gettext extractor for the TrustBio plugins.
 *
 * The previous script was regex-only and matched one shape of call:
 * a single string literal followed by the text domain. That silently
 * dropped every _n() in the plugin -- 51 call sites in trustbio-pro --
 * because a plural call has its domain in the fourth argument, not the
 * second, so the pattern never matched and nothing reported a miss.
 *
 * This walks PHP's own token stream instead, so the argument *positions*
 * decide what a call means. That is how wp-cli's make-pot works, and it
 * gets plurals, contexts and concatenated literals right.
 *
 * JS is scanned with a pattern, which is sound here: the block files call
 * __( 'text', 'domain' ) and nothing else. Any other i18n shape in JS is
 * reported as a warning rather than quietly skipped.
 *
 * Usage:
 *   php makepot.php <plugin-dir> <domain> <out.pot> [--header-from=<pot>] [--dry]
 */

// Directories that are not the plugin. 'tools' is this script's own home --
// it lives inside the free plugin so that it is version-controlled, and a
// build tool should not extract strings from itself. '.github' holds the
// deploy scripts, which are PHP but never run inside WordPress. Neither ships,
// so neither has translatable strings by definition.
const SKIP_DIRS = ['node_modules', 'vendor', '.wordpress-org', '.git', 'languages', 'tools', '.github'];

/* arg index => role. 'count' is read but never emitted. */
const FUNCS = [
    '__'           => ['single' => 0, 'domain' => 1],
    '_e'           => ['single' => 0, 'domain' => 1],
    'esc_html__'   => ['single' => 0, 'domain' => 1],
    'esc_html_e'   => ['single' => 0, 'domain' => 1],
    'esc_attr__'   => ['single' => 0, 'domain' => 1],
    'esc_attr_e'   => ['single' => 0, 'domain' => 1],
    '_x'           => ['single' => 0, 'context' => 1, 'domain' => 2],
    'esc_html_x'   => ['single' => 0, 'context' => 1, 'domain' => 2],
    'esc_attr_x'   => ['single' => 0, 'context' => 1, 'domain' => 2],
    '_n'           => ['single' => 0, 'plural' => 1, 'domain' => 3],
    '_nx'          => ['single' => 0, 'plural' => 1, 'context' => 3, 'domain' => 4],
    '_n_noop'      => ['single' => 0, 'plural' => 1, 'domain' => 2],
    '_nx_noop'     => ['single' => 0, 'plural' => 1, 'context' => 2, 'domain' => 3],
];

$entries  = [];   // key => entry
$warnings = [];

/* ------------------------------------------------------------------ */

/**
 * A gettext entry is identified by (msgctxt, msgid) alone -- the plural form
 * is part of the entry, not part of its identity. So __( 'Author' ) and
 * _n( 'Author', 'Authors', ... ) are ONE entry that happens to have a plural,
 * not two. Keying on the plural as well emits both, and msgfmt rejects the
 * file with "duplicate message definition".
 */
function add_entry(array &$entries, $single, $plural, $context, $ref, $comment, array &$warnings) {
    $key = ($context === null ? '' : $context . "\x04") . $single;
    if (!isset($entries[$key])) {
        $entries[$key] = [
            'single'   => $single,
            'plural'   => $plural,
            'context'  => $context,
            'refs'     => [],
            'comments' => [],
        ];
    } elseif ($plural !== null) {
        if ($entries[$key]['plural'] === null) {
            $entries[$key]['plural'] = $plural;
        } elseif ($entries[$key]['plural'] !== $plural) {
            /* Same singular, two different plurals: the source disagrees with
               itself and one of the two can never be translated. */
            $warnings[] = sprintf(
                '%s: "%s" already has plural "%s", this call says "%s"',
                $ref, $single, $entries[$key]['plural'], $plural
            );
        }
    }
    if (!in_array($ref, $entries[$key]['refs'], true)) {
        $entries[$key]['refs'][] = $ref;
    }
    if ($comment !== null && !in_array($comment, $entries[$key]['comments'], true)) {
        $entries[$key]['comments'][] = $comment;
    }
}

/** Decode a PHP string literal, or return null when it is not a plain literal. */
function decode_literal($raw) {
    $q = $raw[0];
    $body = substr($raw, 1, -1);
    if ($q === "'") {
        return preg_replace_callback('/\\\\(.)/', function ($m) {
            return ($m[1] === "'" || $m[1] === '\\') ? $m[1] : '\\' . $m[1];
        }, $body);
    }
    /* Double quotes: a variable or an expression makes it non-literal. */
    if (preg_match('/(?<!\\\\)\$\{?[a-zA-Z_{]/', $body)) {
        return null;
    }
    $map = ['n' => "\n", 't' => "\t", 'r' => "\r", 'v' => "\v", 'e' => "\e",
            'f' => "\f", '\\' => '\\', '$' => '$', '"' => '"'];
    return preg_replace_callback('/\\\\(.)/', function ($m) use ($map) {
        return $map[$m[1]] ?? '\\' . $m[1];
    }, $body);
}

/**
 * Turn one argument's token run into a literal string, or null.
 * Handles 'a' . 'b' concatenation, which make-pot also folds.
 */
function arg_to_literal(array $tokens) {
    $out = '';
    $seen = false;
    foreach ($tokens as $t) {
        if (is_array($t)) {
            if ($t[0] === T_WHITESPACE || $t[0] === T_COMMENT || $t[0] === T_DOC_COMMENT) {
                continue;
            }
            if ($t[0] === T_CONSTANT_ENCAPSED_STRING) {
                $piece = decode_literal($t[1]);
                if ($piece === null) {
                    return null;
                }
                $out .= $piece;
                $seen = true;
                continue;
            }
            return null;
        }
        if ($t === '.') {
            continue;
        }
        return null;
    }
    return $seen ? $out : null;
}

function scan_php($path, $rel, $domain, array &$entries, array &$warnings) {
    $src = file_get_contents($path);
    $tokens = token_get_all($src);
    $n = count($tokens);

    /* Keep the most recent translators: comment, with the line it ended on. */
    $lastComment = null;
    $lastCommentLine = -10;

    for ($i = 0; $i < $n; $i++) {
        $t = $tokens[$i];

        if (is_array($t) && ($t[0] === T_COMMENT || $t[0] === T_DOC_COMMENT)) {
            if (preg_match('/translators:/i', $t[1])) {
                $clean = preg_replace('#^\s*(/\*+|//|\*|\#)\s?#m', '', $t[1]);
                /* A one-line /* ... *\/ comment closes on the same line, so the
                   terminator is at the END of the text, not the start of a line. */
                $clean = trim(preg_replace('#\s*\*+/\s*$#', '', $clean));
                $clean = trim(preg_replace('/\s*\n\s*/', ' ', $clean));
                $lastComment = $clean;
                $lastCommentLine = $t[2] + substr_count($t[1], "\n");
            }
            continue;
        }

        if (!is_array($t) || $t[0] !== T_STRING) {
            continue;
        }
        $fn = $t[1];
        if (!array_key_exists($fn, FUNCS)) {
            continue;
        }

        /* Not a call to the global function if it is a method or a definition. */
        $p = $i - 1;
        while ($p >= 0 && is_array($tokens[$p]) && $tokens[$p][0] === T_WHITESPACE) {
            $p--;
        }
        if ($p >= 0) {
            $prev = $tokens[$p];
            if (is_array($prev) && in_array($prev[0], [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW], true)) {
                continue;
            }
            if (defined('T_NULLSAFE_OBJECT_OPERATOR') && is_array($prev) && $prev[0] === T_NULLSAFE_OBJECT_OPERATOR) {
                continue;
            }
        }

        /* Must be followed by '(' */
        $q = $i + 1;
        while ($q < $n && is_array($tokens[$q]) && $tokens[$q][0] === T_WHITESPACE) {
            $q++;
        }
        if ($q >= $n || $tokens[$q] !== '(') {
            continue;
        }

        /* Split the argument list on top-level commas. */
        $depth = 0;
        $args = [];
        $cur = [];
        $j = $q;
        for (; $j < $n; $j++) {
            $tk = $tokens[$j];
            if ($tk === '(' || $tk === '[' || $tk === '{') {
                $depth++;
                if ($depth === 1) { continue; }
            } elseif ($tk === ')' || $tk === ']' || $tk === '}') {
                $depth--;
                if ($depth === 0) { $args[] = $cur; break; }
            } elseif ($tk === ',' && $depth === 1) {
                $args[] = $cur;
                $cur = [];
                continue;
            }
            if ($depth >= 1) { $cur[] = $tk; }
        }

        $spec = FUNCS[$fn];
        $line = $t[2];
        $ref  = $rel . ':' . $line;

        $domainIdx = $spec['domain'];
        if (!isset($args[$domainIdx])) {
            $warnings[] = "$ref: $fn() has no text-domain argument";
            continue;
        }
        $dom = arg_to_literal($args[$domainIdx]);
        if ($dom === null) {
            $warnings[] = "$ref: $fn() text domain is not a literal";
            continue;
        }
        if ($dom !== $domain) {
            continue; /* another plugin's domain; not ours to extract */
        }

        $single = arg_to_literal($args[$spec['single']] ?? []);
        if ($single === null) {
            $warnings[] = "$ref: $fn() first argument is not a literal string";
            continue;
        }
        $plural = null;
        if (isset($spec['plural'])) {
            $plural = arg_to_literal($args[$spec['plural']] ?? []);
            if ($plural === null) {
                $warnings[] = "$ref: $fn() plural argument is not a literal string";
                continue;
            }
        }
        $context = null;
        if (isset($spec['context'])) {
            $context = arg_to_literal($args[$spec['context']] ?? []);
            if ($context === null) {
                $warnings[] = "$ref: $fn() context argument is not a literal string";
                continue;
            }
        }

        /* A translators: comment counts when it sits on the line above. */
        $comment = ($lastComment !== null && $line - $lastCommentLine <= 1) ? $lastComment : null;

        /* A placeholder with no translators: comment is a real i18n bug. */
        if ($comment === null && preg_match('/%[0-9]+\$|%s.*%s|%d.*%d|%s.*%d|%d.*%s/', $single)) {
            $warnings[] = "$ref: multiple placeholders with no translators: comment";
        }

        add_entry($entries, $single, $plural, $context, $ref, $comment, $warnings);
        $i = $j;
    }
}

function scan_js($path, $rel, $domain, array &$entries, array &$warnings) {
    $src = file_get_contents($path);
    $lines = explode("\n", $src);
    $d = preg_quote($domain, '/');
    foreach ($lines as $idx => $line) {
        $ln = $idx + 1;
        if (preg_match_all(
            '/(?<![\w$.])__\(\s*(([\'"])((?:\\\\.|(?!\2).)*)\2)\s*,\s*[\'"]' . $d . '[\'"]\s*\)/',
            $line, $m, PREG_SET_ORDER
        )) {
            foreach ($m as $hit) {
                $lit = decode_literal($hit[1]);
                if ($lit === null) { continue; }
                add_entry($entries, $lit, null, null, $rel . ":" . $ln, null, $warnings);
            }
        }
        /* Anything plural- or context-shaped in JS would need a real parser. */
        if (preg_match('/(?<![\w$.])(_n|_x|_nx)\(/', $line)) {
            $warnings[] = "$rel:$ln: JS plural/context call is not extracted";
        }
    }
}

/* ------------------------------------------------------------------ */

function po_escape($s) {
    $s = str_replace(['\\', '"'], ['\\\\', '\\"'], $s);
    $s = str_replace(["\n", "\t", "\r"], ['\\n', '\\t', '\\r'], $s);
    return $s;
}

function po_string($s) {
    /* Keep a trailing newline on its own continuation line, like msgfmt does. */
    return '"' . po_escape($s) . '"';
}

/* ------------------------------------------------------------------ */

$dir    = rtrim($argv[1], "/\\");
$domain = $argv[2];
$out    = $argv[3];
$dry    = in_array('--dry', $argv, true);
$headerFrom = null;
foreach ($argv as $a) {
    if (strpos($a, '--header-from=') === 0) { $headerFrom = substr($a, 14); }
}

$it = new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        function ($f) {
            return !($f->isDir() && in_array($f->getFilename(), SKIP_DIRS, true));
        }
    )
);
$files = [];
foreach ($it as $f) {
    $ext = strtolower($f->getExtension());
    if ($ext === 'php' || $ext === 'js') {
        $files[] = $f->getPathname();
    }
}
sort($files);

foreach ($files as $path) {
    $rel = str_replace('\\', '/', substr($path, strlen($dir) + 1));
    if (substr($rel, -4) === '.php') {
        scan_php($path, $rel, $domain, $entries, $warnings);
    } else {
        scan_js($path, $rel, $domain, $entries, $warnings);
    }
}

/* Order by first reference, so the file reads in source order. */
uasort($entries, function ($a, $b) {
    $pa = explode(':', $a['refs'][0]);
    $pb = explode(':', $b['refs'][0]);
    $la = (int) array_pop($pa);
    $lb = (int) array_pop($pb);
    return [implode(':', $pa), $la] <=> [implode(':', $pb), $lb];
});

$plurals = 0;
foreach ($entries as $e) { if ($e['plural'] !== null) { $plurals++; } }

fwrite(STDERR, sprintf(
    "%s: %d files, %d entries (%d plural, %d with context), %d warnings\n",
    $domain, count($files), count($entries), $plurals,
    count(array_filter($entries, fn($e) => $e['context'] !== null)), count($warnings)
));
foreach ($warnings as $w) { fwrite(STDERR, "  ! $w\n"); }

/* ---- header ---- */
$header = '';
if ($headerFrom && file_exists($headerFrom)) {
    $src = file_get_contents($headerFrom);
    $pos = strpos($src, "\n\n");
    $header = $pos === false ? $src : substr($src, 0, $pos);
    $header = preg_replace('/"POT-Creation-Date: [^"]*\\\\n"/',
        '"POT-Creation-Date: ' . gmdate('Y-m-d H:iO') . '\\n"', $header);
} else {
    $header = "msgid \"\"\nmsgstr \"\"\n";
}

$body = $header . "\n\n";
foreach ($entries as $e) {
    foreach ($e['comments'] as $c) {
        $body .= '#. ' . $c . "\n";
    }
    /* References wrap, as make-pot does, rather than running to 400 columns. */
    $line = '#:';
    foreach ($e['refs'] as $r) {
        if (strlen($line) + strlen($r) + 1 > 78) {
            $body .= $line . "\n";
            $line = '#:';
        }
        $line .= ' ' . $r;
    }
    $body .= $line . "\n";
    if ($e['context'] !== null) {
        $body .= 'msgctxt ' . po_string($e['context']) . "\n";
    }
    $body .= 'msgid ' . po_string($e['single']) . "\n";
    if ($e['plural'] !== null) {
        $body .= 'msgid_plural ' . po_string($e['plural']) . "\n";
        $body .= "msgstr[0] \"\"\n";
        $body .= "msgstr[1] \"\"\n";
    } else {
        $body .= "msgstr \"\"\n";
    }
    $body .= "\n";
}

if ($dry) {
    fwrite(STDERR, "dry run; nothing written\n");
} else {
    file_put_contents($out, $body);
    fwrite(STDERR, "wrote $out (" . strlen($body) . " bytes)\n");
}
