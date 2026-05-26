/**
 * FileDropzone — drag-and-drop multi-file upload widget.
 * Automatically initialises on every <input type="file" multiple>.
 */
(function () {
  const ICONS = {
    'application/pdf': 'bi-file-earmark-pdf text-danger',
    'image/jpeg': 'bi-file-earmark-image text-primary',
    'image/png': 'bi-file-earmark-image text-primary',
    'image/tiff': 'bi-file-earmark-image text-primary',
    'image/webp': 'bi-file-earmark-image text-primary',
    'image/vnd.djvu': 'bi-file-earmark-text text-secondary',
    'image/x.djvu': 'bi-file-earmark-text text-secondary',
    'application/epub+zip': 'bi-book text-success',
    'application/zip': 'bi-file-earmark-zip text-warning',
    'text/plain': 'bi-file-earmark-text text-secondary',
  };

  function iconClass(file) {
    return ICONS[file.type] || 'bi-file-earmark text-secondary';
  }

  function fmtSize(bytes) {
    if (bytes === 0) return '0 Б';
    const units = ['Б', 'КБ', 'МБ', 'ГБ'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return (bytes / Math.pow(1024, i)).toFixed(i ? 1 : 0) + ' ' + units[i];
  }

  function init(input) {
    const files = [];
    const helpText = input.closest('.mb-3, .form-group')?.querySelector('.form-text')?.textContent ?? '';

    // --- Build DOM ---
    const wrapper = document.createElement('div');
    wrapper.className = 'file-dropzone';

    const zone = document.createElement('div');
    zone.className = 'file-dropzone__zone border border-2 border-dashed rounded-3 p-4 text-center text-muted bg-light';
    zone.style.cursor = 'pointer';
    zone.innerHTML = `
      <i class="bi bi-cloud-upload fs-2 d-block mb-1"></i>
      <span class="fw-semibold">Перетащите файлы сюда</span>
      <span class="d-block small">или <span class="text-primary text-decoration-underline">нажмите для выбора</span></span>
      ${helpText ? `<span class="d-block small text-muted mt-1">${helpText}</span>` : ''}
    `;

    const list = document.createElement('div');
    list.className = 'file-dropzone__list mt-2';

    // Hide original input, place it inside the widget
    input.style.display = 'none';
    input.parentNode.insertBefore(wrapper, input);
    wrapper.appendChild(zone);
    wrapper.appendChild(list);
    wrapper.appendChild(input);

    // --- CSS (injected once) ---
    if (!document.getElementById('file-dropzone-css')) {
      const style = document.createElement('style');
      style.id = 'file-dropzone-css';
      style.textContent = `
        .file-dropzone__zone { transition: background .15s, border-color .15s; border-color: #dee2e6 !important; }
        .file-dropzone__zone:hover,
        .file-dropzone__zone.drag-over { background: #e9f0ff !important; border-color: #0d6efd !important; }
        .file-dropzone__item { display: flex; align-items: center; gap: 8px;
          padding: 6px 10px; border-radius: 6px; background: #f8f9fa;
          border: 1px solid #e9ecef; font-size: .875rem; }
        .file-dropzone__item + .file-dropzone__item { margin-top: 4px; }
        .file-dropzone__name { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .file-dropzone__size { color: #6c757d; white-space: nowrap; }
        .file-dropzone__remove { border: none; background: none; color: #6c757d; line-height: 1;
          padding: 0 2px; cursor: pointer; font-size: 1rem; flex-shrink: 0; }
        .file-dropzone__remove:hover { color: #dc3545; }
        .file-dropzone__counter { font-size: .8rem; color: #6c757d; margin-top: 6px; }
      `;
      document.head.appendChild(style);
    }

    // --- Sync files[] → input.files via DataTransfer ---
    function syncInput() {
      const dt = new DataTransfer();
      files.forEach(f => dt.items.add(f));
      input.files = dt.files;
    }

    // --- Render file list ---
    function renderList() {
      list.innerHTML = '';
      files.forEach((f, idx) => {
        const item = document.createElement('div');
        item.className = 'file-dropzone__item';
        item.innerHTML = `
          <i class="bi ${iconClass(f)} flex-shrink-0"></i>
          <span class="file-dropzone__name" title="${f.name}">${f.name}</span>
          <span class="file-dropzone__size">${fmtSize(f.size)}</span>
          <button type="button" class="file-dropzone__remove" title="Убрать" data-idx="${idx}">
            <i class="bi bi-x-lg"></i>
          </button>
        `;
        list.appendChild(item);
      });
      if (files.length > 1) {
        const counter = document.createElement('div');
        counter.className = 'file-dropzone__counter';
        counter.textContent = `Итого: ${files.length} файл${files.length < 5 ? (files.length === 1 ? '' : 'а') : 'ов'}, ${fmtSize(files.reduce((s, f) => s + f.size, 0))}`;
        list.appendChild(counter);
      }
    }

    // --- Add files (deduplicate by name+size) ---
    function addFiles(fileList) {
      let added = 0;
      for (const f of fileList) {
        if (!files.find(x => x.name === f.name && x.size === f.size)) {
          files.push(f);
          added++;
        }
      }
      if (added) { syncInput(); renderList(); }
    }

    // --- Events: zone click ---
    zone.addEventListener('click', () => input.click());

    // --- Events: input change ---
    input.addEventListener('change', () => {
      if (input.files.length) addFiles(input.files);
      // Reset so the same files can be selected again
      input.value = '';
    });

    // --- Events: drag-and-drop ---
    zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
    zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
    zone.addEventListener('drop', e => {
      e.preventDefault();
      zone.classList.remove('drag-over');
      if (e.dataTransfer.files.length) addFiles(e.dataTransfer.files);
    });

    // --- Events: remove button ---
    list.addEventListener('click', e => {
      const btn = e.target.closest('[data-idx]');
      if (!btn) return;
      files.splice(Number(btn.dataset.idx), 1);
      syncInput();
      renderList();
    });
  }

  // --- Auto-init ---
  function initAll() {
    document.querySelectorAll('input[type="file"][multiple]').forEach(input => {
      if (!input.closest('.file-dropzone')) init(input);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }
})();
