import React from 'react';
import { t } from '../../i18n/translations';

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

  return (
    <div className="space-y-6">
      <h2 className="text-2xl font-bold mb-4">{t('customerInfo')}</h2>

      <p className="text-gray-600">{t('enterYourDetails')}</p>

      {/* First Name */}
      <div>
        <label className="block font-bold mb-2">
          {t('firstName')} <span className="text-red-600">*</span>
        </label>
        <input
          type="text"
          value={data.firstName}
          onChange={(e) => handleChange('firstName', e.target.value)}
          className="w-full border px-4 py-2 focus:outline-none focus:border-blue-500"
          placeholder={t('enterFirstName')}
          required
        />
      </div>

      {/* Last Name */}
      <div>
        <label className="block font-bold mb-2">
          {t('lastName')} <span className="text-red-600">*</span>
        </label>
        <input
          type="text"
          value={data.lastName}
          onChange={(e) => handleChange('lastName', e.target.value)}
          className="w-full border px-4 py-2 focus:outline-none focus:border-blue-500"
          placeholder={t('enterLastName')}
          required
        />
      </div>

      {/* Phone */}
      <div>
        <label className="block font-bold mb-2">
          {t('phone')} <span className="text-red-600">*</span>
        </label>
        <input
          type="tel"
          value={data.phone}
          onChange={(e) => handleChange('phone', e.target.value)}
          className={`w-full border px-4 py-2 focus:outline-none ${
            !isPhoneValid ? 'border-red-500' : 'focus:border-blue-500'
          }`}
          placeholder="+972501234567"
          required
        />
        {!isPhoneValid && (
          <p className="text-red-600 text-sm mt-1">{t('invalidPhone')}</p>
        )}
        <p className="text-gray-500 text-sm mt-1">{t('phoneUsedForOrderUpdates')}</p>
      </div>

      {/* Email (Optional) */}
      <div>
        <label className="block font-bold mb-2">{t('email')} ({t('optional')})</label>
        <input
          type="email"
          value={data.email}
          onChange={(e) => handleChange('email', e.target.value)}
          className={`w-full border px-4 py-2 focus:outline-none ${
            !isEmailValid ? 'border-red-500' : 'focus:border-blue-500'
          }`}
          placeholder="email@example.com"
        />
        {!isEmailValid && (
          <p className="text-red-600 text-sm mt-1">{t('invalidEmail')}</p>
        )}
        <p className="text-gray-500 text-sm mt-1">{t('emailForReceipt')}</p>
      </div>

      {/* Privacy Notice */}
      <div className="bg-gray-50 border p-4 text-sm text-gray-700">
        <p className="font-bold mb-2">{t('privacyNotice')}</p>
        <p>{t('guestCheckoutInfo')}</p>
      </div>
    </div>
  );
}
