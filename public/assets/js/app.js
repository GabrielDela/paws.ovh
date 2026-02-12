const user = window.APP_BOOT.user;
const api = async (endpoint, options = {}) => {
  const isFormData = options.body instanceof FormData;
  const res = await fetch(`router.php?endpoint=${endpoint}`, {
    method: options.method || 'GET',
    body: options.body,
    headers: isFormData ? {} : { 'Content-Type': 'application/x-www-form-urlencoded' }
  });
  return res.json();
};

const createCard = (card) => {
  const div = document.createElement('article');
  div.className = 'card';
  div.innerHTML = `
    <img src="${card.image}" alt="${card.name}">
    <h4>${card.number} - ${card.name}</h4>
    <span class="badge ${card.rarity}">${card.rarity}</span>`;
  div.addEventListener('click', () => openModal(card));
  return div;
};

const renderCards = (targetId, cards) => {
  const container = document.getElementById(targetId);
  if (!container) return;
  container.innerHTML = '';
  cards.forEach(card => container.appendChild(createCard(card)));
};

const message = (id, text) => {
  const el = document.getElementById(id);
  if (el) el.textContent = text;
};

document.getElementById('loginForm')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const body = new FormData(e.target);
  const data = await api('login', { method: 'POST', body });
  if (data.error) return message('authMessage', data.error);
  location.reload();
});

document.getElementById('registerForm')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const body = new FormData(e.target);
  const data = await api('register', { method: 'POST', body });
  if (data.error) return message('authMessage', data.error);
  message('authMessage', `Compte créé. Password UNIQUE: ${data.password}`);
});

document.querySelector('[data-action="logout"]')?.addEventListener('click', async () => {
  await api('logout', { method: 'POST' });
  location.reload();
});

document.getElementById('claimBtn')?.addEventListener('click', async () => {
  const data = await api('claimDaily', { method: 'POST' });
  if (data.error) return message('claimResult', data.error);
  message('claimResult', `Nouvelle carte: #${data.number} ${data.name}`);
  loadDeck();
});

document.getElementById('searchBtn')?.addEventListener('click', async () => {
  const n = document.getElementById('searchNumber').value;
  const r = document.getElementById('searchRarity').value;
  const data = await api(`cards&number=${encodeURIComponent(n)}&rarity=${encodeURIComponent(r)}`);
  if (data.error) return;
  renderCards('cardsGrid', data);
});

const loadDeck = async () => {
  if (!user) return;
  const data = await api('deck');
  if (!data.error) renderCards('deckGrid', data);
};

const loadAdmin = async () => {
  if (!user || user.role !== 'admin') return;
  const data = await api('adminOverview');
  const tbody = document.querySelector('#adminTable tbody');
  tbody.innerHTML = '';
  data.forEach(row => {
    const tr = document.createElement('tr');
    tr.innerHTML = `<td>${row.username}</td><td>${row.role}</td><td>${row.cardIds.length}</td>
      <td><input type="number" value="${row.dailyLimit}" min="1" style="width:80px"></td>
      <td>
        <button data-a="limit">Limit</button>
        <button data-a="regen">Pass</button>
        <button data-a="reset">Reset</button>
        <button data-a="add">+1</button>
        <button data-a="remove">-1</button>
      </td>`;
    const limitInput = tr.querySelector('input');
    tr.querySelectorAll('button').forEach(btn => btn.addEventListener('click', async () => {
      const actionMap = { limit: 'setDailyLimit', regen: 'regenPassword', reset: 'resetDailyClaim', add: 'addCard', remove: 'removeCard' };
      const fd = new FormData();
      fd.set('action', actionMap[btn.dataset.a]);
      fd.set('username', row.username);
      fd.set('dailyLimit', limitInput.value);
      fd.set('cardId', '1');
      const response = await api('adminAction', { method: 'POST', body: fd });
      if (response.generatedPassword) {
        message('adminMessage', `Nouveau password ${row.username}: ${response.generatedPassword}`);
      } else {
        message('adminMessage', 'Action admin effectuée.');
      }
      loadAdmin();
      loadDeck();
    }));
    tbody.appendChild(tr);
  });
};

const modal = document.getElementById('cardModal');
const openModal = (card) => {
  document.getElementById('modalImage').src = card.image;
  document.getElementById('modalTitle').textContent = `${card.number} - ${card.name}`;
  modal.hidden = false;
};
document.querySelector('.close-modal')?.addEventListener('click', () => modal.hidden = true);
modal?.addEventListener('click', (e) => { if (e.target === modal) modal.hidden = true; });

loadDeck();
loadAdmin();
