/**
 * Auth API Service for Customer App
 * Handles authentication endpoints (Google + Phone OTP)
 */

const baseUrl = () => window.wpConfig?.publicApiUrl || '/wp-json/squidly/v1/public/';

async function request(endpoint, options = {}) {
  const url = `${baseUrl()}${endpoint}`;
  const config = {
    credentials: 'include',
    headers: {
      'Content-Type': 'application/json',
      ...options.headers,
    },
    ...options,
  };

  const response = await fetch(url, config);

  if (!response.ok) {
    const error = await response.json().catch(() => ({ message: 'Request failed' }));
    throw new Error(error.error || error.message || `HTTP ${response.status}`);
  }

  return response.json();
}

/**
 * Google login with ID token
 */
export async function googleLogin(idToken) {
  return request('auth/google', {
    method: 'POST',
    body: JSON.stringify({ id_token: idToken }),
  });
}

/**
 * Send phone verification code
 */
export async function sendPhoneCode(phone) {
  return request('auth/phone/send-code', {
    method: 'POST',
    body: JSON.stringify({ phone }),
  });
}

/**
 * Verify phone code and optionally provide name for new customers
 */
export async function verifyPhoneCode({ phone, code, first_name, last_name, email }) {
  const body = { phone, code };
  if (first_name) body.first_name = first_name;
  if (last_name) body.last_name = last_name;
  if (email) body.email = email;

  return request('auth/phone/verify', {
    method: 'POST',
    body: JSON.stringify(body),
  });
}

/**
 * Get current authenticated customer
 */
export async function getMe(token) {
  return request('auth/me', {
    headers: {
      Authorization: `Bearer ${token}`,
    },
  });
}

/**
 * Logout — invalidate auth token
 */
export async function logout(token) {
  return request('auth/logout', {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${token}`,
    },
  });
}
