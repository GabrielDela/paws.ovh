/**
 * PAWS - Application Frontend
 * Vanilla JS, fetch API, SPA-like navigation
 */

(function () {
    'use strict';

    // --- Configuration ---
    const API_URL = '/router.php';

    // --- State ---
    let currentUser = null;
    let currentSection = 'auth';

    // --- DOM Elements ---
    const $ = (sel) => document.querySelector(sel);
    const $$ = (sel) => document.querySelectorAll(sel);

    // --- API Helper ---
    async function api(endpoint, options = {}) {
        const params = new URLSearchParams({ endpoint });

        // Ajouter les query params GET
        if (options.query) {
            Object.entries(options.query).forEach(([k, v]) => {
                if (v !== '' && v !== undefined) params.set(k, v);
            });
        }

        const fetchOptions = {
            method: options.method || 'GET',
            headers: {},
        };

        if (options.body) {
            fetchOptions.method = 'POST';
            fetchOptions.headers['Content-Type'] = 'application/json';
            fetchOptions.body = JSON.stringify(options.body);
        }

        const response = await fetch(`${API_URL}?${params}`, fetchOptions);
        return response.json();
    }

    // --- Toast Notifications ---
    function showToast(message, type = 'info') {
        const container = $('#toast-container');
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.textContent = message;
        container.appendChild(toast);

        setTimeout(() => {
            toast.style.animation = 'toastOut 0.3s ease forwards';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // --- Navigation ---
    function showSection(name) {
        $$('.section').forEach(s => s.classList.add('hidden'));
        const section = $(`#section-${name}`);
        if (section) {
            section.classList.remove('hidden');
            currentSection = name;
        }

        // Mettre a jour nav active
        $$('.nav-btn').forEach(btn => btn.classList.remove('active'));
        const activeBtn = $(`.nav-btn[data-section="${name}"]`);
        if (activeBtn) activeBtn.classList.add('active');
    }

    function renderNav() {
        const nav = $('#nav-bar');
        nav.innerHTML = '';

        if (!currentUser) {
            const btn = document.createElement('button');
            btn.className = 'nav-btn active';
            btn.dataset.section = 'auth';
            btn.textContent = 'Connexion';
            btn.addEventListener('click', () => showSection('auth'));
            nav.appendChild(btn);
            return;
        }

        // Boutons pour utilisateur connecte
        const sections = [
            { name: 'collection', label: 'Catalogue' },
            { name: 'deck', label: 'Mon Deck' },
        ];

        if (currentUser.role === 'admin') {
            sections.push({ name: 'admin', label: 'Admin' });
        }

        sections.forEach(s => {
            const btn = document.createElement('button');
            btn.className = 'nav-btn';
            btn.dataset.section = s.name;
            btn.textContent = s.label;
            btn.addEventListener('click', () => {
                showSection(s.name);
                if (s.name === 'collection') loadCards();
                if (s.name === 'deck') loadDeck();
                if (s.name === 'admin') loadAdmin();
            });
            nav.appendChild(btn);
        });

        // Bouton deconnexion
        const logoutBtn = document.createElement('button');
        logoutBtn.className = 'nav-btn nav-btn-logout';
        logoutBtn.textContent = 'Deconnexion';
        logoutBtn.addEventListener('click', handleLogout);
        nav.appendChild(logoutBtn);
    }

    // --- Auth ---
    function initAuth() {
        // Tabs
        $$('.auth-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                $$('.auth-tab').forEach(t => t.classList.remove('active'));
                tab.classList.add('active');

                const target = tab.dataset.tab;
                if (target === 'login') {
                    $('#form-login').classList.remove('hidden');
                    $('#form-register').classList.add('hidden');
                } else {
                    $('#form-login').classList.add('hidden');
                    $('#form-register').classList.remove('hidden');
                }
            });
        });

        // Login form
        $('#form-login').addEventListener('submit', async (e) => {
            e.preventDefault();
            const username = $('#login-username').value.trim();
            const password = $('#login-password').value;

            const result = await api('login', {
                body: { username, password }
            });

            const msg = $('#login-message');
            if (result.success) {
                currentUser = result.user;
                renderNav();
                showSection('collection');
                loadCards();
                showToast('Connexion reussie !', 'success');
            } else {
                msg.textContent = result.error;
                msg.className = 'form-message error';
            }
        });

        // Register form
        $('#form-register').addEventListener('submit', async (e) => {
            e.preventDefault();
            const username = $('#register-username').value.trim();

            const result = await api('register', {
                body: { username }
            });

            const msg = $('#register-message');
            if (result.success) {
                msg.innerHTML = `${escapeHtml(result.message)}<div class="password-display">${escapeHtml(result.password)}</div>`;
                msg.className = 'form-message success';
                showToast('Compte cree ! Notez votre mot de passe.', 'success');
            } else {
                msg.textContent = result.error;
                msg.className = 'form-message error';
            }
        });
    }

    async function handleLogout() {
        await api('logout', { method: 'POST' });
        currentUser = null;
        renderNav();
        showSection('auth');
        showToast('Deconnexion reussie.', 'info');
    }

    // --- Cards ---
    function createCardElement(card) {
        const div = document.createElement('div');
        div.className = `card rarity-${card.rarity}`;
        div.innerHTML = `
            <img class="card-image" src="${escapeHtml(card.image)}" alt="${escapeHtml(card.name)}" loading="lazy">
            <div class="card-body">
                <div class="card-header">
                    <span class="card-name">${escapeHtml(card.name)}</span>
                    <span class="card-number">#${card.number}</span>
                </div>
                <span class="card-rarity ${card.rarity}">${card.rarity}</span>
            </div>
        `;
        div.addEventListener('click', () => openCardModal(card));
        return div;
    }

    function openCardModal(card) {
        const modal = $('#card-modal');
        const content = $('#modal-card');
        content.innerHTML = `
            <img class="card-image" src="${escapeHtml(card.image)}" alt="${escapeHtml(card.name)}">
            <div class="card-name">${escapeHtml(card.name)}</div>
            <div class="card-number">#${card.number}</div>
            <span class="card-rarity ${card.rarity}">${card.rarity}</span>
        `;
        modal.classList.remove('hidden');
    }

    async function loadCards(query = '', type = 'number') {
        const result = await api('cards', { query: { q: query, type: type } });
        const grid = $('#cards-grid');
        grid.innerHTML = '';

        if (result.success && result.cards.length > 0) {
            result.cards.forEach(card => {
                grid.appendChild(createCardElement(card));
            });
        } else {
            grid.innerHTML = '<p class="empty-message">Aucune carte trouvee.</p>';
        }
    }

    function initSearch() {
        const searchInput = $('#search-input');
        const searchType = $('#search-type');
        const searchBtn = $('#btn-search');

        const doSearch = () => {
            loadCards(searchInput.value.trim(), searchType.value);
        };

        searchBtn.addEventListener('click', doSearch);
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') doSearch();
        });

        // Debounce pour recherche en temps reel
        let timeout;
        searchInput.addEventListener('input', () => {
            clearTimeout(timeout);
            timeout = setTimeout(doSearch, 400);
        });

        // Filtres de rarete rapides
        $$('.rarity-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                $$('.rarity-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');

                const rarity = btn.dataset.rarity;
                if (rarity) {
                    searchInput.value = rarity;
                    searchType.value = 'rarity';
                    loadCards(rarity, 'rarity');
                } else {
                    searchInput.value = '';
                    searchType.value = 'number';
                    loadCards();
                }
            });
        });
    }

    // --- Deck ---
    async function loadDeck() {
        const result = await api('deck');
        const grid = $('#deck-grid');
        const empty = $('#deck-empty');
        grid.innerHTML = '';

        if (result.success && result.cards.length > 0) {
            empty.classList.add('hidden');
            result.cards.forEach(card => {
                grid.appendChild(createCardElement(card));
            });
        } else {
            empty.classList.remove('hidden');
        }

        // Charger l'etat du claim
        loadClaimStatus();
    }

    async function loadClaimStatus() {
        const result = await api('canClaim');
        const btn = $('#btn-claim');
        const status = $('#claim-status');

        if (result.canClaim) {
            btn.disabled = false;
            btn.textContent = 'Recuperer ma carte du jour';
            status.textContent = `${result.remaining} carte(s) restante(s) aujourd'hui`;
        } else {
            btn.disabled = true;
            btn.textContent = 'Deja recuperee';
            status.textContent = 'Revenez demain pour une nouvelle carte !';
        }
    }

    async function handleClaim() {
        const result = await api('claimDaily', { method: 'POST' });

        if (result.success) {
            // Afficher la modal de revelation
            const modal = $('#claim-modal');
            const display = $('#claim-card-display');

            display.innerHTML = '';
            const cardEl = createCardElement(result.card);
            cardEl.style.cursor = 'default';
            display.appendChild(cardEl);

            modal.classList.remove('hidden');
            showToast(`Vous avez obtenu ${result.card.name} !`, 'success');

            // Recharger le deck et le statut
            loadDeck();
        } else {
            showToast(result.error, 'error');
        }
    }

    function initDeck() {
        $('#btn-claim').addEventListener('click', handleClaim);
        $('#btn-close-claim').addEventListener('click', () => {
            $('#claim-modal').classList.add('hidden');
        });
    }

    // --- Admin ---
    async function loadAdmin() {
        const result = await api('adminOverview');
        const panel = $('#admin-users');
        panel.innerHTML = '';

        if (!result.success) {
            panel.innerHTML = '<p class="empty-message">Acces refuse.</p>';
            return;
        }

        const { users, decks, allCards } = result;

        users.forEach(user => {
            const userCards = decks[user.username] || [];
            const card = document.createElement('div');
            card.className = 'admin-user-card';

            card.innerHTML = `
                <div class="admin-user-header">
                    <span class="admin-user-name">${escapeHtml(user.username)}</span>
                    <span class="admin-user-role ${user.role}">${user.role}</span>
                </div>
                <div class="admin-user-info">
                    <span>Limite : ${user.dailyLimit}/jour</span>
                    <span>Claims : ${user.claimsToday || 0}</span>
                    <span>Dernier : ${user.lastClaimDate || 'Jamais'}</span>
                    <span>Cartes : ${userCards.length}</span>
                </div>
                <div class="admin-actions">
                    <button class="btn btn-small btn-secondary btn-regen-pwd" data-username="${escapeHtml(user.username)}">
                        Regener. MDP
                    </button>
                    <button class="btn btn-small btn-success btn-reset-claim" data-username="${escapeHtml(user.username)}">
                        Reset Claim
                    </button>
                    <div class="daily-limit-form">
                        <input type="number" min="1" max="50" value="${user.dailyLimit}" class="input-daily-limit" data-username="${escapeHtml(user.username)}">
                        <button class="btn btn-small btn-secondary btn-set-limit" data-username="${escapeHtml(user.username)}">
                            Appliquer
                        </button>
                    </div>
                </div>
                <div class="admin-user-cards" data-username="${escapeHtml(user.username)}">
                    ${userCards.map(c => `
                        <div class="admin-mini-card">
                            <span>#${c.number} ${escapeHtml(c.name)}</span>
                            <button class="remove-card" data-username="${escapeHtml(user.username)}" data-card-id="${c.id}">&times;</button>
                        </div>
                    `).join('')}
                </div>
                <div class="admin-add-card" data-username="${escapeHtml(user.username)}">
                    <select class="select-add-card">
                        <option value="">Ajouter une carte...</option>
                        ${allCards.map(c => `<option value="${c.id}">#${c.number} ${escapeHtml(c.name)} (${c.rarity})</option>`).join('')}
                    </select>
                    <button class="btn btn-small btn-success btn-add-card" data-username="${escapeHtml(user.username)}">
                        Ajouter
                    </button>
                </div>
            `;

            panel.appendChild(card);
        });

        // Bind admin actions
        bindAdminActions();
    }

    function bindAdminActions() {
        // Regenerer mot de passe
        $$('.btn-regen-pwd').forEach(btn => {
            btn.addEventListener('click', async () => {
                const username = btn.dataset.username;
                if (!confirm(`Regenerer le mot de passe de ${username} ?`)) return;

                const result = await api('adminAction', {
                    body: { action: 'regeneratePassword', username }
                });

                if (result.success) {
                    showToast(`Nouveau mot de passe : ${result.password}`, 'success');
                    // Afficher le mot de passe dans un prompt
                    alert(`Nouveau mot de passe pour ${username} :\n\n${result.password}\n\nNotez-le bien !`);
                } else {
                    showToast(result.error, 'error');
                }
            });
        });

        // Reset claim
        $$('.btn-reset-claim').forEach(btn => {
            btn.addEventListener('click', async () => {
                const username = btn.dataset.username;
                const result = await api('adminAction', {
                    body: { action: 'resetDailyClaim', username }
                });

                if (result.success) {
                    showToast(`Claim reset pour ${username}`, 'success');
                    loadAdmin();
                } else {
                    showToast(result.error, 'error');
                }
            });
        });

        // Set daily limit
        $$('.btn-set-limit').forEach(btn => {
            btn.addEventListener('click', async () => {
                const username = btn.dataset.username;
                const input = $(`.input-daily-limit[data-username="${username}"]`);
                const limit = parseInt(input.value);

                const result = await api('adminAction', {
                    body: { action: 'setDailyLimit', username, limit }
                });

                if (result.success) {
                    showToast(`Limite mise a jour pour ${username}`, 'success');
                    loadAdmin();
                } else {
                    showToast(result.error, 'error');
                }
            });
        });

        // Remove card
        $$('.remove-card').forEach(btn => {
            btn.addEventListener('click', async () => {
                const username = btn.dataset.username;
                const cardId = parseInt(btn.dataset.cardId);

                const result = await api('adminAction', {
                    body: { action: 'removeCard', username, cardId }
                });

                if (result.success) {
                    showToast('Carte retiree', 'success');
                    loadAdmin();
                } else {
                    showToast(result.error, 'error');
                }
            });
        });

        // Add card
        $$('.btn-add-card').forEach(btn => {
            btn.addEventListener('click', async () => {
                const username = btn.dataset.username;
                const select = btn.parentElement.querySelector('.select-add-card');
                const cardId = parseInt(select.value);

                if (!cardId) {
                    showToast('Selectionnez une carte', 'error');
                    return;
                }

                const result = await api('adminAction', {
                    body: { action: 'addCard', username, cardId }
                });

                if (result.success) {
                    showToast('Carte ajoutee', 'success');
                    loadAdmin();
                } else {
                    showToast(result.error, 'error');
                }
            });
        });
    }

    // --- Modals ---
    function initModals() {
        // Fermer modal card zoom
        $('#card-modal .modal-overlay').addEventListener('click', () => {
            $('#card-modal').classList.add('hidden');
        });
        $('#card-modal .modal-close').addEventListener('click', () => {
            $('#card-modal').classList.add('hidden');
        });

        // Fermer modal claim
        $('#claim-modal .modal-overlay').addEventListener('click', () => {
            $('#claim-modal').classList.add('hidden');
        });

        // Fermer avec Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                $$('.modal').forEach(m => m.classList.add('hidden'));
            }
        });
    }

    // --- Utils ---
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(String(text)));
        return div.innerHTML;
    }

    // --- Init ---
    async function init() {
        // Verifier si deja connecte
        const result = await api('me');
        if (result.success) {
            currentUser = result.user;
            renderNav();
            showSection('collection');
            loadCards();
        } else {
            renderNav();
            showSection('auth');
        }

        initAuth();
        initSearch();
        initDeck();
        initModals();
    }

    // Lancer l'application
    document.addEventListener('DOMContentLoaded', init);
})();
