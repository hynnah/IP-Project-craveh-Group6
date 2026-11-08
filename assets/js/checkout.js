// Checkout page: order summary built from the cart, delivery form, and placing the order.

const TRASH_ICON = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>';
const FORM_FIELDS = ['street', 'city', 'state', 'zip', 'building', 'floor', 'apt', 'landmark', 'notes', 'phone'];
const DRAFT_KEY = 'craveh-checkout-draft';

let currentUser = null;

function field(name) {
  return document.getElementById('co-' + name);
}

function renderCart() {
  const container = document.getElementById('checkout-items');
  const total = getCartTotal();
  const empty = cart.length === 0;

  document.getElementById('checkout-summary-footer').hidden = empty;
  document.getElementById('checkout-details').hidden = empty;
  document.getElementById('checkout-total').textContent = formatPrice(total);
  document.getElementById('place-order-label').textContent = `Place Order - ${formatPrice(total)}`;

  if (empty) {
    container.innerHTML = `
      <div class="empty-state">
        <h3>Your cart is empty</h3>
        <p>Add some items from the menu before checking out.</p>
        <a href="menu.html" class="btn btn-primary">Browse Menu</a>
      </div>`;
    return;
  }

  container.innerHTML = cart.map(item => `
    <div class="checkout-item" data-id="${escapeHtml(item.id)}">
      <div class="checkout-item-info">
        <img class="checkout-item-image" src="${escapeHtml(imageUrl(item.image))}" alt="${escapeHtml(item.name)}">
        <div class="checkout-item-details">
          <span class="checkout-item-name">${escapeHtml(item.name)}</span>
          <span class="checkout-item-price">${formatPrice(item.price)} each</span>
        </div>
      </div>
      <div class="checkout-item-controls">
        <div class="quantity-controls">
          <button type="button" class="quantity-btn" data-action="decrease" aria-label="Decrease quantity"${item.quantity <= 1 ? ' disabled' : ''}>−</button>
          <span class="quantity-display">${item.quantity}</span>
          <button type="button" class="quantity-btn" data-action="increase" aria-label="Increase quantity"${item.quantity >= MAX_QUANTITY ? ' disabled' : ''}>+</button>
        </div>
        <button type="button" class="btn-remove" data-action="remove" aria-label="Remove ${escapeHtml(item.name)}">${TRASH_ICON}</button>
      </div>
    </div>`).join('');
}

// ── Delivery form ──

function readForm() {
  const values = {};
  FORM_FIELDS.forEach(name => (values[name] = field(name).value.trim()));
  values.phone = values.phone.replace(/[\s-]/g, '');
  return values;
}

function validateForm(values) {
  const errors = {
    street: values.street.length < 5 ? 'Enter a valid street address.' : '',
    city: values.city.length < 2 ? 'City is required.' : '',
    state: !values.state ? 'Country/State is required.' : '',
    zip: !/^\d{4,10}$/.test(values.zip) ? 'Enter a valid ZIP code.' : '',
    phone: !/^\d{7,15}$/.test(values.phone) ? 'Enter a valid phone number (7–15 digits).' : '',
  };
  Object.entries(errors).forEach(([name, message]) => {
    document.getElementById(`co-${name}-error`).textContent = message;
  });
  const firstInvalid = Object.keys(errors).find(name => errors[name]);
  if (firstInvalid) field(firstInvalid).focus();
  return !firstInvalid;
}

// One address string, as the original app stores it: street, apt, city, state zip [building, floor, landmark] (notes)
function buildAddress(v) {
  let address = v.street;
  if (v.apt) address += ', ' + v.apt;
  address += `, ${v.city}, ${v.state} ${v.zip}`;
  const extras = [v.building, v.floor, v.landmark].filter(Boolean).join(', ');
  if (extras) address += ` [${extras}]`;
  if (v.notes) address += ` (${v.notes})`;
  return address;
}

// Guests are sent to log in; keep what they typed so it is still there when they come back.
function saveDraft() {
  try { sessionStorage.setItem(DRAFT_KEY, JSON.stringify(readForm())); } catch (e) {}
}

function restoreDraft() {
  try {
    const draft = JSON.parse(sessionStorage.getItem(DRAFT_KEY) || 'null');
    sessionStorage.removeItem(DRAFT_KEY);
    if (draft) FORM_FIELDS.forEach(name => (field(name).value = draft[name] || ''));
  } catch (e) {}
}

// ── Placing the order ──

function setPlacing(placing) {
  const btn = document.getElementById('place-order-btn');
  btn.disabled = placing;
  document.getElementById('place-order-label').textContent =
    placing ? 'Placing Order…' : `Place Order - ${formatPrice(getCartTotal())}`;
}

function showOrderSuccess(orderId) {
  document.getElementById('checkout-content').innerHTML = `
    <div class="success-card">
      <div class="success-icon">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
      </div>
      <h2>Order Placed Successfully!</h2>
      <p>Order #${escapeHtml(String(orderId))} has been received and will be delivered soon.</p>
      <div class="success-actions">
        <a href="history.html" class="btn btn-secondary">View Order History</a>
        <a href="menu.html" class="btn btn-primary">Continue Shopping</a>
      </div>
    </div>`;
}

async function handlePlaceOrder() {
  const orderError = document.getElementById('order-error');
  orderError.textContent = '';

  if (!currentUser) {
    saveDraft();
    location.href = 'account.html?redirect=checkout.html';
    return;
  }

  const values = readForm();
  if (!validateForm(values) || cart.length === 0) return;

  setPlacing(true);
  // Make sure the latest cart is saved before the server reads it.
  await saveQueue;
  const data = await api('save_order.php', {
    userId: currentUser.id,
    address: buildAddress(values),
    phone: values.phone,
    items: cart.map(item => ({ menuItemId: item.id, name: item.name, price: item.price, quantity: item.quantity })),
  });

  if (!data.success) {
    setPlacing(false);
    orderError.textContent = data.error || 'Could not place your order. Please try again.';
    return;
  }

  // The server already emptied the saved cart.
  cart = [];
  updateCartBadge();
  showOrderSuccess(data.orderId);
}

document.addEventListener('DOMContentLoaded', async () => {
  const [session, menu] = await Promise.all([sessionReady, api('menu_items.php')]);
  currentUser = session.user;

  // Use current prices and drop items that were removed from the menu.
  if (menu.success) {
    const removed = syncCartWithMenu(menu.items);
    if (removed.length) showToast(`No longer available, removed: ${removed.join(', ')}`);
  }
  renderCart();
  restoreDraft();

  document.getElementById('place-order-btn').addEventListener('click', handlePlaceOrder);

  document.getElementById('checkout-items').addEventListener('click', e => {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;
    const id = btn.closest('.checkout-item').dataset.id;
    const item = cart.find(i => i.id === id);
    if (!item) return;

    if (btn.dataset.action === 'increase') updateCartQuantity(id, item.quantity + 1);
    if (btn.dataset.action === 'decrease') updateCartQuantity(id, item.quantity - 1);
    if (btn.dataset.action === 'remove') {
      if (!confirm(`Remove ${item.name} from your cart?`)) return;
      removeFromCart(id);
    }
    renderCart();
  });
});
