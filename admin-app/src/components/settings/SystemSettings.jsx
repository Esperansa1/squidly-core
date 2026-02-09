import { useState, useEffect } from 'react';
import { FormField, Button, Divider } from '../ui';
import api from '../../services/api';

/**
 * SystemSettings - Configure system-wide settings
 * VAT rate, currency, and other global options
 */
const SystemSettings = () => {
  const [settings, setSettings] = useState({
    vat_rate: 0.18,
    currency: 'ILS',
    currency_symbol: '₪',
  });
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState(null);

  useEffect(() => {
    loadSettings();
  }, []);

  const loadSettings = async () => {
    try {
      setLoading(true);
      const config = await api.getAdminConfig();
      setSettings({
        vat_rate: config.taxes?.vat_rate ?? 0.18,
        currency: config.currency?.code ?? 'ILS',
        currency_symbol: config.currency?.symbol ?? '₪',
      });
    } catch (error) {
      console.error('Error loading settings:', error);
      setMessage({ type: 'error', text: 'שגיאה בטעינת הגדרות' });
    } finally {
      setLoading(false);
    }
  };

  const handleChange = (field, value) => {
    setSettings(prev => ({
      ...prev,
      [field]: value,
    }));
  };

  const handleSave = async () => {
    try {
      setSaving(true);
      setMessage(null);

      await api.updateSystemSettings(settings);

      setMessage({ type: 'success', text: 'ההגדרות נשמרו בהצלחה' });

      // Reload settings to confirm
      setTimeout(() => {
        loadSettings();
        setMessage(null);
      }, 2000);
    } catch (error) {
      console.error('Error saving settings:', error);
      setMessage({ type: 'error', text: error.message || 'שגיאה בשמירת הגדרות' });
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-full">
        <div className="text-gray-500">טוען הגדרות...</div>
      </div>
    );
  }

  return (
    <div className="h-full overflow-y-auto">
      <div className="max-w-2xl mx-auto py-6">
        <h2 className="text-2xl font-bold mb-6">הגדרות מערכת</h2>

        {/* Message display */}
        {message && (
          <div
            className={`mb-6 p-4 rounded-lg ${
              message.type === 'success'
                ? 'bg-green-50 text-green-800 border border-green-200'
                : 'bg-red-50 text-red-800 border border-red-200'
            }`}
          >
            {message.text}
          </div>
        )}

        {/* VAT Settings Section */}
        <div className="bg-white rounded-lg shadow-sm border p-6 mb-6">
          <h3 className="text-lg font-bold mb-4">מע״מ ומיסים</h3>

          <FormField
            label="שיעור מע״מ"
            fieldType="input"
            type="number"
            step="0.01"
            min="0"
            max="1"
            name="vat_rate"
            value={settings.vat_rate}
            onChange={(e) => handleChange('vat_rate', parseFloat(e.target.value) || 0)}
            fullWidth
            helpText="הזן את שיעור המע״מ כמספר עשרוני (לדוגמה: 0.18 עבור 18%). הזן 0 אם המחירים כוללים מע״מ."
          />

          <div className="mt-4 p-4 bg-blue-50 rounded-lg text-sm text-blue-800">
            <p className="font-semibold mb-2">הסבר:</p>
            <ul className="list-disc list-inside space-y-1">
              <li>
                <strong>0.18</strong> = 18% מע״מ (ברירת מחדל)
              </li>
              <li>
                <strong>0.17</strong> = 17% מע״מ
              </li>
              <li>
                <strong>0</strong> = מחירים כוללים מע״מ (המע״מ לא יוצג בעגלה ובתשלום)
              </li>
            </ul>
            <p className="mt-2">
              מע״מ נוכחי: <strong>{(settings.vat_rate * 100).toFixed(0)}%</strong>
            </p>
          </div>
        </div>

        <Divider />

        {/* Currency Settings Section */}
        <div className="bg-white rounded-lg shadow-sm border p-6 mb-6">
          <h3 className="text-lg font-bold mb-4">מטבע</h3>

          <div className="grid grid-cols-2 gap-4">
            <FormField
              label="קוד מטבע"
              fieldType="input"
              type="text"
              name="currency"
              value={settings.currency}
              onChange={(e) => handleChange('currency', e.target.value)}
              fullWidth
              helpText="לדוגמה: ILS, USD, EUR"
            />

            <FormField
              label="סימן מטבע"
              fieldType="input"
              type="text"
              name="currency_symbol"
              value={settings.currency_symbol}
              onChange={(e) => handleChange('currency_symbol', e.target.value)}
              fullWidth
              helpText="לדוגמה: ₪, $, €"
            />
          </div>
        </div>

        {/* Save Button */}
        <div className="flex justify-end gap-4">
          <Button variant="secondary" onClick={loadSettings} disabled={saving}>
            ביטול
          </Button>
          <Button variant="primary" onClick={handleSave} loading={saving}>
            שמור שינויים
          </Button>
        </div>
      </div>
    </div>
  );
};

export default SystemSettings;
