/* WebEdge code editor (CodeMirror 5). */
(function () {
    'use strict';
    var ta = document.getElementById('codeEditor');
    if (!ta || !window.CodeMirror) return;
    var form = document.getElementById('editorForm');
    var dirtyBadge = document.querySelector('[data-dirty]');
    var editor = CodeMirror.fromTextArea(ta, {
        mode: ta.getAttribute('data-mode') || 'text/plain',
        lineNumbers: true,
        lineWrapping: false,
        indentUnit: 4,
        tabSize: 4,
        indentWithTabs: false,
        matchBrackets: true,
        autoCloseBrackets: true,
        autoCloseTags: true,
        matchTags: { bothTags: true },
        extraKeys: {
            'Ctrl-S': function () { editor.save(); dirty = false; form.submit(); },
            'Cmd-S': function () { editor.save(); dirty = false; form.submit(); },
            'Alt-G': 'jumpToLine',
            Tab: function (cm) {
                if (cm.somethingSelected()) cm.indentSelection('add');
                else cm.replaceSelection(Array(cm.getOption('indentUnit') + 1).join(' '), 'end');
            }
        }
    });
    editor.setSize(null, Math.max(400, window.innerHeight - 220));
    var dirty = false;
    editor.on('change', function () {
        dirty = true;
        if (dirtyBadge) dirtyBadge.hidden = false;
    });
    form.addEventListener('submit', function () { editor.save(); dirty = false; });
    window.addEventListener('beforeunload', function (e) {
        if (dirty) { e.preventDefault(); e.returnValue = ''; }
    });
})();
