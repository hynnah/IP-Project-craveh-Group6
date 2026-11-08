// History page: guests must log in. Orders are still static sample data for now.

document.addEventListener('DOMContentLoaded', async () => {
  const session = await sessionReady;
  document.getElementById('history-auth').hidden = !!session.user;
  document.getElementById('history-orders').hidden = !session.user;
});
