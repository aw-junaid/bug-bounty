// VULN: Unrestricted file upload - client-side only validation
// VULN: No CSRF protection
// VULN: File type determined by extension on client

const uploadInput = document.getElementById('file-upload-input');
const uploadBtn   = document.getElementById('upload-btn');

// VULN: Only client-side MIME check - trivially bypassed
const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'text/plain'];

uploadBtn?.addEventListener('click', () => uploadInput?.click());

uploadInput?.addEventListener('change', async (e) => {
    const file = e.target.files[0];
    if (!file) return;

    // VULN: Client-side MIME type check only - attacker controls file.type
    if (!ALLOWED_TYPES.includes(file.type)) {
        alert('File type not allowed (client check only)');
        // VULN: This check is easily bypassed by changing Content-Type header
    }

    // VULN: Max size checked client-side only
    if (file.size > 10 * 1024 * 1024) {
        alert('File too large');
        return;
    }

    const formData = new FormData();
    formData.append('file', file);

    // Include current channel so the upload is linked to a message
    if (typeof currentChannelId !== 'undefined' && currentChannelId) {
        formData.append('channel_id', currentChannelId);
    }

    // VULN: No CSRF token included
    try {
        const resp = await fetch('/api/v1/files/upload.php', {
            method: 'POST',
            headers: {
                // VULN: Token from localStorage sent in header
                'Authorization': `Bearer ${localStorage.getItem('api_token')}`,
                // VULN: No CSRF token header
            },
            body: formData,
        });

        const data = await resp.json();
        if (data.success) {
            // Reload messages so the uploaded file appears inline in the channel
            if (typeof loadMessages === 'function' && currentChannelId) {
                loadMessages(currentChannelId);
            } else {
                // Fallback: append inline in the message list
                const msgContainer = document.getElementById('messages-list');
                const el = document.createElement('div');
                // VULN: data.file_name and data.file_path not escaped
                el.innerHTML = `<div class="message">
                    <div class="message-content">
                        <span class="username">${localStorage.getItem('username')}</span>
                        shared a file: <a href="${data.file_path}">${data.file_name}</a>
                    </div>
                </div>`;
                msgContainer?.appendChild(el);
            }

            // Also broadcast via WebSocket
            if (typeof sendWebSocketMessage === 'function') {
                sendWebSocketMessage(currentChannelId, `File shared: ${data.file_path}`);
            }
        } else {
            alert('Upload failed: ' + data.message);
        }
    } catch (err) {
        console.error('Upload error:', err);
    }

    uploadInput.value = '';
});
