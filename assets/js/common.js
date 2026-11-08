// Shared helpers for every page: API calls, session, cart, nav.

const MAX_QUANTITY = 50;
let cart = [];

async function api(path, body) {
  const options = body === undefined
    ? { credentials: 'same-origin' }
    : {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
      };
  const res = await fetch('api/' + path, options);
  let data;
  try {
    data = await res.json();
  } catch (e) {
    data = { success: false, error: 'Unexpected server response' };
  }
  return data;
}

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text ?? '';
  return div.innerHTML;
}

// Images uploaded through admin are stored relative to pages/ ("../uploads/..."); pages live at the root here.
function imageUrl(src) {
  return (src || '').replace(/^\.\.\//, '');
}

function formatPrice(amount) {
  return '₱' + amount.toFixed(2);
}

async function getSession() {
  const data = await api('session.php');
  return data.success ? data : { user: null, cart: [] };
}

// Loads the session (user + saved cart) once; pages await this instead of fetching again.
async function loadSession() {
  const session = await getSession();
  cart = session.cart || [];
  return session;
}

let sessionReady = loadSession();

// Show the logged-in user's first name on the Account nav link.
function updateAccountLink(user) {
  const link = document.querySelector('.nav-link[href="account.html"]');
  if (link) link.textContent = user ? user.name.split(' ')[0] : 'Account';
}

// ── Cart ──

function getCartCount() {
  return cart.reduce((sum, item) => sum + item.quantity, 0);
}

function getCartTotal() {
  return cart.reduce((sum, item) => sum + item.price * item.quantity, 0);
}

function updateCartBadge() {
  const count = getCartCount();
  document.querySelectorAll('.cart-badge').forEach(badge => {
    badge.textContent = count > 99 ? '99+' : count;
    badge.hidden = count === 0;
  });
}

// Saves run one after another so a slow request can't overwrite a newer cart.
let saveQueue = Promise.resolve();
function saveCart() {
  updateCartBadge();
  saveQueue = saveQueue.then(async () => {
    const data = await api('cart.php', { action: 'save', cart });
    if (!data.success) showToast(data.error || 'Could not save your cart');
  });
  return saveQueue;
}

function addToCart(item) {
  const existing = cart.find(i => i.id === item.id);
  if (existing) {
    if (existing.quantity >= MAX_QUANTITY) return false;
    existing.quantity++;
  } else {
    cart.push({ id: item.id, name: item.name, price: item.price, image: item.image, quantity: 1 });
  }
  saveCart();
  return true;
}

function updateCartQuantity(itemId, quantity) {
  const item = cart.find(i => i.id === itemId);
  if (!item) return;
  item.quantity = Math.min(Math.max(quantity, 1), MAX_QUANTITY);
  saveCart();
}

function removeFromCart(itemId) {
  cart = cart.filter(i => i.id !== itemId);
  saveCart();
}

// Refresh name/price/image from the live menu and drop items that are no longer available.
// Returns the names of removed items.
function syncCartWithMenu(menuItems) {
  const byId = new Map(menuItems.map(item => [item.id, item]));
  const removed = cart.filter(i => !byId.has(i.id)).map(i => i.name);
  const synced = cart
    .filter(i => byId.has(i.id))
    .map(i => {
      const { name, price, image } = byId.get(i.id);
      return { ...i, name, price, image };
    });
  const changed = JSON.stringify(synced) !== JSON.stringify(cart);
  cart = synced;
  if (changed) saveCart();
  return removed;
}

let toastTimer;
function showToast(message) {
  let toast = document.querySelector('.toast');
  if (!toast) {
    toast = document.createElement('div');
    toast.className = 'toast';
    toast.setAttribute('role', 'status');
    document.body.appendChild(toast);
  }
  toast.textContent = message;
  toast.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => toast.classList.remove('show'), 2500);
}

document.addEventListener('DOMContentLoaded', async () => {
  const hamburger = document.getElementById('hamburger-btn');
  const nav = document.getElementById('main-nav');
  if (hamburger && nav) {
    hamburger.addEventListener('click', () => {
      hamburger.classList.toggle('active');
      nav.classList.toggle('open');
    });
  }

  const session = await sessionReady;
  updateAccountLink(session.user);
  updateCartBadge();
});
