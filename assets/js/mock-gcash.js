// Drives the simulated GCash checkout page.
//
// Standalone on purpose: it does not load common.js, because this page is
// pretending to be a different system. It knows only the reference it was
// handed in the URL.

const params = new URLSearchParams(location.search);
const reference = params.get('ref') || '';

function show(id, visible) {
  document.getElementById(id).hidden = !visible;
}

function showError(message) {
  show('loading', false);
  show('details', false);
  const el = document.getElementById('error');
  el.textContent = message;
  el.hidden = false;
}

function peso(amount) {
  return '₱' + Number(amount).toFixed(2);
}

async function loadPayment() {
  if (!reference) {
    showError('No payment reference in the link.');
    return;
  }

  let data;
  try {
    const res = await fetch('api/mock_pay.php?ref=' + encodeURIComponent(reference));
    data = await res.json();
  } catch (e) {
    showError('Could not reach the payment service.');
    return;
  }

  if (!data.success) {
    showError(data.error || 'This payment link is not valid.');
    return;
  }
  if (data.status !== 'pending') {
    showError('This payment has already been ' + data.status + '.');
    return;
  }

  document.getElementById('amount').textContent = peso(data.amount);
  document.getElementById('order-id').textContent = data.orderId;
  document.getElementById('customer').textContent = data.customerName || 'Craveh customer';
  document.getElementById('reference').textContent = data.reference;

  show('loading', false);
  show('details', true);
}

async function settle(outcome) {
  const pay = document.getElementById('btn-pay');
  const cancel = document.getElementById('btn-cancel');
  pay.disabled = true;
  cancel.disabled = true;
  pay.textContent = outcome === 'paid' ? 'Authorising…' : 'Cancelling…';

  let data;
  try {
    const res = await fetch('api/mock_pay.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ ref: reference, outcome }),
    });
    data = await res.json();
  } catch (e) {
    showError('Could not reach the payment service.');
    return;
  }

  if (!data.success) {
    showError(data.error || 'The merchant could not be notified of this payment.');
    return;
  }

  // The provider has sent its webhook and Craveh has applied it, so the
  // order status is already correct by the time the browser gets back.
  location.href = 'history.html?order=' + encodeURIComponent(data.orderId);
}

document.getElementById('btn-pay').addEventListener('click', () => settle('paid'));
document.getElementById('btn-cancel').addEventListener('click', () => settle('failed'));

loadPayment();
