import React, { createContext, useContext, useState, useEffect, useCallback } from 'react';
import * as authApi from '../services/authApi';

const AuthContext = createContext(null);

const TOKEN_KEY = 'squidly_auth_token';

export function AuthProvider({ children }) {
  const [customer, setCustomer] = useState(null);
  const [token, setToken] = useState(() => localStorage.getItem(TOKEN_KEY));
  const [loading, setLoading] = useState(!!localStorage.getItem(TOKEN_KEY));

  const isAuthenticated = !!customer;

  // On mount: restore session from stored token
  useEffect(() => {
    if (!token) {
      setLoading(false);
      return;
    }

    authApi
      .getMe(token)
      .then((data) => {
        setCustomer(data.customer);
      })
      .catch(() => {
        // Token invalid or expired — clear it
        localStorage.removeItem(TOKEN_KEY);
        setToken(null);
        setCustomer(null);
      })
      .finally(() => {
        setLoading(false);
      });
  }, []);

  const saveToken = useCallback((newToken) => {
    localStorage.setItem(TOKEN_KEY, newToken);
    setToken(newToken);
  }, []);

  /**
   * Login with Google credential response
   * @param {Object} credentialResponse — from Google Identity Services
   */
  const loginWithGoogle = useCallback(async (credentialResponse) => {
    const data = await authApi.googleLogin(credentialResponse.credential);
    saveToken(data.token);
    setCustomer(data.customer);
    return data;
  }, [saveToken]);

  /**
   * Send phone verification code
   */
  const sendPhoneCode = useCallback(async (phone) => {
    return authApi.sendPhoneCode(phone);
  }, []);

  /**
   * Verify phone code
   * @param {Object} params
   * @param {string} params.phone
   * @param {string} params.code
   * @param {string} [params.firstName]
   * @param {string} [params.lastName]
   * @param {string} [params.email]
   */
  const verifyPhoneCode = useCallback(async ({ phone, code, firstName, lastName, email }) => {
    const data = await authApi.verifyPhoneCode({
      phone,
      code,
      first_name: firstName,
      last_name: lastName,
      email,
    });

    // If needs_info, return the response for the UI to handle
    if (data.needs_info) {
      return data;
    }

    // Otherwise we're fully authenticated
    saveToken(data.token);
    setCustomer(data.customer);
    return data;
  }, [saveToken]);

  /**
   * Logout — optimistic: clear UI immediately, server call in background
   */
  const logout = useCallback(() => {
    const oldToken = token;
    // Optimistic: clear state instantly so UI updates immediately
    localStorage.removeItem(TOKEN_KEY);
    setToken(null);
    setCustomer(null);
    // Fire server invalidation in background — don't block UI
    if (oldToken) {
      authApi.logout(oldToken).catch(() => {});
    }
  }, [token]);

  /**
   * Refresh customer data from server
   */
  const refreshCustomer = useCallback(async () => {
    if (!token) return;
    try {
      const data = await authApi.getMe(token);
      setCustomer(data.customer);
    } catch {
      // Token may have expired
      logout();
    }
  }, [token, logout]);

  const value = {
    customer,
    token,
    loading,
    isAuthenticated,
    loginWithGoogle,
    sendPhoneCode,
    verifyPhoneCode,
    logout,
    refreshCustomer,
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
}

export default AuthContext;
