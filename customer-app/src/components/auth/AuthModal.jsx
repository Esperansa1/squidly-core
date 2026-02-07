import React, { useState } from 'react';
import { GoogleOAuthProvider, GoogleLogin } from '@react-oauth/google';
import { useAuth } from '../../contexts/AuthContext';
import { useToast } from '../../contexts/ToastContext';
import theme from '../../config/theme';

/**
 * AuthModal — Login/Signup modal with Google and Phone OTP
 *
 * Phone flow states:
 *   idle → sending → code_sent → verifying → (needs_info | done)
 *
 * When needs_info: show registration form, then re-verify with info attached.
 * OTP is kept alive on the server until registration completes.
 */
export default function AuthModal({ isOpen, onClose }) {
  const { loginWithGoogle, sendPhoneCode, verifyPhoneCode } = useAuth();
  const { showToast } = useToast();

  // Phone auth state
  const [phoneStep, setPhoneStep] = useState('idle');
  const [phone, setPhone] = useState('');
  const [code, setCode] = useState('');
  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');
  const [email, setEmail] = useState('');
  const [error, setError] = useState('');

  const googleClientId = window.wpConfig?.googleClientId || '';

  const resetState = () => {
    setPhoneStep('idle');
    setPhone('');
    setCode('');
    setFirstName('');
    setLastName('');
    setEmail('');
    setError('');
  };

  const handleClose = () => {
    resetState();
    onClose();
  };

  // ===== Google =====
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

  // ===== Phone Step 1: Send code =====
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

  // ===== Phone Step 2: Verify code =====
  const handleVerifyCode = async () => {
    if (!code.trim() || code.length !== 6) return;
    try {
      setError('');
      setPhoneStep('verifying');

      const data = await verifyPhoneCode({
        phone: phone.trim(),
        code: code.trim(),
      });

      if (data.needs_info) {
        // New customer — show registration form
        // OTP stays alive on the server
        setPhoneStep('needs_info');
        return;
      }

      // Existing customer — logged in
      showToast('התחברת בהצלחה!', 'success');
      handleClose();
    } catch (err) {
      setError(err.message || 'Verification failed');
      setPhoneStep('code_sent');
    }
  };

  // ===== Phone Step 3: Submit registration info =====
  const handleRegister = async () => {
    if (!firstName.trim() || !lastName.trim()) {
      setError('יש להזין שם פרטי ושם משפחה');
      return;
    }
    try {
      setError('');
      setPhoneStep('verifying');

      const data = await verifyPhoneCode({
        phone: phone.trim(),
        code: code.trim(),
        firstName: firstName.trim(),
        lastName: lastName.trim(),
        email: email.trim() || undefined,
      });

      if (data.needs_info) {
        // OTP expired between steps — need to start over
        setError('הקוד פג תוקף, אנא שלח קוד חדש');
        setPhoneStep('idle');
        setCode('');
        return;
      }

      showToast('נרשמת והתחברת בהצלחה!', 'success');
      handleClose();
    } catch (err) {
      setError(err.message || 'Registration failed');
      setPhoneStep('needs_info');
    }
  };

  if (!isOpen) return null;

  // Shared input style
  const inputStyle = {
    width: '100%',
    padding: `${theme.spacing.sm} ${theme.spacing.md}`,
    border: `1px solid ${theme.colors.border}`,
    borderRadius: theme.borderRadius.md,
    fontSize: '1rem',
    outline: 'none',
    boxSizing: 'border-box',
  };

  const labelStyle = {
    display: 'block',
    fontSize: '0.875rem',
    fontWeight: 500,
    color: theme.colors.text.secondary,
    marginBottom: theme.spacing.xs,
  };

  const primaryBtnStyle = (disabled) => ({
    width: '100%',
    marginTop: theme.spacing.md,
    padding: `${theme.spacing.sm} ${theme.spacing.md}`,
    backgroundColor: theme.colors.primary,
    color: '#FFFFFF',
    border: 'none',
    borderRadius: theme.borderRadius.md,
    fontSize: '1rem',
    fontWeight: 600,
    cursor: disabled ? 'not-allowed' : 'pointer',
    opacity: disabled ? 0.6 : 1,
  });

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
          maxWidth: '420px',
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
            {phoneStep === 'needs_info' ? 'הרשמה' : 'התחברות'}
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
          {/* Error */}
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

          {/* ===== REGISTRATION FORM (new customer) ===== */}
          {phoneStep === 'needs_info' && (
            <div>
              <p
                style={{
                  fontSize: '0.875rem',
                  color: theme.colors.text.secondary,
                  marginBottom: theme.spacing.lg,
                  marginTop: 0,
                }}
              >
                נראה שזו הפעם הראשונה שלך! מלא את הפרטים הבאים כדי להשלים את ההרשמה.
              </p>

              {/* First + Last name row */}
              <div style={{ display: 'flex', gap: theme.spacing.sm, marginBottom: theme.spacing.md }}>
                <div style={{ flex: 1 }}>
                  <label style={labelStyle}>שם פרטי *</label>
                  <input
                    type="text"
                    value={firstName}
                    onChange={(e) => setFirstName(e.target.value)}
                    autoFocus
                    style={inputStyle}
                  />
                </div>
                <div style={{ flex: 1 }}>
                  <label style={labelStyle}>שם משפחה *</label>
                  <input
                    type="text"
                    value={lastName}
                    onChange={(e) => setLastName(e.target.value)}
                    style={inputStyle}
                  />
                </div>
              </div>

              {/* Email */}
              <div style={{ marginBottom: theme.spacing.sm }}>
                <label style={labelStyle}>אימייל (לא חובה)</label>
                <input
                  type="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder="example@email.com"
                  dir="ltr"
                  style={{ ...inputStyle, textAlign: 'left' }}
                />
              </div>

              <button
                onClick={handleRegister}
                disabled={!firstName.trim() || !lastName.trim() || phoneStep === 'verifying'}
                style={primaryBtnStyle(!firstName.trim() || !lastName.trim() || phoneStep === 'verifying')}
              >
                {phoneStep === 'verifying' ? 'נרשם...' : 'השלם הרשמה'}
              </button>
            </div>
          )}

          {/* ===== LOGIN FLOW (Google + Phone) ===== */}
          {phoneStep !== 'needs_info' && (
            <>
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

                {/* Step 1: Phone number */}
                {(phoneStep === 'idle' || phoneStep === 'sending') && (
                  <div>
                    <label style={labelStyle}>מספר טלפון</label>
                    <input
                      type="tel"
                      value={phone}
                      onChange={(e) => setPhone(e.target.value)}
                      placeholder="050-1234567"
                      dir="ltr"
                      style={{ ...inputStyle, textAlign: 'left' }}
                      onKeyDown={(e) => e.key === 'Enter' && handleSendCode()}
                      disabled={phoneStep === 'sending'}
                    />
                    <button
                      onClick={handleSendCode}
                      disabled={!phone.trim() || phoneStep === 'sending'}
                      style={primaryBtnStyle(!phone.trim() || phoneStep === 'sending')}
                    >
                      {phoneStep === 'sending' ? 'שולח...' : 'שלח קוד אימות'}
                    </button>
                  </div>
                )}

                {/* Step 2: OTP code */}
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
                    <label style={labelStyle}>קוד אימות</label>
                    <input
                      type="text"
                      value={code}
                      onChange={(e) => setCode(e.target.value.replace(/\D/g, '').slice(0, 6))}
                      placeholder="000000"
                      maxLength={6}
                      dir="ltr"
                      autoFocus
                      style={{
                        ...inputStyle,
                        fontSize: '1.5rem',
                        letterSpacing: '0.5em',
                        textAlign: 'center',
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

                    {/* Resend code link */}
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
                  </div>
                )}
              </div>
            </>
          )}
        </div>
      </div>
    </div>
  );
}
