const API_BASE_PATH = '/trace3/backend/index.php';
const LAN_API_BASE_URL = `http://172.20.10.14${API_BASE_PATH}`;
const API_BASE_URLS = [
  LAN_API_BASE_URL,
  `http://10.0.2.2${API_BASE_PATH}`,
  `http://10.0.3.2${API_BASE_PATH}`,
  `http://192.168.137.1${API_BASE_PATH}`,
  `http://127.0.0.1${API_BASE_PATH}`,
];
const REQUEST_TIMEOUT_MS = 20000;

let resolvedApiBaseUrl = null;

function previewResponseBody(text) {
  return text.replace(/\s+/g, ' ').trim().slice(0, 240);
}

async function fetchJson(baseUrl, path, { method, token, body }) {
  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), REQUEST_TIMEOUT_MS);
  const isFormData = typeof FormData !== 'undefined' && body instanceof FormData;

  try {
    const response = await fetch(`${baseUrl}${path}`, {
      method,
      headers: {
        Accept: 'application/json',
        ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
      },
      body: body ? (isFormData ? body : JSON.stringify(body)) : undefined,
      signal: controller.signal,
    });

    const text = await response.text();
    let json = {};

    try {
      json = text ? JSON.parse(text) : {};
    } catch (error) {
      const details = previewResponseBody(text);
      const parseError = new Error(`Backend returned a non-JSON response (${response.status}). ${details || error.message}`);
      parseError.isBackendResponseError = true;
      throw parseError;
    }

    return { response, json };
  } finally {
    clearTimeout(timeoutId);
  }
}

async function request(path, { method = 'GET', token, body } = {}) {
  const baseUrls = resolvedApiBaseUrl
    ? [resolvedApiBaseUrl, ...API_BASE_URLS.filter((url) => url !== resolvedApiBaseUrl)]
    : API_BASE_URLS;

  let lastNetworkError = null;
  const attemptedUrls = [];

  for (const baseUrl of baseUrls) {
    let response;
    let json;

    try {
      ({ response, json } = await fetchJson(baseUrl, path, { method, token, body }));
      resolvedApiBaseUrl = baseUrl;
    } catch (error) {
      if (error.isBackendResponseError) {
        resolvedApiBaseUrl = baseUrl;
        throw error;
      }

      lastNetworkError = error;
      attemptedUrls.push(`${baseUrl}${path} (${error.name || 'Error'}: ${error.message})`);
      continue;
    }

    if (!response.ok || json.success === false) {
      throw new Error(json.message || 'Request failed.');
    }

    return json.data ?? {};
  }

  const attemptedMessage = attemptedUrls.length ? ` Tried: ${attemptedUrls.join('; ')}` : '';

  if (lastNetworkError?.name === 'AbortError') {
    throw new Error(`Connection timed out. Open this exact URL on the device browser: ${LAN_API_BASE_URL}/api/login. If it times out there too, connect the device to the same hotspot/Wi-Fi as this computer and allow Apache through Windows Firewall.${attemptedMessage}`);
  }

  throw new Error(`Cannot connect to the backend. Open this exact URL on the device browser: ${LAN_API_BASE_URL}/api/login. If it fails there too, connect the device to the same hotspot/Wi-Fi as this computer and allow Apache through Windows Firewall.${attemptedMessage}`);
}

export const api = {
  login: (payload) => request('/api/login', { method: 'POST', body: payload }),
  registerParent: (payload) => request('/api/register/parent', { method: 'POST', body: payload }),
  registerDriver: (payload) => request('/api/register/driver', { method: 'POST', body: payload }),
  searchLocations: (payload) => request('/api/geocode/search', { method: 'POST', body: payload }),
  reverseGeocode: (payload) => request('/api/geocode/reverse', { method: 'POST', body: payload }),
  updateProfile: (token, payload) => request('/api/profile', { method: 'POST', token, body: payload }),
  parentDashboard: (token) => request('/api/parent/dashboard', { token }),
  driverDashboard: (token) => request('/api/driver/dashboard', { token }),
  studentDashboard: (token) => request('/api/child/dashboard', { token }),
  drivers: (token) => request('/api/drivers', { token }),
  addStudent: (token, payload) => request('/api/parents/children', { method: 'POST', token, body: payload }),
  updateStudent: (token, studentId, payload) => request(`/api/parents/children/${studentId}`, { method: 'POST', token, body: payload }),
  createBooking: (token, payload) => request('/api/bookings', { method: 'POST', token, body: payload }),
  estimateMonthlyPlan: (token, payload) => request('/api/parents/monthly-plan/estimate', { method: 'POST', token, body: payload }),
  payMonthlyPlan: (token, payload) => request('/api/parents/monthly-plan/pay', { method: 'POST', token, body: payload }),
  updateMonthlyPlan: (token, planId, payload) => request(`/api/parents/monthly-plan/${planId}/update`, { method: 'POST', token, body: payload }),
  cancelMonthlyPlan: (token, planId) => request(`/api/parents/monthly-plan/${planId}/cancel`, { method: 'POST', token }),
  cancelMonthlyPlanDay: (token, bookingId, payload) => request(`/api/parents/monthly-plan/bookings/${bookingId}/cancel-day`, { method: 'POST', token, body: payload }),
  approveBooking: (token, bookingId, payload) => request(`/api/driver/bookings/${bookingId}/approve`, { method: 'POST', token, body: payload }),
  rejectBooking: (token, bookingId) => request(`/api/driver/bookings/${bookingId}/reject`, { method: 'POST', token }),
  updateDriverAvailability: (token, isOnline) => request('/api/driver/availability', { method: 'POST', token, body: { isOnline } }),
  updateRideStatus: (token, rideId, status, payload) => {
    if (typeof FormData !== 'undefined' && payload instanceof FormData) {
      payload.append('status', status);
      return request(`/api/driver/rides/${rideId}/status`, { method: 'POST', token, body: payload });
    }

    return request(`/api/driver/rides/${rideId}/status`, { method: 'POST', token, body: { status, ...(payload || {}) } });
  },
  pushLocation: (token, rideId, location) => request(`/api/driver/rides/${rideId}/location`, { method: 'POST', token, body: location }),
  trackRide: (token, rideId) => request(`/api/rides/${rideId}/track`, { token }),
  transferRide: (token, rideId, payload) => request(`/api/driver/rides/${rideId}/transfer`, { method: 'POST', token, body: payload }),
  sendMessage: (token, payload) => request('/api/messages', { method: 'POST', token, body: typeof payload === 'string' ? { text: payload } : payload }),
};
