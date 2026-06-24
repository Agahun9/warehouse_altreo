(function () {
  'use strict';

  function button(label, title, command, value) {
    var element = document.createElement('button');
    element.type = 'button';
    element.className = 'free-rte-button';
    element.innerHTML = label;
    element.title = title;
    element.setAttribute('aria-label', title);
    element.dataset.command = command || '';
    if (value !== undefined) {
      element.dataset.value = value;
    }
    return element;
  }

  function group(items) {
    var wrapper = document.createElement('span');
    wrapper.className = 'free-rte-toolbar-group';
    items.forEach(function (item) {
      wrapper.appendChild(item);
    });
    return wrapper;
  }

  function restoreSelection(editor, range) {
    editor.focus();
    if (!range) return;
    var selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(range);
  }

  function selectionInside(editor) {
    var selection = window.getSelection();
    if (!selection.rangeCount) return null;
    var range = selection.getRangeAt(0);
    return editor.contains(range.commonAncestorContainer) ? range.cloneRange() : null;
  }

  function init(textarea) {
    if (textarea.dataset.freeRteReady === '1') return;
    textarea.dataset.freeRteReady = '1';
    textarea.classList.add('free-rte-original');

    var wrapper = document.createElement('div');
    wrapper.className = 'free-rte';
    var toolbar = document.createElement('div');
    toolbar.className = 'free-rte-toolbar';
    toolbar.setAttribute('role', 'toolbar');
    toolbar.setAttribute('aria-label', 'Formatowanie tekstu');

    var format = document.createElement('select');
    format.className = 'free-rte-select';
    format.title = 'Format akapitu';
    [
      ['', 'Akapit'],
      ['h2', 'Nagłówek 2'],
      ['h3', 'Nagłówek 3'],
      ['h4', 'Nagłówek 4'],
      ['blockquote', 'Cytat'],
      ['pre', 'Kod']
    ].forEach(function (option) {
      var node = document.createElement('option');
      node.value = option[0];
      node.textContent = option[1];
      format.appendChild(node);
    });

    toolbar.appendChild(group([
      button('↶', 'Cofnij', 'undo'),
      button('↷', 'Ponów', 'redo')
    ]));
    toolbar.appendChild(group([format]));
    toolbar.appendChild(group([
      button('<b>B</b>', 'Pogrubienie', 'bold'),
      button('<i>I</i>', 'Kursywa', 'italic'),
      button('<u>U</u>', 'Podkreślenie', 'underline'),
      button('<s>S</s>', 'Przekreślenie', 'strikeThrough')
    ]));
    toolbar.appendChild(group([
      button('• Lista', 'Lista punktowana', 'insertUnorderedList'),
      button('1. Lista', 'Lista numerowana', 'insertOrderedList'),
      button('←', 'Zmniejsz wcięcie', 'outdent'),
      button('→', 'Zwiększ wcięcie', 'indent')
    ]));
    toolbar.appendChild(group([
      button('≡←', 'Wyrównaj do lewej', 'justifyLeft'),
      button('≡', 'Wyśrodkuj', 'justifyCenter'),
      button('→≡', 'Wyrównaj do prawej', 'justifyRight')
    ]));
    toolbar.appendChild(group([
      button('🔗', 'Wstaw link', 'createLink'),
      button('🖼', 'Wstaw obraz z adresu URL', 'insertImage'),
      button('×🔗', 'Usuń link', 'unlink'),
      button('Tx', 'Usuń formatowanie', 'removeFormat'),
      button('&lt;/&gt;', 'Edytuj kod HTML', 'source')
    ]));

    var editor = document.createElement('div');
    editor.className = 'free-rte-editor';
    editor.contentEditable = 'true';
    editor.setAttribute('role', 'textbox');
    editor.setAttribute('aria-multiline', 'true');
    editor.dataset.placeholder = textarea.getAttribute('placeholder') || 'Wpisz treść…';
    editor.innerHTML = textarea.value;
    var height = parseInt(textarea.dataset.editorHeight || '350', 10);
    editor.style.minHeight = Math.max(180, height) + 'px';

    var source = document.createElement('textarea');
    source.className = 'free-rte-source';
    source.spellcheck = false;
    source.style.minHeight = editor.style.minHeight;

    wrapper.appendChild(toolbar);
    wrapper.appendChild(editor);
    wrapper.appendChild(source);
    textarea.insertAdjacentElement('afterend', wrapper);

    var savedRange = null;
    editor.addEventListener('keyup', function () {
      savedRange = selectionInside(editor);
      textarea.value = editor.innerHTML;
    });
    editor.addEventListener('mouseup', function () {
      savedRange = selectionInside(editor);
    });
    editor.addEventListener('input', function () {
      textarea.value = editor.innerHTML;
    });

    format.addEventListener('change', function () {
      restoreSelection(editor, savedRange);
      document.execCommand('formatBlock', false, format.value || 'p');
      textarea.value = editor.innerHTML;
      format.value = '';
    });

    toolbar.addEventListener('mousedown', function (event) {
      if (event.target.closest('button')) {
        event.preventDefault();
      }
    });
    toolbar.addEventListener('click', function (event) {
      var control = event.target.closest('button[data-command]');
      if (!control) return;
      var command = control.dataset.command;

      if (command === 'source') {
        var sourceMode = wrapper.classList.toggle('is-source');
        if (sourceMode) {
          source.value = editor.innerHTML;
          source.focus();
          control.classList.add('is-active');
        } else {
          editor.innerHTML = source.value;
          textarea.value = source.value;
          editor.focus();
          control.classList.remove('is-active');
        }
        return;
      }

      restoreSelection(editor, savedRange);
      if (command === 'createLink') {
        var link = window.prompt('Adres linku (https://…):', 'https://');
        if (!link) return;
        document.execCommand('createLink', false, link);
      } else if (command === 'insertImage') {
        var image = window.prompt('Adres obrazu (https://…):', 'https://');
        if (!image) return;
        document.execCommand('insertImage', false, image);
      } else {
        document.execCommand(command, false, control.dataset.value || null);
      }
      textarea.value = editor.innerHTML;
      savedRange = selectionInside(editor);
    });

    source.addEventListener('input', function () {
      textarea.value = source.value;
    });

    var form = textarea.closest('form');
    if (form) {
      form.addEventListener('submit', function () {
        if (wrapper.classList.contains('is-source')) {
          editor.innerHTML = source.value;
        }
        textarea.value = editor.innerHTML;
      });
    }
  }

  function initAll(root) {
    (root || document).querySelectorAll('textarea.free-rich-text-editor').forEach(init);
  }

  window.FreeRichTextEditor = { initAll: initAll };
  document.addEventListener('DOMContentLoaded', function () {
    initAll(document);
  });
}());

