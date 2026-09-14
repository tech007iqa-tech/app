<?php
/**
 * Documentation Module - Knowledge Base & Guidelines Hub
 * Renders technical blueprints, SOPs, and system directives with native Markdown parsing.
 */

/**
 * Robust Native PHP Markdown-to-HTML Parser
 */
if (!function_exists('render_markdown_doc')) {
    function render_markdown_doc(string $markdown): string {
        $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);

        // 1. Extract fenced code blocks
        $codeBlocks = [];
        $markdown = preg_replace_callback('/```([a-zA-Z0-9_\-]*)\n([\s\S]*?)\n```/', function ($matches) use (&$codeBlocks) {
            $lang = strtolower(trim($matches[1]));
            $code = htmlspecialchars($matches[2], ENT_QUOTES, 'UTF-8');
            $id = '###CODE_BLOCK_' . count($codeBlocks) . '###';
            $codeBlocks[$id] = [
                'lang' => $lang ?: 'code',
                'code' => $code
            ];
            return $id;
        }, $markdown);

        // 2. Block processing
        $lines = explode("\n", $markdown);
        $output = [];
        $inList = false;
        $listType = null;
        $inTable = false;
        $tableRows = [];

        $flushList = function() use (&$output, &$inList, &$listType) {
            if ($inList) {
                $output[] = "</$listType>";
                $inList = false;
                $listType = null;
            }
        };

        $flushTable = function() use (&$output, &$inTable, &$tableRows) {
            if ($inTable && !empty($tableRows)) {
                $html = '<div class="table-responsive doc-table-wrap"><table class="data-table">';
                $header = array_shift($tableRows);
                $html .= '<thead><tr>';
                foreach ($header as $th) {
                    $html .= '<th>' . render_inline_markdown(trim($th)) . '</th>';
                }
                $html .= '</tr></thead><tbody>';
                foreach ($tableRows as $row) {
                    $html .= '<tr>';
                    foreach ($row as $td) {
                        $html .= '<td>' . render_inline_markdown(trim($td)) . '</td>';
                    }
                    $html .= '</tr>';
                }
                $html .= '</tbody></table></div>';
                $output[] = $html;
                $tableRows = [];
                $inTable = false;
            }
        };

        $inBlockquote = false;
        $blockquoteLines = [];
        $flushBlockquote = function() use (&$output, &$inBlockquote, &$blockquoteLines) {
            if ($inBlockquote && !empty($blockquoteLines)) {
                $first = trim($blockquoteLines[0]);
                $isAlert = false;
                $alertType = 'note';
                $alertTitle = 'Note';
                if (preg_match('/^\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\]/i', $first, $m)) {
                    $isAlert = true;
                    $alertType = strtolower($m[1]);
                    $alertTitle = ucfirst($alertType);
                    $blockquoteLines[0] = preg_replace('/^\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\]\s*/i', '', $blockquoteLines[0]);
                }
                $body = render_inline_markdown(implode('<br>', array_filter($blockquoteLines)));
                if ($isAlert) {
                    $icons = [
                        'note' => 'ℹ️',
                        'tip' => '💡',
                        'important' => '⚠️',
                        'warning' => '🚨',
                        'caution' => '🛑'
                    ];
                    $ico = $icons[$alertType] ?? 'ℹ️';
                    $output[] = "<div class=\"alert doc-alert alert-{$alertType}\"><div class=\"doc-alert-title\">{$ico} {$alertTitle}</div><div>{$body}</div></div>";
                } else {
                    $output[] = "<blockquote class=\"doc-quote\">{$body}</blockquote>";
                }
                $blockquoteLines = [];
                $inBlockquote = false;
            }
        };

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Code block placeholder
            if (preg_match('/^###CODE_BLOCK_\d+###$/', $trimmed)) {
                $flushList();
                $flushTable();
                $flushBlockquote();
                $output[] = $trimmed;
                continue;
            }

            // Horizontal divider
            if (preg_match('/^(---|\*\*\*|___)\s*$/', $trimmed)) {
                $flushList();
                $flushTable();
                $flushBlockquote();
                $output[] = '<hr class="doc-divider">';
                continue;
            }

            // Headings (# to ######)
            if (preg_match('/^(#{1,6})\s+(.*)$/', $trimmed, $m)) {
                $flushList();
                $flushTable();
                $flushBlockquote();
                $level = strlen($m[1]);
                $text = render_inline_markdown($m[2]);
                $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', strip_tags($m[2])));
                $output[] = "<h{$level} id=\"{$slug}\" class=\"doc-heading\">{$text}</h{$level}>";
                continue;
            }

            // Blockquotes & Alerts
            if (preg_match('/^>\s*(.*)$/', $line, $m)) {
                $flushList();
                $flushTable();
                $inBlockquote = true;
                $blockquoteLines[] = $m[1];
                continue;
            } else {
                $flushBlockquote();
            }

            // Tables: | col | col |
            if (preg_match('/^\|(.+)\|$/', $trimmed, $m)) {
                $flushList();
                // Check separator row |--|:--|
                if (preg_match('/^\|[\s\-:]+(\|[\s\-:]+)+\|$/', $trimmed)) {
                    continue;
                }
                $cells = explode('|', trim($trimmed, '|'));
                $tableRows[] = $cells;
                $inTable = true;
                continue;
            } else {
                $flushTable();
            }

            // Unordered List (- or *)
            if (preg_match('/^[\*\-]\s+(.*)$/', $trimmed, $m)) {
                $flushTable();
                $item = $m[1];
                // Task list checkboxes: - [x] or - [ ]
                if (preg_match('/^\[([ xX])\]\s*(.*)$/', $item, $tm)) {
                    $isChecked = strtolower($tm[1]) === 'x';
                    $checkClass = $isChecked ? 'task-checked' : 'task-unchecked';
                    $checkIcon = $isChecked ? '☑️' : '⬜';
                    $item = "<span class=\"task-item {$checkClass}\">{$checkIcon} " . render_inline_markdown($tm[2]) . "</span>";
                } else {
                    $item = render_inline_markdown($item);
                }
                if (!$inList || $listType !== 'ul') {
                    $flushList();
                    $output[] = '<ul class="doc-list">';
                    $inList = true;
                    $listType = 'ul';
                }
                $output[] = "<li>{$item}</li>";
                continue;
            }

            // Ordered List (1. )
            if (preg_match('/^\d+\.\s+(.*)$/', $trimmed, $m)) {
                $flushTable();
                $item = render_inline_markdown($m[1]);
                if (!$inList || $listType !== 'ol') {
                    $flushList();
                    $output[] = '<ol class="doc-list">';
                    $inList = true;
                    $listType = 'ol';
                }
                $output[] = "<li>{$item}</li>";
                continue;
            }

            // Blank lines
            if ($trimmed === '') {
                $flushList();
                $flushTable();
                $flushBlockquote();
                continue;
            }

            // Standard text paragraph
            $flushList();
            $flushTable();
            $flushBlockquote();
            $output[] = '<p class="doc-para">' . render_inline_markdown($trimmed) . '</p>';
        }

        $flushList();
        $flushTable();
        $flushBlockquote();

        $html = implode("\n", $output);

        // 3. Inject code blocks with syntax styling & copy utility
        foreach ($codeBlocks as $placeholder => $block) {
            $copyBtn = '<button type="button" class="btn-copy-code" onclick="copyCodeBlock(this)">Copy</button>';
            $blockHtml = "<div class=\"doc-code-card\"><div class=\"doc-code-header\"><span class=\"doc-code-lang\">{$block['lang']}</span>{$copyBtn}</div><pre><code class=\"language-{$block['lang']}\">{$block['code']}</code></pre></div>";
            $html = str_replace($placeholder, $blockHtml, $html);
        }

        return $html;
    }
}

/**
 * Inline Markdown Formatting (Escaping, Code, Bold, Italics, Links)
 */
if (!function_exists('render_inline_markdown')) {
    function render_inline_markdown(string $text): string {
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        // Inline code `code`
        $text = preg_replace('/`([^`]+)`/', '<code class="doc-inline-code">$1</code>', $text);

        // Bold **text** and __text__
        $text = preg_replace('/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $text);
        $text = preg_replace('/__(.*?)__/s', '<strong>$1</strong>', $text);

        // Italic *text* and _text_
        $text = preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/s', '<em>$1</em>', $text);
        $text = preg_replace('/(?<!_)_([^_]+)_(?!_)/s', '<em>$1</em>', $text);

        // Strikethrough ~~text~~
        $text = preg_replace('/~~(.*?)~~/s', '<del>$1</del>', $text);

        // Links [label](url)
        $text = preg_replace_callback('/\[(.*?)\]\((.*?)\)/', function($matches) {
            $label = $matches[1];
            $url = trim($matches[2]);
            
            // Internal .md file routing
            if (preg_match('/([a-zA-Z0-9_\-]+)\.md$/i', $url, $m)) {
                return '<a href="?page=docs&file=' . urlencode($m[1] . '.md') . '" class="doc-link" title="Open documentation">' . $label . ' ↗</a>';
            }
            
            return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" class="doc-link" target="_blank" rel="noopener">' . $label . '</a>';
        }, $text);

        return $text;
    }
}

// Root project directory resolution
$baseDir = realpath(__DIR__ . '/../../../');

// Categories of documentation
$docSections = [
    'Marketing Hub' => [
        'icon' => '📢',
        'dir' => realpath(__DIR__ . '/../../docs'),
        'extras' => [
            realpath(__DIR__ . '/../../README.md')
        ]
    ],
    'System & AI Directives' => [
        'icon' => '🤖',
        'dir' => realpath($baseDir . '/DOCS'),
        'extras' => [
            realpath($baseDir . '/README.md')
        ]
    ],
    'Orders & CRM' => [
        'icon' => '📊',
        'dir' => realpath($baseDir . '/orders/DOCS'),
        'extras' => [
            realpath($baseDir . '/orders/README.md')
        ]
    ],
    'Labels & Hardware' => [
        'icon' => '🏷️',
        'dir' => realpath($baseDir . '/labels/DOCS'),
        'extras' => [
            realpath($baseDir . '/labels/README.md')
        ]
    ],
    'Tech Control Center' => [
        'icon' => '🛠️',
        'dir' => realpath($baseDir . '/tech/DOCS'),
        'extras' => [
            realpath($baseDir . '/tech/README.md')
        ]
    ]
];

// Build aggregated catalog
$docsCatalog = [];

foreach ($docSections as $sectionName => $secData) {
    $files = [];
    if (!empty($secData['dir']) && is_dir($secData['dir'])) {
        $globbed = glob($secData['dir'] . '/*.md');
        if ($globbed) {
            $files = array_merge($files, $globbed);
        }
    }
    if (!empty($secData['extras'])) {
        foreach ($secData['extras'] as $ex) {
            if ($ex && file_exists($ex)) {
                $files[] = $ex;
            }
        }
    }

    foreach ($files as $f) {
        $basename = basename($f);
        $uniqueKey = md5($f);
        
        // Clean title generation
        $cleanTitle = str_replace(['_', '.md'], [' ', ''], $basename);
        if (strtoupper($cleanTitle) === $cleanTitle) {
            $title = ucwords(strtolower($cleanTitle));
        } else {
            $title = ucwords(preg_replace('/(?<!\ )[A-Z]/', ' $0', $cleanTitle));
        }

        // Custom friendly overrides
        if ($basename === 'README.md') {
            $title = $sectionName . ' Overview (README)';
        } elseif ($basename === 'AI_AGENT_INSTRUCTIONS.md') {
            $title = 'AI Agent Directives';
        } elseif ($basename === 'AI_TECHNICAL_DEEP_DIVE.md') {
            $title = 'AI Technical Deep Dive';
        } elseif ($basename === 'GLOBAL_SITEMAP.md') {
            $title = 'Global System Sitemap';
        } elseif ($basename === 'CODE_REVIEW_CHECKLIST.md') {
            $title = 'Code Review Checklist';
        } elseif ($basename === 'DEVELOPMENT_GUIDELINES.md') {
            $title = 'Development Guidelines';
        } elseif ($basename === 'ARCHITECTURE.md') {
            $title = 'System Architecture Blueprint';
        }

        $docsCatalog[] = [
            'key' => $uniqueKey,
            'section' => $sectionName,
            'icon' => $secData['icon'],
            'path' => $f,
            'basename' => $basename,
            'title' => $title,
            'size' => filesize($f)
        ];
    }
}

// Select active document
$selectedParam = $_GET['file'] ?? null;
$activeDoc = null;

if ($selectedParam) {
    // Match by key or basename
    foreach ($docsCatalog as $item) {
        if ($item['basename'] === $selectedParam || $item['key'] === $selectedParam) {
            $activeDoc = $item;
            break;
        }
    }
}

// Default to DEVELOPMENT_GUIDELINES.md or ARCHITECTURE.md if none selected
if (!$activeDoc && !empty($docsCatalog)) {
    foreach ($docsCatalog as $item) {
        if ($item['basename'] === 'DEVELOPMENT_GUIDELINES.md') {
            $activeDoc = $item;
            break;
        }
    }
    if (!$activeDoc) {
        $activeDoc = $docsCatalog[0];
    }
}

$rawMarkdown = '';
$renderedHtml = '';
$readingTime = 1;
$wordCount = 0;

if ($activeDoc && file_exists($activeDoc['path'])) {
    $rawMarkdown = file_get_contents($activeDoc['path']);
    $wordCount = str_word_count(strip_tags($rawMarkdown));
    $readingTime = max(1, ceil($wordCount / 200));
    $renderedHtml = render_markdown_doc($rawMarkdown);
}
?>

<!-- PAGE HEADER (Warehouse Standard) -->
<header class="page-header">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1>Knowledge Base &amp; Specifications</h1>
            <p>Standard operating procedures, feature specifications, and architectural blueprints.</p>
        </div>
        <?php if ($activeDoc): ?>
            <div style="display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap;">
                <span class="badge" style="background: var(--accent-tertiary); color: var(--accent-primary); border: 1px solid var(--accent-secondary); padding: 8px 14px; border-radius: 8px; font-weight: 700; font-size: 0.85rem;">📖 <?= $readingTime; ?> min read</span>
                <span class="badge" style="background: #f1f5f9; color: var(--text-secondary); border: 1px solid var(--border-color); padding: 8px 14px; border-radius: 8px; font-weight: 600; font-size: 0.85rem;">📝 <?= number_format($wordCount); ?> words</span>
                <button type="button" class="btn btn-secondary" onclick="copyRawMarkdown()" style="height: 38px; padding: 0 14px; font-size: 0.85rem; font-weight: 700; cursor: pointer; border-radius: var(--border-radius-md); border: 1px solid var(--border-color); background: var(--bg-panel); color: var(--text-main); display: inline-flex; align-items: center; gap: 6px;">📋 Copy Raw</button>
                <button type="button" class="btn btn-secondary" onclick="window.print()" style="height: 38px; padding: 0 14px; font-size: 0.85rem; font-weight: 700; cursor: pointer; border-radius: var(--border-radius-md); border: 1px solid var(--border-color); background: var(--bg-panel); color: var(--text-main); display: inline-flex; align-items: center; gap: 6px;">🖨️ Print</button>
            </div>
        <?php endif; ?>
    </div>
</header>

<!-- MAIN TWO-COLUMN WORKSPACE -->
<div class="docs-workspace">
    <!-- SIDEBAR: CATALOG & FILTER -->
    <aside class="card docs-sidebar">
        <div class="docs-search-box">
            <input type="text" id="docFilterInput" placeholder="🔍 Filter guides & specs..." onkeyup="filterDocsCatalog()" autocomplete="off">
        </div>

        <div class="docs-sections" id="docsSectionsContainer">
            <?php
            // Group catalog by section
            $grouped = [];
            foreach ($docsCatalog as $item) {
                $grouped[$item['section']][] = $item;
            }
            foreach ($grouped as $secName => $items):
                $secIcon = $items[0]['icon'] ?? '📁';
            ?>
                <div class="docs-group" data-section="<?= h(strtolower($secName)); ?>">
                    <div class="docs-group-header">
                        <span class="group-icon"><?= $secIcon; ?></span>
                        <span class="group-title"><?= h($secName); ?></span>
                        <span class="group-count"><?= count($items); ?></span>
                    </div>
                    <nav class="docs-nav-items">
                        <?php foreach ($items as $doc):
                            $isActive = ($activeDoc && $activeDoc['key'] === $doc['key']);
                        ?>
                            <a href="?page=docs&file=<?= urlencode($doc['basename']); ?>" 
                               class="docs-nav-link <?= $isActive ? 'active' : ''; ?>"
                               data-doc-title="<?= h(strtolower($doc['title'] . ' ' . $doc['basename'])); ?>">
                                <span class="nav-link-title"><?= h($doc['title']); ?></span>
                                <span class="nav-link-file"><?= h($doc['basename']); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                </div>
            <?php endforeach; ?>
        </div>
    </aside>

    <!-- MAIN READING CANVAS -->
    <section class="card docs-canvas">
        <?php if ($activeDoc): ?>
            <article class="doc-article">
                <header class="doc-header">
                    <div class="doc-header-tag"><?= h($activeDoc['icon'] . ' ' . $activeDoc['section']); ?></div>
                    <h1 class="doc-title"><?= h($activeDoc['title']); ?></h1>
                    <div class="doc-file-path">
                        <code><?= h($activeDoc['basename']); ?></code>
                    </div>
                </header>

                <div class="doc-content-body">
                    <?= $renderedHtml; ?>
                </div>
            </article>

            <!-- HIDDEN TEXTAREA FOR COPY RAW -->
            <textarea id="rawMarkdownContent" style="display: none;"><?= h($rawMarkdown); ?></textarea>
        <?php else: ?>
            <div class="doc-empty-state">
                <div class="empty-icon">📖</div>
                <h2>No Document Selected</h2>
                <p>Select a specification or standard operating procedure from the sidebar.</p>
            </div>
        <?php endif; ?>
    </section>
</div>

<script>
// Client-side quick filter for the documentation catalog
function filterDocsCatalog() {
    const input = document.getElementById('docFilterInput').value.toLowerCase().trim();
    const groups = document.querySelectorAll('.docs-group');

    groups.forEach(group => {
        const links = group.querySelectorAll('.docs-nav-link');
        let hasVisible = false;

        links.forEach(link => {
            const dataTitle = link.getAttribute('data-doc-title') || '';
            if (dataTitle.includes(input)) {
                link.style.display = 'flex';
                hasVisible = true;
            } else {
                link.style.display = 'none';
            }
        });

        group.style.display = hasVisible ? 'block' : 'none';
    });
}

// Copy Code Block functionality
function copyCodeBlock(button) {
    const codeElem = button.closest('.doc-code-card').querySelector('code');
    if (!codeElem) return;
    navigator.clipboard.writeText(codeElem.innerText).then(() => {
        const orig = button.innerText;
        button.innerText = 'Copied! ✓';
        button.classList.add('is-copied');
        setTimeout(() => {
            button.innerText = orig;
            button.classList.remove('is-copied');
        }, 2000);
    });
}

// Copy raw markdown text
function copyRawMarkdown() {
    const raw = document.getElementById('rawMarkdownContent');
    if (!raw) return;
    navigator.clipboard.writeText(raw.value).then(() => {
        alert('Raw Markdown copied to clipboard!');
    });
}
</script>

<style>
/* -------------------------------------
   DOCUMENTATION STYLING (Warehouse Light Standard)
   Aligned with marketing/assets/css/style.css
   ------------------------------------- */

.docs-workspace {
    display: grid;
    grid-template-columns: 310px 1fr;
    gap: 2rem;
    align-items: start;
}

/* Sidebar Card */
.docs-sidebar {
    grid-column: 1 / 2 !important;
    position: sticky;
    top: 100px;
    padding: 1.5rem !important;
    background: var(--bg-panel, #ffffff) !important;
    border: 1px solid var(--border-color, #e2e8f0) !important;
    border-radius: var(--border-radius-lg, 16px) !important;
    box-shadow: var(--shadow-sm) !important;
    max-height: calc(100vh - 120px);
    overflow-y: auto;
    transform: none !important;
    transition: none !important;
}

.docs-sidebar:hover {
    transform: none !important;
    box-shadow: var(--shadow-sm) !important;
}

.docs-sidebar::-webkit-scrollbar {
    width: 6px;
}
.docs-sidebar::-webkit-scrollbar-track {
    background: transparent;
}
.docs-sidebar::-webkit-scrollbar-thumb {
    background: var(--border-color, #e2e8f0);
    border-radius: 4px;
}

.docs-search-box {
    margin-bottom: 1.25rem;
}

.docs-search-box input {
    width: 100%;
    padding: 10px 14px;
    border-radius: var(--border-radius-md, 10px);
    border: 1px solid var(--border-color, #e2e8f0);
    background: #f8fafc;
    color: var(--text-main, #0f172a);
    font-size: 0.88rem;
    outline: none;
    transition: all 0.2s ease;
}

.docs-search-box input:focus {
    border-color: var(--accent-primary, #007268);
    background: #ffffff;
    box-shadow: 0 0 0 3px rgba(0, 114, 104, 0.12);
}

.docs-sections {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.docs-group-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-weight: 800;
    color: var(--text-secondary, #64748b);
    margin-bottom: 0.5rem;
    padding-bottom: 0.35rem;
    border-bottom: 1px solid var(--border-color, #e2e8f0);
}

.group-count {
    margin-left: auto;
    background: var(--accent-tertiary, #f1ffd765);
    color: var(--accent-primary, #007268);
    border: 1px solid var(--accent-secondary, #7aff6b6e);
    padding: 1px 7px;
    border-radius: 12px;
    font-size: 0.72rem;
    font-weight: 700;
}

.docs-nav-items {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.docs-nav-link {
    display: flex;
    flex-direction: column;
    padding: 10px 12px;
    border-radius: var(--border-radius-md, 10px);
    text-decoration: none;
    border: 1px solid var(--border-color, #e2e8f0);
    background: var(--bg-panel, #ffffff);
    transition: all 0.2s ease-in-out;
}

.nav-link-title {
    color: var(--text-main, #0f172a);
    font-size: 0.88rem;
    font-weight: 600;
}

.nav-link-file {
    color: var(--text-secondary, #64748b);
    font-size: 0.72rem;
    font-family: monospace;
}

.docs-nav-link:hover {
    background: var(--accent-tertiary, #f1ffd765);
    border-color: var(--accent-primary, #007268);
}

.docs-nav-link:hover .nav-link-title {
    color: var(--accent-primary, #007268);
}

.docs-nav-link.active {
    background: var(--accent-primary, #007268) !important;
    border-color: var(--accent-primary, #007268) !important;
}

.docs-nav-link.active .nav-link-title {
    color: #ffffff !important;
}

.docs-nav-link.active .nav-link-file {
    color: rgba(255, 255, 255, 0.85) !important;
}

/* Canvas Card */
.docs-canvas {
    grid-column: 2 / 3 !important;
    background: var(--bg-panel, #ffffff) !important;
    border: 1px solid var(--border-color, #e2e8f0) !important;
    border-radius: var(--border-radius-lg, 16px) !important;
    padding: 3rem !important;
    min-height: 800px;
    box-shadow: var(--shadow-sm) !important;
    transform: none !important;
    transition: none !important;
}

.docs-canvas:hover {
    transform: none !important;
    box-shadow: var(--shadow-sm) !important;
}

.doc-article {
    max-width: 960px;
    margin: 0 auto;
}

.doc-header {
    border-bottom: 2px solid var(--border-color, #e2e8f0);
    padding-bottom: 1.5rem;
    margin-bottom: 2.25rem;
}

.doc-header-tag {
    font-size: 0.8rem;
    text-transform: uppercase;
    font-weight: 800;
    color: var(--accent-primary, #007268);
    letter-spacing: 0.06em;
    margin-bottom: 0.5rem;
}

.doc-title {
    font-size: 2.2rem;
    font-weight: 900;
    color: var(--text-main, #0f172a);
    line-height: 1.25;
    letter-spacing: -0.5px;
    margin: 0 0 0.75rem 0;
}

.doc-file-path code {
    font-size: 0.82rem;
    background: #f1f5f9;
    color: var(--text-secondary, #64748b);
    border: 1px solid var(--border-color, #e2e8f0);
    padding: 3px 8px;
    border-radius: 6px;
    font-family: monospace;
}

/* Typography & Content Body */
.doc-content-body {
    color: var(--text-main, #0f172a);
    font-size: 1rem;
    line-height: 1.75;
}

.doc-heading {
    color: var(--text-main, #0f172a);
    font-weight: 800;
    margin-top: 2.25rem;
    margin-bottom: 1rem;
    line-height: 1.35;
    letter-spacing: -0.3px;
}

h1.doc-heading {
    font-size: 1.85rem;
    border-bottom: 2px solid var(--border-color, #e2e8f0);
    padding-bottom: 0.5rem;
}

h2.doc-heading {
    font-size: 1.4rem;
    border-bottom: 1px solid var(--border-color, #e2e8f0);
    padding-bottom: 0.4rem;
}

h3.doc-heading {
    font-size: 1.15rem;
}

h4.doc-heading {
    font-size: 1rem;
    color: var(--text-dim, #475569);
}

.doc-para {
    color: var(--text-dim, #334155);
    margin-bottom: 1.25rem;
}

.doc-divider {
    border: 0;
    height: 1px;
    background: var(--border-color, #e2e8f0);
    margin: 2.5rem 0;
}

.doc-list {
    margin-bottom: 1.5rem;
    padding-left: 1.75rem;
    color: var(--text-dim, #334155);
}

.doc-list li {
    margin-bottom: 0.45rem;
}

.doc-content-body strong {
    color: var(--text-main, #0f172a);
    font-weight: 700;
}

/* Inline Elements */
.doc-inline-code {
    background: #f1f5f9;
    color: var(--accent-primary, #007268);
    padding: 2px 7px;
    border-radius: 5px;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 0.88em;
    font-weight: 600;
    border: 1px solid #e2e8f0;
}

.doc-link {
    color: var(--accent-primary, #007268);
    text-decoration: none;
    font-weight: 700;
    border-bottom: 1px dashed rgba(0, 114, 104, 0.4);
    transition: all 0.15s;
}

.doc-link:hover {
    color: #004d46;
    border-bottom-style: solid;
    border-bottom-color: var(--accent-primary, #007268);
}

/* Task List Items */
.task-item {
    display: inline-flex;
    align-items: baseline;
    gap: 0.35rem;
}
.task-checked {
    color: var(--text-main, #0f172a);
    font-weight: 600;
}
.task-unchecked {
    color: var(--text-secondary, #64748b);
}

/* Alerts & Callouts */
.doc-alert {
    padding: 1.1rem 1.4rem !important;
    border-radius: var(--border-radius-md, 10px) !important;
    margin: 1.5rem 0 !important;
    border-left: 4px solid !important;
    font-size: 0.95rem !important;
    line-height: 1.6 !important;
    display: block !important;
}

.doc-alert-title {
    font-weight: 800;
    font-size: 0.82rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 0.35rem;
}

.alert-note {
    background: #f0f9ff !important;
    border-left-color: #0284c7 !important;
    border: 1px solid #bae6fd;
    color: #0369a1 !important;
}
.alert-tip {
    background: #f7fee7 !important;
    border-left-color: #65a30d !important;
    border: 1px solid #d9f99d;
    color: #3f6212 !important;
}
.alert-important {
    background: #fffbeb !important;
    border-left-color: #d97706 !important;
    border: 1px solid #fde68a;
    color: #92400e !important;
}
.alert-warning {
    background: #fef2f2 !important;
    border-left-color: #dc2626 !important;
    border: 1px solid #fecaca;
    color: #991b1b !important;
}
.alert-caution {
    background: #fff1f2 !important;
    border-left-color: #e11d48 !important;
    border: 1px solid #fecdd3;
    color: #9f1239 !important;
}

.doc-quote {
    border-left: 4px solid var(--accent-primary, #007268);
    padding: 0.8rem 1.4rem;
    margin: 1.5rem 0;
    background: var(--accent-tertiary, #f1ffd765);
    border-radius: 0 var(--border-radius-md, 10px) var(--border-radius-md, 10px) 0;
    font-style: italic;
    color: var(--text-main, #0f172a);
}

/* Code Cards (Dark Slate Editor on Crisp White Card) */
.doc-code-card {
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: var(--border-radius-md, 10px);
    margin: 1.75rem 0;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.12);
}

.doc-code-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #1e293b;
    padding: 8px 16px;
    border-bottom: 1px solid #334155;
}

.doc-code-lang {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-weight: 800;
    color: #94a3b8;
    font-family: monospace;
}

.btn-copy-code {
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid #475569;
    color: #cbd5e1;
    font-size: 0.75rem;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 5px;
    cursor: pointer;
    transition: all 0.15s;
}

.btn-copy-code:hover {
    background: #334155;
    color: #ffffff;
}

.btn-copy-code.is-copied {
    background: var(--accent-primary, #007268);
    border-color: var(--accent-primary, #007268);
    color: #ffffff;
}

.doc-code-card pre {
    margin: 0;
    padding: 1.25rem 1.5rem;
    overflow-x: auto;
}

.doc-code-card code {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 0.88rem;
    line-height: 1.65;
    color: #f8fafc;
}

/* Tables (Warehouse Standard) */
.doc-table-wrap {
    margin: 1.75rem 0;
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: var(--border-radius-md, 10px);
    overflow-x: auto;
    background: var(--bg-panel, #ffffff);
}

.doc-table-wrap table {
    width: 100%;
    border-collapse: collapse;
}

.doc-table-wrap th {
    background: #f8fafc !important;
    padding: 12px 16px !important;
    font-size: 0.78rem !important;
    font-weight: 800 !important;
    text-transform: uppercase !important;
    color: var(--text-secondary, #64748b) !important;
    border-bottom: 2px solid var(--border-color, #e2e8f0) !important;
    letter-spacing: 0.05em !important;
}

.doc-table-wrap td {
    padding: 12px 16px !important;
    border-bottom: 1px solid var(--border-color, #e2e8f0) !important;
    color: var(--text-main, #0f172a) !important;
    font-size: 0.95rem !important;
}

.doc-table-wrap tr:last-child td {
    border-bottom: none !important;
}

.doc-table-wrap tr:hover td {
    background: var(--accent-tertiary, #f1ffd765) !important;
}

/* Empty State */
.doc-empty-state {
    text-align: center;
    padding: 8rem 2rem;
    color: var(--text-secondary, #64748b);
}

.empty-icon {
    font-size: 4rem;
    margin-bottom: 1.25rem;
}

/* Mobile Responsiveness */
@media (max-width: 960px) {
    .docs-workspace {
        grid-template-columns: 1fr;
    }
    .docs-sidebar {
        position: static;
        max-height: none;
    }
    .docs-canvas {
        padding: 1.5rem !important;
    }
}
</style>
