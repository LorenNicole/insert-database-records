(() => {
  const els = {
    noticeArea: document.getElementById('idr-notice-area'),
    tableSelect: document.getElementById('idr-selected-table'),
    fileInput: document.getElementById('idr-csv-file'),
    validateButton: document.getElementById('idr-validate-button'),
    insertButton: document.getElementById('idr-insert-button'),
    deleteButton: document.getElementById('idr-delete-button'),
    modalBackdrop: document.getElementById('idr-modal-backdrop'),
    modalMessage: document.getElementById('idr-modal-message'),
    modalConfirm: document.getElementById('idr-modal-confirm'),
    modalCancel: document.getElementById('idr-modal-cancel'),
  };

  const state = {
    currentAction: null,
  };

  function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
  }

  function setNotice(message, type = 'success') {
    els.noticeArea.innerHTML =
      `<div class="notice notice-${type} is-dismissible"><p>${escapeHtml(message)}</p></div>`;
  }

  function getSelectedTable() {
    return els.tableSelect.value || '';
  }

  function getSelectedFile() {
    return els.fileInput.files && els.fileInput.files.length
      ? els.fileInput.files[0]
      : null;
  }

  function buildFormData() {
    const formData = new FormData();
    formData.append('selected_table', getSelectedTable());

    const file = getSelectedFile();
    if (file) {
      formData.append('csv_file', file);
    }

    return formData;
  }

  async function apiFetch(path, options = {}) {
    const headers = new Headers(options.headers || {});
    headers.set('X-WP-Nonce', idrAdminSettings.restNonce);

    const response = await fetch(idrAdminSettings.restUrl + path, {
      ...options,
      headers,
      credentials: 'same-origin',
    });

    let payload = {};
    try {
      payload = await response.json();
    } catch (e) {
      payload = {};
    }

    if (!response.ok) {
      const message =
        payload.message ||
        payload?.data?.message ||
        `Request failed with status ${response.status}.`;
      throw new Error(message);
    }

    return payload;
  }

  async function loadTables() {
    els.tableSelect.innerHTML =
      `<option value="">${escapeHtml(idrAdminSettings.strings.loadingTables)}</option>`;

    try {
      const payload = await apiFetch('tables', { method: 'GET' });
      const options = ['<option value="">Select a custom table</option>'];

      (payload.tables || []).forEach((tableName) => {
        options.push(
          `<option value="${escapeHtml(tableName)}">${escapeHtml(tableName)}</option>`
        );
      });

      els.tableSelect.innerHTML = options.join('');
    } catch (error) {
      els.tableSelect.innerHTML = '<option value="">Unable to load tables</option>';
      setNotice(error.message, 'error');
    }
  }

  function validateLocalInputs(requireFile = true) {
    const selectedTable = getSelectedTable();
    const selectedFile = getSelectedFile();

    if (!selectedTable) {
      setNotice(idrAdminSettings.strings.selectTableFirst, 'error');
      return false;
    }

    if (requireFile && !selectedFile) {
      setNotice(idrAdminSettings.strings.selectFileFirst, 'error');
      return false;
    }

    return true;
  }

  async function runValidation() {
    if (!validateLocalInputs(true)) {
      return;
    }

    setNotice(idrAdminSettings.strings.validating, 'info');

    try {
      const payload = await apiFetch('imports/validate', {
        method: 'POST',
        body: buildFormData(),
      });

      const rowCount = payload?.meta?.row_count || 0;
      setNotice(`${payload.message} Row count: ${rowCount}.`, 'success');
    } catch (error) {
      setNotice(error.message, 'error');
      console.error('Validation request failed:', error);
    }
  }

  async function runInsert() {
    if (!validateLocalInputs(true)) {
      return;
    }

    setNotice(idrAdminSettings.strings.inserting, 'info');

    try {
      const payload = await apiFetch('imports', {
        method: 'POST',
        body: buildFormData(),
      });

      setNotice(payload.message, 'success');
    } catch (error) {
      setNotice(error.message, 'error');
      console.error('Insert request failed:', error);
    }
  }

  async function runDelete() {
    if (!validateLocalInputs(false)) {
      return;
    }

    setNotice(idrAdminSettings.strings.deleting, 'info');

    try {
      const payload = await apiFetch(
        `imports/latest?selected_table=${encodeURIComponent(getSelectedTable())}`,
        { method: 'DELETE' }
      );

      setNotice(payload.message, 'success');
    } catch (error) {
      setNotice(error.message, 'error');
      console.error('Delete request failed:', error);
    }
  }

  function openModal(message, action) {
    state.currentAction = action;
    els.modalMessage.textContent = message;
    els.modalBackdrop.hidden = false;
  }

  function closeModal() {
    state.currentAction = null;
    els.modalBackdrop.hidden = true;
  }

  els.validateButton.addEventListener('click', runValidation);

  els.insertButton.addEventListener('click', () => {
    if (!validateLocalInputs(true)) {
      return;
    }
    openModal(idrAdminSettings.strings.confirmInsert, 'insert');
  });

  els.deleteButton.addEventListener('click', () => {
    if (!validateLocalInputs(false)) {
      return;
    }
    openModal(idrAdminSettings.strings.confirmDelete, 'delete');
  });

  els.modalConfirm.addEventListener('click', async () => {
    const action = state.currentAction;
    closeModal();

    if (action === 'insert') {
      await runInsert();
    } else if (action === 'delete') {
      await runDelete();
    }
  });

  els.modalCancel.addEventListener('click', closeModal);

  els.modalBackdrop.addEventListener('click', (event) => {
    if (event.target === els.modalBackdrop) {
      closeModal();
    }
  });

  loadTables();
})();