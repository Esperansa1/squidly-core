import React from 'react';
import { t } from '../../i18n/translations';
import theme from '../../config/theme';

/**
 * CustomerInfoStep - Guest customer information form
 * Step 1 of checkout process
 */
export default function CustomerInfoStep({ data, onChange }) {
  const handleChange = (field, value) => {
    onChange({
      ...data,
      [field]: value,
    });
  };

  // Israeli phone validation regex
  const phoneRegex = /^(\+972|0)[2-9]\d{7,8}$/;
  const isPhoneValid = data.phone ? phoneRegex.test(data.phone.replace(/[-\s]/g, '')) : true;

  // Email validation
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  const isEmailValid = data.email ? emailRegex.test(data.email) : true;

  const inputStyle = (hasError = false) => ({
    width: '100%',
    border: `1px solid ${hasError ? theme.colors.error : theme.colors.border}`,
    borderRadius: theme.borderRadius.lg,
    padding: `${theme.spacing.md} ${theme.spacing.md}`,
    fontSize: theme.typography.mobile.body,
    color: theme.colors.text.primary,
    backgroundColor: theme.colors.cardBg,
    outline: 'none',
    transition: 'border-color 0.2s ease',
    boxSizing: 'border-box',
  });

  const labelStyle = {
    display: 'block',
    fontWeight: '600',
    fontSize: theme.typography.mobile.body,
    color: theme.colors.text.primary,
    marginBottom: theme.spacing.xs,
  };

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: theme.spacing.lg }}>
      <h2
        style={{
          fontSize: theme.typography.desktop.h2,
          fontWeight: '700',
          color: theme.colors.text.primary,
          margin: `0 0 ${theme.spacing.xs} 0`,
        }}
      >
        {t('customerInfo')}
      </h2>

      <p style={{ color: theme.colors.text.secondary, margin: 0 }}>
        {t('enterYourDetails')}
      </p>

      {/* Name Fields Row */}
      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: theme.spacing.md }}>
        {/* First Name */}
        <div>
          <label style={labelStyle}>
            {t('firstName')} <span style={{ color: theme.colors.error }}>*</span>
          </label>
          <input
            type="text"
            value={data.firstName}
            onChange={(e) => handleChange('firstName', e.target.value)}
            style={inputStyle()}
            placeholder={t('enterFirstName')}
            required
          />
        </div>

        {/* Last Name */}
        <div>
          <label style={labelStyle}>
            {t('lastName')} <span style={{ color: theme.colors.error }}>*</span>
          </label>
          <input
            type="text"
            value={data.lastName}
            onChange={(e) => handleChange('lastName', e.target.value)}
            style={inputStyle()}
            placeholder={t('enterLastName')}
            required
          />
        </div>
      </div>

      {/* Phone */}
      <div>
        <label style={labelStyle}>
          {t('phone')} <span style={{ color: theme.colors.error }}>*</span>
        </label>
        <input
          type="tel"
          value={data.phone}
          onChange={(e) => handleChange('phone', e.target.value)}
          style={inputStyle(!isPhoneValid)}
          placeholder="+972501234567"
          required
        />
        {!isPhoneValid && (
          <p
            style={{
              color: theme.colors.error,
              fontSize: theme.typography.mobile.small,
              marginTop: theme.spacing.xs,
            }}
          >
            {t('invalidPhone')}
          </p>
        )}
        <p
          style={{
            color: theme.colors.text.muted,
            fontSize: theme.typography.mobile.small,
            marginTop: theme.spacing.xs,
          }}
        >
          {t('phoneUsedForOrderUpdates')}
        </p>
      </div>

      {/* Email (Optional) */}
      <div>
        <label style={labelStyle}>
          {t('email')} ({t('optional')})
        </label>
        <input
          type="email"
          value={data.email}
          onChange={(e) => handleChange('email', e.target.value)}
          style={inputStyle(!isEmailValid)}
          placeholder="email@example.com"
        />
        {!isEmailValid && (
          <p
            style={{
              color: theme.colors.error,
              fontSize: theme.typography.mobile.small,
              marginTop: theme.spacing.xs,
            }}
          >
            {t('invalidEmail')}
          </p>
        )}
        <p
          style={{
            color: theme.colors.text.muted,
            fontSize: theme.typography.mobile.small,
            marginTop: theme.spacing.xs,
          }}
        >
          {t('emailForReceipt')}
        </p>
      </div>

      {/* Privacy Notice */}
      <div
        style={{
          backgroundColor: theme.colors.background,
          border: `1px solid ${theme.colors.border}`,
          borderRadius: theme.borderRadius.lg,
          padding: theme.spacing.md,
          fontSize: theme.typography.mobile.small,
          color: theme.colors.text.secondary,
        }}
      >
        <p style={{ fontWeight: '700', marginBottom: theme.spacing.xs }}>
          {t('privacyNotice')}
        </p>
        <p style={{ margin: 0, lineHeight: '1.5' }}>{t('guestCheckoutInfo')}</p>
      </div>
    </div>
  );
}
