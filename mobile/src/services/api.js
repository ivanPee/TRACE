const API_BASE_URL = 'http://192.168.1.10/trace/TRACE/backend/index.php';
const REQUEST_TIMEOUT_MS = 15000;

async function request(path, { method = 'GET', token, body } = {}) {
  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), REQUEST_TIMEOUT_MS);

  let response;
  let json;

  try {
    response = await fetch(`${API_BASE_URL}${path}`, {
      method,
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
      },
      body: body ? JSON.stringify(body) : undefined,
      signal: controller.signal,
    });
    json = await response.json();
  } catch (error) {
    if (error.name === 'AbortError') {
      throw new Error('Connection timed out. Check that XAMPP Apache/MySQL are running and the API URL is reachable.');
    }

    throw new Error('Cannot connect to the backend. Check that XAMPP Apache/MySQL are running.');
  } finally {
    clearTimeout(timeoutId);
  }

  if (!response.ok || json.success === false) {
    throw new Error(json.message || 'Request failed.');
  }

  return json.data || {};
}

export const api = {
  login: (payload) => request('/api/login', { method: 'POST', body: payload }),
  registerParent: (payload) => request('/api/register/parent', { method: 'POST', body: payload }),
  registerDriver: (payload) => request('/api/register/driver', { method: 'POST', body: payload }),
  parentDashboard: (token) => request('/api/parent/dashboard', { token }),
  driverDashboard: (token) => request('/api/driver/dashboard', { token }),
  drivers: (token) => request('/api/drivers', { token }),
  addStudent: (token, payload) => request('/api/parents/students', { method: 'POST', token, body: payload }),
  createBooking: (token, payload) => request('/api/bookings', { method: 'POST', token, body: payload }),
  updateRideStatus: (token, rideId, status) => request(`/api/driver/rides/${rideId}/status`, { method: 'POST', token, body: { status } }),
  pushLocation: (token, rideId, location) => request(`/api/driver/rides/${rideId}/location`, { method: 'POST', token, body: location }),
  transferRide: (token, rideId, driverId) => request(`/api/driver/rides/${rideId}/transfer`, { method: 'POST', token, body: { driverId } }),
};
