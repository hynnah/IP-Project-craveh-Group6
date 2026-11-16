// History page: the signed-in user's real orders, loaded from api/history.php.

// Where the FOOD is. Cancelled is not a stage — it is the tracker being
// abandoned, so it gets its own notice instead of a dot.
const ORDER_STAGES = [
  { key: 'pending', label: 'Placed' },
  { key: 'confirmed', label: 'Confirmed' },
  { key: 'preparing', label: 'Preparing' },
  { key: 'out_for_delivery', label: 'On the Way' },
  { key: 'delivered', label: 'Delivered' },
];

const STATUS_LABELS = {
  pending: 'Pending',
  confirmed: 'Confirmed',
  preparing: 'Preparing',
  out_for_delivery: 'Out for Delivery',
  delivered: 'Delivered',
  cancelled: 'Cancelled',
};

const ICONS = {
  receipt: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/></svg>',
  check: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>',
  phone: '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.13.96.36 1.9.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0122 16.92z"/></svg>',
  card: '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>',
  warning: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
};

// Where the MONEY is. Deliberately separate from the delivery status above:
// a GCash order is paid before it is cooked, a COD order is delivered before
// it is paid, so one label could not describe both.
function paymentLabel(order) {
  if (order.paymentMethod === 'gcash') {
    return {
      pending: 'Awaiting GCash payment',
      paid: 'Paid via GCash',
      failed: 'GCash payment failed',
      refunded: 'Refunded',
      unpaid: 'Unpaid',
    }[order.paymentStatus] || 'GCash';
  }
  return order.paymentStatus === 'paid' ? 'Paid on delivery' : 'Cash on Delivery';
}

// MySQL hands back "YYYY-MM-DD HH:MM:SS". Safari refuses to parse that with a
// space in it, so swap in the "T" the Date constructor expects.
function formatOrderDate(value) {
  const date = new Date(String(value || '').replace(' ', 'T'));
  if (isNaN(date.getTime())) return '';
  return date.toLocaleString(undefined, {
    month: 'short', day: 'numeric', year: 'numeric',
    hour: 'numeric', minute: '2-digit',
  });
}

function progressTracker(status) {
  if (status === 'cancelled') {
    return `<div class="order-cancelled-notice">${ICONS.warning}<span>This order was cancelled</span></div>`;
  }

  const current = ORDER_STAGES.findIndex(stage => stage.key === status);
  if (current === -1) return '';
  const finished = status === 'delivered';

  return `<div class="order-progress">${ORDER_STAGES.map((stage, i) => {
    const state = i < current || (finished && i === current) ? 'completed' : (i === current ? 'active' : '');
    const step = `
      <div class="progress-step ${state}">
        <div class="progress-dot">${ICONS.check}</div>
        <span class="progress-label">${stage.label}</span>
      </div>`;
    const line = i < ORDER_STAGES.length - 1
      ? `<div class="progress-line ${i < current ? 'completed' : ''}"></div>`
      : '';
    return step + line;
  }).join('')}</div>`;
}

function orderCard(order) {
  const items = order.items.map(item => `
    <div class="order-item">
      <span class="order-item-name">
        <span class="item-qty-badge">${item.quantity}x</span> ${escapeHtml(item.name)}
      </span>
      <span class="order-item-price">${formatPrice(item.price * item.quantity)}</span>
    </div>`).join('');

  return `
    <div class="order-card">
      <div class="order-header">
        <div class="order-header-left">
          <div class="order-id-wrapper">
            ${ICONS.receipt}
            <p class="order-id">Order #${order.id}</p>
          </div>
          <span class="order-date">${escapeHtml(formatOrderDate(order.createdAt))}</span>
        </div>
        <span class="order-status order-status-${escapeHtml(order.status)}">
          ${escapeHtml(STATUS_LABELS[order.status] || order.status)}
        </span>
      </div>

      ${progressTracker(order.status)}

      <div class="order-items">${items}</div>

      <div class="order-footer">
        <div class="order-delivery">
          <p>Delivery Address</p>
          <p class="order-delivery-address">${escapeHtml(order.address)}</p>
          <p class="order-phone">${ICONS.phone}${escapeHtml(order.phone)}</p>
        </div>
        <div class="order-total">
          <p>Total</p>
          <span class="order-total-amount">${formatPrice(order.total)}</span>
          <p class="order-payment-method">${ICONS.card}${escapeHtml(paymentLabel(order))}</p>
        </div>
      </div>
    </div>`;
}

function show(id, visible) {
  const el = document.getElementById(id);
  if (el) el.hidden = !visible;
}

async function loadHistory() {
  const data = await api('history.php');

  show('history-loading', false);

  if (!data.success) {
    // 401 is not an error to apologise for — it just means "log in first".
    const needsLogin = /log in/i.test(data.error || '');
    show('history-auth', needsLogin);
    if (!needsLogin) {
      document.getElementById('history-error').textContent =
        data.error || 'Could not load your orders. Please try again.';
    }
    return;
  }

  show('history-auth', false);
  document.getElementById('history-error').textContent = '';

  const orders = data.orders || [];
  show('history-empty', orders.length === 0);
  show('history-orders', orders.length > 0);
  document.getElementById('history-orders').innerHTML = orders.map(orderCard).join('');
}

document.addEventListener('DOMContentLoaded', loadHistory);

// Coming back from the GCash tab does not reload the page, so refresh when it
// regains focus — that is when a "pending" payment has usually become "paid".
document.addEventListener('visibilitychange', () => {
  if (document.visibilityState === 'visible') loadHistory();
});
