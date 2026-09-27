(function () {
  const table = document.getElementById('idr_table');
  const fileInput = document.getElementById('idr_csv');
  const validateBtn = document.getElementById('idr-validate-btn');
  const insertBtn = document.getElementById('idr-insert-btn');
  const deleteBtn = document.getElementById('idr-delete-btn');
  const notices = document.getElementById('idr-notices');
  const modal = document.getElementById('idr-modal');
  const modalMessage = document.getElementById('idr-modal-message');
  const modalConfirm = document.getElementById('idr-modal-confirm');
  const modalCancel = document.getElementById('idr-modal-cancel');

  let pendingAction = null;
  let uploadedToken = null;
  let uploadedName = null;
  let lastUploadedFingerprint = null;

  function notice(message, type = 'info') {
    notices.innerHTML = `<div class="notice notice-${type}"><p>${message}</p></div>`;
  }

  function showModal(message, onConfirm) {
    pendingAction = onConfirm;
    modalMessage.textContent = message;
    modal.hidden = false;
  }

  function hideModal() {
    modal.hidden = true;
    pendingAction = null;
  }

  function getFileFingerprint(file) {
    return `${file.name}|${file.size}|${file.lastModified}`;
  }

  async function uploadFileIfNeeded() {
    const file = fileInput.files[0];

    if (!file) {
      throw new Error('Choose a CSV file first.');
    }

    const fingerprint = getFileFingerprint(file);

    if (uploadedToken && lastUploadedFingerprint === fingerprint) {
      return { token: uploadedToken, file_name: uploadedName };
    }

    const formData = new FormData();
    formData.append('csv_file', file);

    const response = await fetch(IDR_CONFIG.root + 'upload', {
      method: 'POST',
      headers: {
        'X-WP-Nonce': IDR_CONFIG.nonce
      },
      body: formData
    });

    const result = await response.json();

    if (!result.success) {
      throw new Error(result.message || 'Upload failed.');
    }

    uploadedToken = result.token;
    uploadedName = result.file_name;
    lastUploadedFingerprint = fingerprint;

    return { token: uploadedToken, file_name: uploadedName };
  }

  async function post(path, body) {
    const response = await fetch(IDR_CONFIG.root + path, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': IDR_CONFIG.nonce
      },
      body: JSON.stringify(body)
    });

    return response.json();
  }

  validateBtn?.addEventListener('click', async function () {
    try {
      if (!table.value) {
        throw new Error('Select a destination table first.');
      }

      const upload = await uploadFileIfNeeded();

      const result = await post('validate', {
        table_name: table.value,
        upload_token: upload.token
      });

      notice(result.message, result.valid ? 'success' : 'error');
    } catch (error) {
      notice(error.message, 'error');
    }
  });

  insertBtn?.addEventListener('click', async function () {
    try {
      if (!table.value) {
        throw new Error('Select a destination table first.');
      }

      const upload = await uploadFileIfNeeded();

      const validateResult = await post('validate', {
        table_name: table.value,
        upload_token: upload.token
      });

      if (!validateResult.valid) {
        notice(validateResult.message, 'error');
        return;
      }

      showModal(
        `Insert ${validateResult.row_count} records into ${table.value}?`,
        async function () {
          const result = await post('import', {
            table_name: table.value,
            upload_token: upload.token
          });

          notice(result.message, result.success ? 'success' : 'error');

          if (result.success) {
            uploadedToken = null;
            uploadedName = null;
            lastUploadedFingerprint = null;
            fileInput.value = '';
          }

          hideModal();
        }
      );
    } catch (error) {
      notice(error.message, 'error');
    }
  });

  deleteBtn?.addEventListener('click', function () {
    if (!table.value) {
      notice('Select a destination table first.', 'error');
      return;
    }

    showModal(
      `Delete the records from the last successful import into ${table.value}?`,
      async function () {
        const result = await post('delete-last-batch', {
          table_name: table.value
        });

        notice(result.message, result.success ? 'success' : 'error');
        hideModal();
      }
    );
  });

  modalConfirm?.addEventListener('click', function () {
    if (pendingAction) {
      pendingAction();
    }
  });

  modalCancel?.addEventListener('click', hideModal);

  fileInput?.addEventListener('change', function () {
    uploadedToken = null;
    uploadedName = null;
    lastUploadedFingerprint = null;
  });
})();
