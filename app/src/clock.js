// One clock for the whole app. Now: the PC clock (offset 0).
// With the API: setServerTime(serverNowMs) after login -> every time comes from the server clock,
// so changing the PC clock cannot change tracked time.
let offset = 0;
export const nowMs = () => Date.now() + offset;
export const setServerTime = (serverMs) => { offset = serverMs - Date.now(); };
