import { useState, useEffect, useRef } from 'react';
import { PhotoIcon } from '@heroicons/react/24/outline';
import { Card, FormField, Button, Toast } from '../ui/index';
import { DEFAULT_THEME, generateCSSVariables } from '../../config/theme.js';
import api from '../../services/api.js';

const applyTheme = (theme) => {
  const cssVars = generateCSSVariables({ ...DEFAULT_THEME, ...theme });
  const root = document.documentElement;
  Object.entries(cssVars).forEach(([prop, val]) => root.style.setProperty(prop, val));
};

const AppearanceSection = () => {
  const theme = DEFAULT_THEME;
  const [formData, setFormData] = useState({
    restaurant_name: '',
    primary_color: '#D12525',
    secondary_color: '#F2F2F2',
    accent_color: '#D12525',
    logo_url: '',
  });
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [uploadingLogo, setUploadingLogo] = useState(false);
  const [showToast, setShowToast] = useState(false);
  const [toastMessage, setToastMessage] = useState('');
  const [toastType, setToastType] = useState('success');
  const fileInputRef = useRef(null);

  useEffect(() => {
    api.getSettings()
      .then((data) => setFormData(data))
      .catch(() => displayToast('שגיאה בטעינת הגדרות המראה', 'error'))
      .finally(() => setLoading(false));
  }, []);

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData((prev) => ({ ...prev, [name]: value }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    try {
      setSaving(true);
      await api.updateSettings({
        restaurant_name: formData.restaurant_name,
        primary_color:   formData.primary_color,
        secondary_color: formData.secondary_color,
        accent_color:    formData.accent_color,
        logo_url:        formData.logo_url,
      });
      applyTheme(formData);
      displayToast('הגדרות המראה נשמרו בהצלחה');
    } catch (error) {
      console.error('Error saving appearance settings:', error);
      displayToast('שגיאה בשמירת הגדרות', 'error');
    } finally {
      setSaving(false);
    }
  };

  const handleLogoClick = () => fileInputRef.current?.click();

  const handleLogoUpload = async (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    if (!file.type.startsWith('image/')) {
      displayToast('יש להעלות קובץ תמונה בלבד', 'error');
      return;
    }
    if (file.size > 2 * 1024 * 1024) {
      displayToast('גודל הקובץ לא יכול לעבור 2MB', 'error');
      return;
    }
    try {
      setUploadingLogo(true);
      const result = await api.uploadLogo(file);
      setFormData((prev) => ({ ...prev, logo_url: result.logo_url }));
      displayToast('הלוגו עודכן בהצלחה');
    } catch (error) {
      console.error('Error uploading logo:', error);
      displayToast('שגיאה בהעלאת הלוגו', 'error');
    } finally {
      setUploadingLogo(false);
      if (fileInputRef.current) fileInputRef.current.value = '';
    }
  };

  const displayToast = (message, type = 'success') => {
    setToastMessage(message);
    setToastType(type);
    setShowToast(true);
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-full">
        <div className="text-gray-500">טוען...</div>
      </div>
    );
  }

  return (
    <div className="max-w-3xl mx-auto mt-6 space-y-4">
      <Card className="p-6">
        <h2 className="text-xl font-semibold mb-4" style={{ color: theme.text_primary }}>
          מראה המסעדה
        </h2>
        <form onSubmit={handleSubmit} className="space-y-4">
          <FormField
            label="שם המסעדה"
            fieldType="input"
            type="text"
            name="restaurant_name"
            value={formData.restaurant_name}
            onChange={handleChange}
            fullWidth
          />

          <div className="grid grid-cols-3 gap-3">
            <div className="flex flex-col gap-1">
              <label className="text-sm font-medium" style={{ color: theme.text_primary }}>
                צבע ראשי
              </label>
              <input
                type="color"
                name="primary_color"
                value={formData.primary_color}
                onChange={handleChange}
                className="h-10 w-full rounded border border-gray-300 cursor-pointer p-0.5"
              />
            </div>
            <div className="flex flex-col gap-1">
              <label className="text-sm font-medium" style={{ color: theme.text_primary }}>
                צבע משני
              </label>
              <input
                type="color"
                name="secondary_color"
                value={formData.secondary_color}
                onChange={handleChange}
                className="h-10 w-full rounded border border-gray-300 cursor-pointer p-0.5"
              />
            </div>
            <div className="flex flex-col gap-1">
              <label className="text-sm font-medium" style={{ color: theme.text_primary }}>
                צבע הדגשה
              </label>
              <input
                type="color"
                name="accent_color"
                value={formData.accent_color}
                onChange={handleChange}
                className="h-10 w-full rounded border border-gray-300 cursor-pointer p-0.5"
              />
            </div>
          </div>

          <div className="flex flex-col gap-2">
            <label className="text-sm font-medium" style={{ color: theme.text_primary }}>
              לוגו המסעדה
            </label>
            <div className="flex items-center gap-4">
              <button
                type="button"
                onClick={handleLogoClick}
                disabled={uploadingLogo}
                className="relative w-20 h-20 rounded-lg border-2 border-dashed border-gray-300 flex items-center justify-center hover:border-gray-400 transition-colors overflow-hidden"
              >
                {formData.logo_url ? (
                  <img src={formData.logo_url} alt="לוגו" className="w-full h-full object-cover" />
                ) : (
                  <PhotoIcon className="w-8 h-8 text-gray-400" />
                )}
                {uploadingLogo && (
                  <div className="absolute inset-0 flex items-center justify-center bg-black bg-opacity-50">
                    <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-white" />
                  </div>
                )}
              </button>
              <div className="text-sm text-gray-500">
                <p>לחץ להעלאת לוגו</p>
                <p>PNG, JPG עד 2MB</p>
              </div>
            </div>
            <input
              ref={fileInputRef}
              type="file"
              accept="image/*"
              onChange={handleLogoUpload}
              className="hidden"
            />
          </div>

          <div className="flex justify-end pt-2">
            <Button type="submit" variant="primary" loading={saving} disabled={saving}>
              שמור שינויים
            </Button>
          </div>
        </form>
      </Card>

      {showToast && (
        <Toast message={toastMessage} type={toastType} onClose={() => setShowToast(false)} />
      )}
    </div>
  );
};

export default AppearanceSection;
