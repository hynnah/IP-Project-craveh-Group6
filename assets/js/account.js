// Account page: login, sign up, Google sign-in and logout.

let isSignup = false;

// Pages that send guests here to log in first (?redirect=...). Only these are allowed.
const REDIRECT_PAGES = {
  'checkout.html': 'Log in or sign up to place your order.',
  'history.html': 'Log in or sign up to see your order history.',
};
const redirectTo = new URLSearchParams(location.search).get('redirect');
const redirectNotice = REDIRECT_PAGES[redirectTo] || null;

function setMode(signup) {
  isSignup = signup;
  document.getElementById('auth-title').textContent = signup ? 'Sign Up' : 'Login';
  document.getElementById('name-group').hidden = !signup;
  document.getElementById('auth-submit').textContent = signup ? 'Create Account' : 'Login';
  document.getElementById('auth-toggle-text').textContent = signup ? 'Already have an account? ' : "Don't have an account? ";
  document.getElementById('auth-toggle-btn').textContent = signup ? 'Login' : 'Sign Up';
  document.getElementById('password-input').autocomplete = signup ? 'new-password' : 'current-password';
  document.getElementById('auth-error').textContent = '';
}

function showView(user) {
  document.getElementById('auth-view').hidden = !!user;
  document.getElementById('profile-view').hidden = !user;
  if (user) {
    document.getElementById('profile-name').textContent = user.name;
    document.getElementById('profile-email').textContent = user.email;
  }
  updateAccountLink(user);
}

// Shared by both sign-in routes: whatever got us a session, the page does
// the same thing afterwards.
async function completeLogin(user, form) {
  await loadSession();
  if (redirectNotice) {
    location.href = redirectTo;
    return;
  }
  if (form) form.reset();
  showView(user);
  updateCartBadge();
}

async function handleSubmit(e) {
  e.preventDefault();
  const error = document.getElementById('auth-error');
  const submit = document.getElementById('auth-submit');
  const body = {
    email: document.getElementById('email-input').value.trim(),
    password: document.getElementById('password-input').value,
  };

  if (isSignup) {
    body.name = document.getElementById('name-input').value.trim();
    if (!body.name) return (error.textContent = 'Please enter your name.');
    if (body.password.length < 6) return (error.textContent = 'Password must be at least 6 characters.');
  }
  if (!body.email || !body.password) return (error.textContent = 'Please enter your email and password.');

  error.textContent = '';
  submit.disabled = true;
  const data = await api(isSignup ? 'signup.php' : 'login.php', body);
  submit.disabled = false;

  if (!data.success) {
    error.textContent = data.error || 'Something went wrong. Please try again.';
    return;
  }
  // Login may restore a saved cart.
  await completeLogin(data.user, e.target);
}

// ── Google sign-in ──

// Google's library is loaded with async/defer, so it may not have arrived by
// the time this script runs. Wait for it rather than assuming.
function waitForGoogleLibrary(timeoutMs = 6000) {
  return new Promise(resolve => {
    const startedAt = Date.now();
    (function poll() {
      if (window.google && window.google.accounts && window.google.accounts.id) return resolve(true);
      if (Date.now() - startedAt > timeoutMs) return resolve(false);
      setTimeout(poll, 100);
    })();
  });
}

// Google hands the browser a signed ID token. The browser never decides who
// the user is — it forwards the token, and the server decides.
async function handleGoogleCredential(response) {
  const error = document.getElementById('auth-error');
  error.textContent = '';

  const data = await api('google_login.php', { credential: response.credential });
  if (!data.success) {
    error.textContent = data.error || 'Google sign-in failed. Please try again.';
    return;
  }
  await completeLogin(data.user, document.getElementById('auth-form'));
}

async function initGoogleSignIn() {
  const config = await api('auth_config.php');
  const clientId = config.googleClientId;
  // Not configured on this machine: leave the button hidden rather than
  // showing one that is guaranteed to fail.
  if (!clientId) return;

  if (!(await waitForGoogleLibrary())) return;

  const container = document.getElementById('google-signin-btn');
  // Unhide before rendering: Google sizes the button from its container, and
  // a hidden element measures zero.
  document.getElementById('google-divider').hidden = false;
  container.hidden = false;

  google.accounts.id.initialize({
    client_id: clientId,
    callback: handleGoogleCredential,
  });
  google.accounts.id.renderButton(container, {
    theme: 'outline',
    size: 'large',
    text: 'continue_with',
    shape: 'rectangular',
    width: Math.min(container.clientWidth || 320, 400),
  });
}

document.addEventListener('DOMContentLoaded', async () => {
  document.getElementById('auth-form').addEventListener('submit', handleSubmit);
  document.getElementById('auth-toggle-btn').addEventListener('click', () => setMode(!isSignup));
  document.getElementById('logout-btn').addEventListener('click', async () => {
    await saveQueue;
    await api('logout.php', {});
    cart = [];
    updateCartBadge();
    setMode(false);
    showView(null);
  });

  const session = await sessionReady;
  if (session.user && redirectNotice) {
    location.replace(redirectTo);
    return;
  }
  if (redirectNotice) document.querySelector('.page-subtitle').textContent = redirectNotice;
  showView(session.user);

  if (!session.user) initGoogleSignIn();
});
