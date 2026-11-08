// Account page: login, sign up and logout.

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
  await loadSession();
  if (redirectNotice) {
    location.href = redirectTo;
    return;
  }
  e.target.reset();
  showView(data.user);
  updateCartBadge();
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
});
