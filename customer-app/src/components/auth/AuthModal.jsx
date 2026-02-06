import React, { useState } from 'react';
import { GoogleOAuthProvider, GoogleLogin } from '@react-oauth/google';
import { useAuth } from '../../contexts/AuthContext';
import { useToast } from '../../contexts/ToastContext';
import theme from '../../config/theme';

/**
 * AuthModal — Login/Signup modal with Google and Phone OTP
 *
 * States: idle → sending → code_sent → verifying → needs_name → done
 */
export default function AuthModal({ isOpen, onClose }) {
  const { loginWithGoogle, sendPhoneCode, verifyPhoneCode } = useAuth();
  const { showToast } = useToast();

  // Phone auth state machine
  const [phoneStep, setPhoneStep] = useState('idle'); // idle | sending | code_sent | verifying | needs_name
  const [phone, setPhone] = useState('');
  const [code, setCode] = useState('');
  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');
  const [error, setError] = useState('');

  const googleClientId = window.wpConfig?.googleClientId || '';

  const resetState = () => {
    setPhoneStep('idle');
    setPhone('');
    setCode('');
    setFirstName('');
    setLastName('');
    setError('');
  };

  const handleClose = () => {
    resetState();
    onClose();
  };

  // Google login handler
  const handleGoogleSuccess = async (credentialResponse) => {
    try {
      setError('');
      await loginWithGoogle(credentialResponse);
      showToast('התחברת בהצלחה!', 'success');
      handleClose();
    } catch (err) {
      setError(err.message || 'Google login failed');
    }
  };

  // Phone: send code
  const handleSendCode = async () => {
    if (!phone.trim()) return;
    try {
      setError('');
      setPhoneStep('sending');
      await sendPhoneCode(phone.trim());
      setPhoneStep('code_sent');
    } catch (err) {
      setError(err.message || 'Failed to send code');
      setPhoneStep('idle');
    }
  };

  // Phone: verify code
  const handleVerifyCode = async () => {
    if (!code.trim()) return;
    try {
      setError('');
      setPhoneStep('verifying');
      const data = await verifyPhoneCode(
        phone.trim(),
        code.trim(),
        firstName.trim() || undefined,
        lastName.trim() || undefined
      );

      if (data.needs_name) {
        setPhoneStep('needs_name');
        return;
      }

      // Success
      showToast('התחברת בהצלחה!', 'success');
      handleClose();
    } catch (err) {
      setError(err.message || 'Verification failed');
      setPhoneStep('code_sent');
    }
  };

  // Phone: submit name for new customer then verify again
  const handleSubmitName = async () => {
    if (!firstName.trim() || !lastName.trim()) {
      setError('יש להזין שם פרטי ושם משפחה');
      return;
    }
    try {
      setError('');
      setPhoneStep('verifying');
      const data = await verifyPhoneCode(
        phone.trim(),
        code.trim(),
        firstName.trim(),
        lastName.trim()
      );

      if (data.needs_name) {
        // The OTP was already consumed — need to re-send
        setError('הקוד פג תוקף, נשלח קוד חדש');
        setPhoneStep('idle');
        setCode('');
        return;
      }

      showToast('התחברת בהצלחה!', 'success');
      handleClose();
    } catch (err) {
      setError(err.message || 'Registration failed');
      setPhoneStep('needs_name');
    }
  };

  if (!isOpen) return null;

  return (
    <div
      style={{
        position: 'fixed',
        inset: 0,
        zIndex: 9999,
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        backgroundColor: 'rgba(0, 0, 0, 0.5)',
        direction: 'rtl',
      }}
      onClick={(e) => {
        if (e.target === e.currentTarget) handleClose();
      }}
    >
      <div
        style={{
          backgroundColor: '#FFFFFF',
          borderRadius: theme.borderRadius.xl,
          width: '100%',
          maxWidth: '400px',
          maxHeight: '90vh',
          overflowY: 'auto',
          boxShadow: theme.shadows.lg,
          margin: theme.spacing.md,
        }}
      >
        {/* Header */}
        <div
          style={{
            display: 'flex',
            justifyContent: 'space-between',
            alignItems: 'center',
            padding: `${theme.spacing.lg} ${theme.spacing.lg} ${theme.spacing.md}`,
            borderBottom: `1px solid ${theme.colors.border}`,
          }}
        >
          <h2
            style={{
              fontSize: theme.typography.desktop.h2,
              fontWeight: 700,
              color: theme.colors.text.primary,
              margin: 0,
            }}
          >
            התחברות
          </h2>
          <button
            onClick={handleClose}
            style={{
              width: '36px',
              height: '36px',
              border: 'none',
              backgroundColor: theme.colors.background,
              borderRadius: theme.borderRadius.full,
              cursor: 'pointer',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              fontSize: '1.25rem',
              color: theme.colors.text.secondary,
            }}
            aria-label="סגור"
          >
            ✕
          </button>
        </div>

        {/* Content */}
        <div style={{ padding: theme.spacing.lg }}>
          {/* Error message */}
          {error && (
            <div
              style={{
                backgroundColor: '#FEF2F2',
                border: '1px solid #FECACA',
                color: '#DC2626',
                padding: theme.spacing.sm,
                borderRadius: theme.borderRadius.md,
                marginBottom: theme.spacing.md,
                fontSize: '0.875rem',
                textAlign: 'center',
              }}
            >
              {error}
            </div>
          )}

          {/* Google Sign-In */}
          {googleClientId && (
            <>
              <div style={{ marginBottom: theme.spacing.lg }}>
                <GoogleOAuthProvider clientId={googleClientId}>
                  <div style={{ display: 'flex', justifyContent: 'center' }}>
                    <GoogleLogin
                      onSuccess={handleGoogleSuccess}
                      onError={() => setError('Google login failed')}
                      shape="rectangular"
                      size="large"
                      width="100%"
                      text="signin_with"
                      locale="he"
                    />
                  </div>
                </GoogleOAuthProvider>
              </div>

              {/* Divider */}
              <div
                style={{
                  display: 'flex',
                  alignItems: 'center',
                  gap: theme.spacing.md,
                  marginBottom: theme.spacing.lg,
                }}
              >
                <div style={{ flex: 1, height: '1px', backgroundColor: theme.colors.border }} />
                <span style={{ color: theme.colors.text.muted, fontSize: '0.875rem' }}>או</span>
                <div style={{ flex: 1, height: '1px', backgroundColor: theme.colors.border }} />
              </div>
            </>
          )}

          {/* Phone Auth */}
          <div>
            <h3
              style={{
                fontSize: theme.typography.desktop.h3,
                fontWeight: 600,
                color: theme.colors.text.primary,
                marginBottom: theme.spacing.md,
                marginTop: 0,
              }}
            >
              התחברות עם טלפון
            </h3>

            {/* Step 1: Phone number input */}
            {(phoneStep === 'idle' || phoneStep === 'sending') && (
              <div>
                <label
                  style={{
                    display: 'block',
                    fontSize: '0.875rem',
                    fontWeight: 500,
                    color: theme.colors.text.secondary,
                    marginBottom: theme.spacing.xs,
                  }}
                >
                  מספר טלפון
                </label>
                <input
                  type="tel"
                  value={phone}
                  onChange={(e) => setPhone(e.target.value)}
                  placeholder="050-1234567"
                  dir="ltr"
                  style={{
                    width: '100%',
                    padding: `${theme.spacing.sm} ${theme.spacing.md}`,
                    border: `1px solid ${theme.colors.border}`,
                    borderRadius: theme.borderRadius.md,
                    fontSize: '1rem',
                    outline: 'none',
                    boxSizing: 'border-box',
                    textAlign: 'left',
                  }}
                  onKeyDown={(e) => e.key === 'Enter' && handleSendCode()}
                  disabled={phoneStep === 'sending'}
                />
                <button
                  onClick={handleSendCode}
                  disabled={!phone.trim() || phoneStep === 'sending'}
                  style={{
                    width: '100%',
                    marginTop: theme.spacing.md,
                    padding: `${theme.spacing.sm} ${theme.spacing.md}`,
                    backgroundColor: theme.colors.primary,
                    color: '#FFFFFF',
                    border: 'none',
                    borderRadius: theme.borderRadius.md,
                    fontSize: '1rem',
                    fontWeight: 600,
                    cursor: phoneStep === 'sending' ? 'wait' : 'pointer',
                    opacity: !phone.trim() || phoneStep === 'sending' ? 0.6 : 1,
                  }}
                >
                  {phoneStep === 'sending' ? 'שולח...' : 'שלח קוד אימות'}
                </button>
              </div>
            )}

            {/* Step 2: OTP code input */}
            {(phoneStep === 'code_sent' || phoneStep === 'verifying') && (
              <div>
                <p
                  style={{
                    fontSize: '0.875rem',
                    color: theme.colors.text.secondary,
                    marginBottom: theme.spacing.md,
                    marginTop: 0,
                  }}
                >
                  קוד אימות נשלח ל-{phone}
                </p>
                <label
                  style={{
                    display: 'block',
                    fontSize: '0.875rem',
                    fontWeight: 500,
                    color: theme.colors.text.secondary,
                    marginBottom: theme.spacing.xs,
                  }}
                >
                  קוד אימות
                </label>
                <input
                  type="text"
                  value={code}
                  onChange={(e) => setCode(e.target.value.replace(/\D/g, '').slice(0, 6))}
                  placeholder="000000"
                  maxLength={6}
                  dir="ltr"
                  autoFocus
                  style={{
                    width: '100%',
                    padding: `${theme.spacing.sm} ${theme.spacing.md}`,
                    border: `1px solid ${theme.colors.border}`,
                    borderRadius: theme.borderRadius.md,
                    fontSize: '1.5rem',
                    letterSpacing: '0.5em',
                    textAlign: 'center',
                    outline: 'none',
                    boxSizing: 'border-box',
                  }}
                  onKeyDown={(e) => e.key === 'Enter' && code.length === 6 && handleVerifyCode()}
                  disabled={phoneStep === 'verifying'}
                />
                <div style={{ display: 'flex', gap: theme.spacing.sm, marginTop: theme.spacing.md }}>
                  <button
                    onClick={() => {
                      setPhoneStep('idle');
                      setCode('');
                      setError('');
                    }}
                    style={{
                      flex: 1,
                      padding: `${theme.spacing.sm} ${theme.spacing.md}`,
                      backgroundColor: theme.colors.background,
                      color: theme.colors.text.secondary,
                      border: `1px solid ${theme.colors.border}`,
                      borderRadius: theme.borderRadius.md,
                      fontSize: '0.875rem',
                      cursor: 'pointer',
                    }}
                  >
                    שנה מספר
                  </button>
                  <button
                    onClick={handleVerifyCode}
                    disabled={code.length !== 6 || phoneStep === 'verifying'}
                    style={{
                      flex: 2,
                      padding: `${theme.spacing.sm} ${theme.spacing.md}`,
                      backgroundColor: theme.colors.primary,
                      color: '#FFFFFF',
                      border: 'none',
                      borderRadius: theme.borderRadius.md,
                      fontSize: '1rem',
                      fontWeight: 600,
                      cursor: phoneStep === 'verifying' ? 'wait' : 'pointer',
                      opacity: code.length !== 6 || phoneStep === 'verifying' ? 0.6 : 1,
                    }}
                  >
                    {phoneStep === 'verifying' ? 'מאמת...' : 'אמת'}
                  </button>
                </div>
              </div>
            )}

            {/* Step 3: Name fields for new customers */}
            {phoneStep === 'needs_name' && (
              <div>
                <p
                  style={{
                    fontSize: '0.875rem',
                    color: theme.colors.text.secondary,
                    marginBottom: theme.spacing.md,
                    marginTop: 0,
                  }}
                >
                  נראה שזו הפעם הראשונה שלך - מה השם שלך?
                </p>
                <div style={{ display: 'flex', gap: theme.spacing.sm, marginBottom: theme.spacing.md }}>
                  <div style={{ flex: 1 }}>
                    <label
                      style={{
                        display: 'block',
                        fontSize: '0.875rem',
                        fontWeight: 500,
                        color: theme.colors.text.secondary,
                        marginBottom: theme.spacing.xs,
                      }}
                    >
                      שם פרטי
                    </label>
                    <input
                      type="text"
                      value={firstName}
                      onChange={(e) => setFirstName(e.target.value)}
                      autoFocus
                      style={{
                        width: '100%',
                        padding: `${theme.spacing.sm} ${theme.spacing.md}`,
                        border: `1px solid ${theme.colors.border}`,
                        borderRadius: theme.borderRadius.md,
                        fontSize: '1rem',
                        outline: 'none',
                        boxSizing: 'border-box',
                      }}
                    />
                  </div>
                  <div style={{ flex: 1 }}>
                    <label
                      style={{
                        display: 'block',
                        fontSize: '0.875rem',
                        fontWeight: 500,
                        color: theme.colors.text.secondary,
                        marginBottom: theme.spacing.xs,
                      }}
                    >
                      שם משפחה
                    </label>
                    <input
                      type="text"
                      value={lastName}
                      onChange={(e) => setLastName(e.target.value)}
                      style={{
                        width: '100%',
                        padding: `${theme.spacing.sm} ${theme.spacing.md}`,
                        border: `1px solid ${theme.colors.border}`,
                        borderRadius: theme.borderRadius.md,
                        fontSize: '1rem',
                        outline: 'none',
                        boxSizing: 'border-box',
                      }}
                    />
                  </div>
                </div>
                <button
                  onClick={handleSubmitName}
                  disabled={!firstName.trim() || !lastName.trim()}
                  style={{
                    width: '100%',
                    padding: `${theme.spacing.sm} ${theme.spacing.md}`,
                    backgroundColor: theme.colors.primary,
                    color: '#FFFFFF',
                    border: 'none',
                    borderRadius: theme.borderRadius.md,
                    fontSize: '1rem',
                    fontWeight: 600,
                    cursor: 'pointer',
                    opacity: !firstName.trim() || !lastName.trim() ? 0.6 : 1,
                  }}
                >
                  השלם הרשמה
                </button>
              </div>
            )}

            {/* Resend code link */}
            {phoneStep === 'code_sent' && (
              <button
                onClick={handleSendCode}
                style={{
                  marginTop: theme.spacing.md,
                  background: 'none',
                  border: 'none',
                  color: theme.colors.primary,
                  cursor: 'pointer',
                  fontSize: '0.875rem',
                  textDecoration: 'underline',
                  padding: 0,
                }}
              >
                שלח קוד מחדש
              </button>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
