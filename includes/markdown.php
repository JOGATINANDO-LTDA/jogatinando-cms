<?php
function parseMarkdown(string $text): string {
    if ($text === '') return '';

    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8');

    $lines = explode("\n", $text);
    $html = [];
    $inCodeBlock = false;
    $codeLang = '';
    $codeBuffer = [];
    $inList = false;
    $listType = '';
    $listBuffer = [];

    foreach ($lines as $line) {
        if (preg_match('/^```(\w*)$/', $line, $m)) {
            if ($inCodeBlock) {
                $codeContent = implode("\n", $codeBuffer);
                if ($codeLang === 'mermaid') {
                    $html[] = '<div class="mermaid-block"><pre class="mermaid">' . $codeContent . '</pre></div>';
                } else {
                    $html[] = '<pre><code' . ($codeLang ? ' class="lang-' . $codeLang . '"' : '') . '>' . $codeContent . '</code></pre>';
                }
                $inCodeBlock = false;
                $codeBuffer = [];
                $codeLang = '';
            } else {
                if ($inList) { $html[] = _mdCloseList($listType, $listBuffer); $inList = false; $listBuffer = []; }
                $inCodeBlock = true;
                $codeLang = $m[1];
            }
            continue;
        }

        if ($inCodeBlock) {
            $codeBuffer[] = $line;
            continue;
        }

        if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $m)) {
            if ($inList) { $html[] = _mdCloseList($listType, $listBuffer); $inList = false; $listBuffer = []; }
            $level = strlen($m[1]);
            $html[] = '<h' . $level . '>' . _mdInline($m[2]) . '</h' . $level . '>';
            continue;
        }

        if (preg_match('/^[-*]\s+(.+)$/', $line, $m)) {
            if (!$inList || $listType !== 'ul') {
                if ($inList) { $html[] = _mdCloseList($listType, $listBuffer); $listBuffer = []; }
                $inList = true;
                $listType = 'ul';
            }
            $listBuffer[] = _mdInline($m[1]);
            continue;
        }

        if (preg_match('/^\d+\.\s+(.+)$/', $line, $m)) {
            if (!$inList || $listType !== 'ol') {
                if ($inList) { $html[] = _mdCloseList($listType, $listBuffer); $listBuffer = []; }
                $inList = true;
                $listType = 'ol';
            }
            $listBuffer[] = _mdInline($m[1]);
            continue;
        }

        if ($inList && trim($line) === '') {
            $html[] = _mdCloseList($listType, $listBuffer);
            $inList = false;
            $listBuffer = [];
            continue;
        }

        if ($inList) {
            $listBuffer[] = _mdInline($line);
            continue;
        }

        if (preg_match('/^---$/', trim($line))) {
            $html[] = '<hr>';
            continue;
        }

        if (trim($line) === '') {
            continue;
        }

        $html[] = '<p>' . _mdInline($line) . '</p>';
    }

    if ($inCodeBlock) {
        $codeContent = implode("\n", $codeBuffer);
        if ($codeLang === 'mermaid') {
            $html[] = '<div class="mermaid-block"><pre class="mermaid">' . $codeContent . '</pre></div>';
        } else {
            $html[] = '<pre><code' . ($codeLang ? ' class="lang-' . $codeLang . '"' : '') . '>' . $codeContent . '</code></pre>';
        }
    }

    if ($inList) {
        $html[] = _mdCloseList($listType, $listBuffer);
    }

    return implode("\n", $html);
}

function _mdInline(string $text): string {
    $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);
    $text = preg_replace('/__(.+?)__/s', '<strong>$1</strong>', $text);
    $text = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/s', '<em>$1</em>', $text);
    $text = preg_replace('/(?<!_)_(?!_)(.+?)(?<!_)_(?!_)/s', '<em>$1</em>', $text);
    $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
    $text = preg_replace('/!\[([^\]]*)\]\(([^)]+)\)/', '<img src="$2" alt="$1" style="max-width:100%">', $text);
    $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank" rel="noopener">$1</a>', $text);
    return $text;
}

function _mdCloseList(string $type, array $items): string {
    $tag = $type;
    $inner = implode('', array_map(function($item) { return '<li>' . $item . '</li>'; }, $items));
    return '<' . $tag . '>' . $inner . '</' . $tag . '>';
}
