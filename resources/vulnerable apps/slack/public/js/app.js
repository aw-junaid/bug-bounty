// VULN: DOM XSS - user-controlled data inserted via innerHTML
// VULN: Auth tokens stored in localStorage - accessible to XSS
// VULN: Exposed API tokens hardcoded in JS

// VULN: Sensitive tokens stored in localStorage (accessible to XSS)
const API_TOKEN    = localStorage.getItem('api_token');
const USER_ID      = localStorage.getItem('user_id');
const USERNAME     = localStorage.getItem('username');

// VULN: Hardcoded internal API key exposed to all users
const INTERNAL_KEY = 'internal-debug-key-1234';
const API_BASE_URL = '/api/v1';

let currentChannelId = null;

// DOM references
const messagesList    = document.getElementById('messages-list');
const messageInput    = document.getElementById('message-input');
const channelList     = document.getElementById('channel-list');
const chatHeader      = document.getElementById('chat-header');
const chatHeaderTitle = document.getElementById('chat-header-title');

/** Escape a string so it is safe to insert into an HTML attribute or text node. */
function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

// ── Channel creation modal ──────────────────────────────────────────────────

document.getElementById('create-channel-btn')?.addEventListener('click', () => {
    document.getElementById('create-channel-modal').style.display = 'flex';
    document.getElementById('new-channel-name').focus();
});

// Submit on Enter in the channel name field
document.getElementById('new-channel-name')?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') createChannel();
    if (e.key === 'Escape') closeCreateChannelModal();
});

function closeCreateChannelModal() {
    document.getElementById('create-channel-modal').style.display = 'none';
    document.getElementById('new-channel-name').value = '';
    document.getElementById('new-channel-desc').value = '';
    const err = document.getElementById('create-channel-error');
    err.style.display = 'none';
    err.textContent   = '';
}

async function createChannel() {
    const name = document.getElementById('new-channel-name').value.trim();
    const desc = document.getElementById('new-channel-desc').value.trim();
    const err  = document.getElementById('create-channel-error');

    if (!name) {
        err.textContent   = 'Channel name is required.';
        err.style.display = 'block';
        return;
    }

    try {
        const resp = await fetch(`${API_BASE_URL}/channels/create.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${API_TOKEN}`,
            },
            body: JSON.stringify({ name, description: desc }),
        });
        const data = await resp.json();
        if (data.success) {
            closeCreateChannelModal();
            await loadChannels();
            // Switch to the newly created channel
            if (data.channel_id) {
                setActiveChannel(data.channel_id, name);
            }
        } else {
            err.textContent   = data.error || 'Failed to create channel.';
            err.style.display = 'block';
        }
    } catch (e) {
        err.textContent   = 'Network error. Please try again.';
        err.style.display = 'block';
        console.error('Failed to create channel', e);
    }
}

// Close modal on overlay click
document.getElementById('create-channel-modal')?.addEventListener('click', (e) => {
    if (e.target === document.getElementById('create-channel-modal')) {
        closeCreateChannelModal();
    }
});

// ── Channel / message helpers ───────────────────────────────────────────────

// VULN: XSS - channel name injected directly into DOM via innerHTML
function setActiveChannel(channelId, channelName) {
    currentChannelId = channelId;
    // VULN: channelName comes from server but may contain user-supplied data
    chatHeaderTitle.innerHTML = `# ${channelName}`;  // VULN: DOM XSS if channelName contains <script>

    // Show search controls now that a channel is selected
    document.getElementById('search-toggle-btn').style.display = '';

    loadMessages(channelId);

    document.querySelectorAll('.channel-item').forEach(el => {
        el.classList.remove('active');
    });
    const el = document.querySelector(`[data-channel-id="${channelId}"]`);
    if (el) el.classList.add('active');
}

/**
 * Parse a [file:ID:PATH:NAME] token stored by the upload endpoint and return
 * an HTML snippet. Images are rendered inline; other files get a download link.
 */
function renderFileToken(token) {
    // format: [file:<id>:<path>:<name>]  (name may contain any characters)
    const match = token.match(/^\[file:(\d+):([^[\]:]+):(.+)\]$/);
    if (!match) return null;
    const [, , filePath, fileName] = match;
    const ext = fileName.split('.').pop().toLowerCase();
    const imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
    const safeHref  = escHtml(filePath);
    const safeName  = escHtml(fileName);
    if (imageExts.includes(ext)) {
        return `<span class="message-file-attachment">
            <a href="${safeHref}" target="_blank">${safeName}</a>
            <img src="${safeHref}" alt="${safeName}" loading="lazy">
        </span>`;
    }
    return `<span class="message-file-attachment">📎 <a href="${safeHref}" target="_blank">${safeName}</a></span>`;
}

// VULN: Stored XSS - message content rendered via innerHTML without sanitization
function renderMessage(msg) {
    const div = document.createElement('div');
    div.className = 'message';

    let contentHtml = msg.message_content;

    // Replace [file:...] tokens with inline file/image HTML
    contentHtml = contentHtml.replace(/\[file:\d+:[^\]]+\]/g, (token) => {
        return renderFileToken(token) || token;
    });

    // VULN: msg.message_content and msg.username rendered directly via innerHTML
    div.innerHTML = `
        <div class="message-avatar">${msg.username ? msg.username[0].toUpperCase() : '?'}</div>
        <div class="message-content">
            <span class="username">${msg.username}</span>
            <span class="timestamp">${new Date(msg.timestamp).toLocaleTimeString()}</span>
            <div class="text">${contentHtml}</div>
        </div>
    `;
    messagesList.appendChild(div);
}

async function loadMessages(channelId) {
    messagesList.innerHTML = '';
    try {
        const resp = await fetch(`${API_BASE_URL}/messages/fetch.php?channel_id=${channelId}`, {
            headers: { 'Authorization': `Bearer ${API_TOKEN}` }
        });
        const data = await resp.json();
        if (data.messages) {
            data.messages.reverse().forEach(renderMessage);
        }
        messagesList.scrollTop = messagesList.scrollHeight;
    } catch (e) {
        console.error('Failed to load messages', e);
    }
}

async function sendMessage() {
    const content = messageInput.value.trim();
    if (!content || !currentChannelId) return;

    try {
        await fetch(`${API_BASE_URL}/messages/send.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${API_TOKEN}`,
            },
            // VULN: user_id sent from client - IDOR on server
            body: JSON.stringify({
                channel_id: currentChannelId,
                message: content,
                user_id: USER_ID,
            }),
        });
        messageInput.value = '';
        loadMessages(currentChannelId);
    } catch (e) {
        console.error('Failed to send message', e);
    }
}

async function loadChannels() {
    try {
        const resp = await fetch(`${API_BASE_URL}/channels/list.php`);
        const data = await resp.json();
        channelList.innerHTML = '';
        if (data.channels) {
            data.channels.forEach(ch => {
                const el = document.createElement('div');
                el.className = 'channel-item';
                el.dataset.channelId = ch.channel_id;
                // VULN: channel_name from server injected via innerHTML - XSS
                el.innerHTML = ch.channel_name;
                el.addEventListener('click', () => setActiveChannel(ch.channel_id, ch.channel_name));
                channelList.appendChild(el);
            });
        }
    } catch (e) {
        console.error('Failed to load channels', e);
    }
}

// ── Search ──────────────────────────────────────────────────────────────────

function toggleSearch() {
    const searchBar     = document.getElementById('search-bar');
    const searchResults = document.getElementById('search-results');
    const isVisible     = searchBar.style.display !== 'none';
    if (isVisible) {
        closeSearch();
    } else {
        searchBar.style.display = 'flex';
        document.getElementById('search-input').focus();
        document.getElementById('search-toggle-btn').style.display = 'none';
    }
}

function closeSearch() {
    document.getElementById('search-bar').style.display     = 'none';
    document.getElementById('search-results').style.display = 'none';
    document.getElementById('search-input').value           = '';
    document.getElementById('search-toggle-btn').style.display = '';
}

async function performSearch() {
    const query = document.getElementById('search-input').value.trim();
    if (!query || !currentChannelId) return;

    const resultsDiv = document.getElementById('search-results');
    resultsDiv.innerHTML = '<em style="color:#717274">Searching…</em>';
    resultsDiv.style.display = 'block';

    try {
        const resp = await fetch(
            `${API_BASE_URL}/messages/search.php?q=${encodeURIComponent(query)}&channel_id=${encodeURIComponent(currentChannelId)}`,
            { headers: { 'Authorization': `Bearer ${API_TOKEN}` } }
        );
        const data = await resp.json();
        resultsDiv.innerHTML = '';

        if (!data.messages || data.messages.length === 0) {
            resultsDiv.innerHTML = '<em style="color:#717274">No results found.</em>';
            return;
        }

        data.messages.forEach(msg => {
            const item = document.createElement('div');
            item.className = 'search-result-item';
            item.innerHTML = `
                <span class="username">${escHtml(msg.username)}</span>
                <span class="timestamp">${new Date(msg.timestamp).toLocaleTimeString()}</span>
                <div class="text">${escHtml(msg.message_content)}</div>
            `;
            resultsDiv.appendChild(item);
        });
    } catch (e) {
        resultsDiv.innerHTML = '<em style="color:#e8912d">Search failed. Please try again.</em>';
        console.error('Search error', e);
    }
}

// Search on Enter key
document.getElementById('search-input')?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') performSearch();
    if (e.key === 'Escape') closeSearch();
});

// ── Misc ────────────────────────────────────────────────────────────────────

// VULN: XSS via URL parameter - reflected into DOM
function getQueryParam(name) {
    const url = new URLSearchParams(window.location.search);
    return url.get(name);
}

const highlightChannel = getQueryParam('channel');
if (highlightChannel) {
    // VULN: DOM XSS - channel param from URL inserted via innerHTML
    chatHeaderTitle.innerHTML = `Searching: ${highlightChannel}`;
}

// VULN: User info stored in DOM as data attributes - accessible to injected scripts
document.body.dataset.userId   = USER_ID;
document.body.dataset.apiToken = API_TOKEN;  // VULN: Token in DOM

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    if (!API_TOKEN) {
        window.location.href = '/login';
        return;
    }
    loadChannels();
});

// Send on Enter
messageInput?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
});
