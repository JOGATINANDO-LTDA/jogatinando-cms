function initMarkdownEditor(textareaId, options) {
    var ta = document.getElementById(textareaId);
    if (!ta) return;
    var opts = options || {};
    var imageUploadUrl = opts.imageUploadUrl || '';

    var toolbar = document.createElement('div');
    toolbar.className = 'md-toolbar';
    toolbar.innerHTML = [
        mdBtn('H2', 'heading-2', function() { wrapLines(ta, '## '); }),
        mdBtn('H3', 'heading-3', function() { wrapLines(ta, '### '); }),
        mdBtn('B', 'bold', function() { wrapSelection(ta, '**', '**'); }),
        mdBtn('I', 'italic', function() { wrapSelection(ta, '_', '_'); }),
        mdBtn('Link', 'link', function() {
            var url = prompt('URL:', 'https://');
            if (url) wrapSelection(ta, '[', '](' + url + ')');
        }),
        mdBtn('Img', 'image', function() {
            if (imageUploadUrl) {
                openImageUpload(ta, imageUploadUrl);
            } else {
                var url = prompt('URL da imagem:', 'https://');
                if (url) insertAtCursor(ta, '![' + (prompt('Texto alternativo:', '') || 'imagem') + '](' + url + ')');
            }
        }),
        mdBtn('UL', 'list-ul', function() { wrapLines(ta, '- ', ''); }),
        mdBtn('OL', 'list-ol', function() { wrapLines(ta, '1. ', ''); }),
        mdBtn('Code', 'code', function() { wrapSelection(ta, '`', '`'); }),
        mdBtn('Block', 'block-code', function() { wrapSelection(ta, '\n```\n', '\n```\n'); }),
        mdBtn('Mermaid', 'mermaid', function() { insertAtCursor(ta, '\n```mermaid\ngraph TD\n    A[Início] --> B[Fim]\n```\n'); })
    ].join('');

    ta.parentNode.insertBefore(toolbar, ta);

    ta.addEventListener('keydown', function(e) {
        if (e.ctrlKey || e.metaKey) {
            if (e.key === 'b') { e.preventDefault(); wrapSelection(ta, '**', '**'); }
            if (e.key === 'i') { e.preventDefault(); wrapSelection(ta, '_', '_'); }
            if (e.key === 'k') {
                e.preventDefault();
                var url = prompt('URL:', 'https://');
                if (url) wrapSelection(ta, '[', '](' + url + ')');
            }
        }
    });
}

function mdBtn(label, icon, fn) {
    return '<button type="button" class="md-btn" data-action="' + icon + '" title="' + label + '">' + label + '</button>';
}

function wrapSelection(ta, before, after) {
    var start = ta.selectionStart;
    var end = ta.selectionEnd;
    var sel = ta.value.substring(start, end) || 'texto';
    ta.setRangeText(before + sel + after, start, end, 'select');
    ta.focus();
}

function wrapLines(ta, prefix) {
    var start = ta.selectionStart;
    var end = ta.selectionEnd;
    var sel = ta.value.substring(start, end);
    if (!sel) { sel = 'item'; }
    var lines = sel.split('\n').map(function(l) { return prefix + l; });
    ta.setRangeText(lines.join('\n'), start, end, 'end');
    ta.focus();
}

function insertAtCursor(ta, text) {
    var pos = ta.selectionStart;
    ta.setRangeText(text, pos, pos, 'end');
    ta.focus();
}

function openImageUpload(ta, uploadUrl) {
    var input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/png,image/jpeg,image/gif,image/webp';
    input.onchange = function() {
        var file = input.files[0];
        if (!file) return;
        var formData = new FormData();
        formData.append('image', file);
        var xhr = new XMLHttpRequest();
        xhr.open('POST', uploadUrl, true);
        xhr.onload = function() {
            if (xhr.status === 200) {
                var resp = JSON.parse(xhr.responseText);
                if (resp.url) {
                    var alt = file.name.replace(/\.[^.]+$/, '');
                    insertAtCursor(ta, '![' + alt + '](' + resp.url + ')');
                }
            }
        };
        xhr.send(formData);
    };
    input.click();
}
