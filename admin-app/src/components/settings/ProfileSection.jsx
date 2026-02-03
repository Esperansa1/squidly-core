import { useState, useRef } from 'react';
import { UserCircleIcon } from '@heroicons/react/24/outline';
import { Card, FormField, Button, Toast } from '../ui/index';
import { DEFAULT_THEME } from '../../config/theme.js';
import api from '../../services/api.js';

const ProfileSection = ({ currentUser, onUpdate }) => {
  const theme = DEFAULT_THEME;
  const [formData, setFormData] = useState({
    display_name: currentUser?.display_name || '',
    first_name: currentUser?.first_name || '',
    last_name: currentUser?.last_name || '',
    email: currentUser?.email || '',
    password: '',
  });
  const [errors, setErrors] = useState({});
  const [saving, setSaving] = useState(false);
  const [uploadingAvatar, setUploadingAvatar] = useState(false);
  const [showToast, setShowToast] = useState(false);
  const [toastMessage, setToastMessage] = useState('');
  const [toastType, setToastType] = useState('success');
  const fileInputRef = useRef(null);

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData((prev) => ({ ...prev, [name]: value }));
    if (errors[name]) {
      setErrors((prev) => ({ ...prev, [name]: null }));
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    const newErrors = {};

    if (!formData.display_name.trim()) {
      newErrors.display_name = 'שם התצוגה הוא שדה חובה';
    }

    if (!formData.email.trim() || !isValidEmail(formData.email)) {
      newErrors.email = 'אימייל לא תקין';
    }

    if (formData.password.trim() && formData.password.length < 8) {
      newErrors.password = 'הסיסמה חייבת להכיל לפחות 8 תווים';
    }

    if (Object.keys(newErrors).length > 0) {
      setErrors(newErrors);
      return;
    }

    try {
      setSaving(true);

      const updateData = {
        display_name: formData.display_name,
        first_name: formData.first_name,
        last_name: formData.last_name,
        email: formData.email,
      };

      if (formData.password.trim()) {
        updateData.password = formData.password;
      }

      await api.updateCurrentUser(updateData);
      displayToast('הפרופיל עודכן בהצלחה');
      setFormData((prev) => ({ ...prev, password: '' }));
      onUpdate();
    } catch (error) {
      console.error('Error updating profile:', error);
      displayToast('שגיאה בעדכון הפרופיל', 'error');
    } finally {
      setSaving(false);
    }
  };

  const handleAvatarClick = () => {
    fileInputRef.current?.click();
  };

  const handleAvatarUpload = async (event) => {
    const file = event.target.files?.[0];
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
      setUploadingAvatar(true);
      const avatarFormData = new FormData();
      avatarFormData.append('avatar', file);

      await api.uploadAvatar('me', avatarFormData);
      displayToast('תמונת הפרופיל עודכנה בהצלחה');
      onUpdate();
    } catch (error) {
      console.error('Error uploading avatar:', error);
      displayToast('שגיאה בהעלאת תמונת הפרופיל', 'error');
    } finally {
      setUploadingAvatar(false);
      if (fileInputRef.current) {
        fileInputRef.current.value = '';
      }
    }
  };

  const displayToast = (message, type = 'success') => {
    setToastMessage(message);
    setToastType(type);
    setShowToast(true);
  };

  const isValidEmail = (email) => {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  };

  if (!currentUser) {
    return (
      <div className="flex items-center justify-center h-full">
        <div className="text-gray-500">טוען...</div>
      </div>
    );
  }

  return (
    <div className="max-w-3xl mx-auto mt-6">
      <Card className="p-6">
        <h2 className="text-xl font-semibold mb-4" style={{ color: theme.text_primary }}>
          הפרופיל שלי
        </h2>

        <form onSubmit={handleSubmit} className="space-y-4">
          {/* Avatar + Display Name Row */}
          <div className="flex items-start gap-4">
            <button
              type="button"
              onClick={handleAvatarClick}
              disabled={uploadingAvatar}
              className="group relative flex-shrink-0"
            >
              {currentUser.avatar_url ? (
                <img
                  src={currentUser.avatar_url}
                  alt="Profile"
                  className="w-16 h-16 rounded-full object-cover ring-2 ring-gray-200 group-hover:ring-blue-500 transition-all"
                />
              ) : (
                <div className="w-16 h-16 rounded-full bg-gray-200 flex items-center justify-center ring-2 ring-gray-200 group-hover:ring-blue-500 transition-all">
                  <UserCircleIcon className="w-10 h-10 text-gray-400" />
                </div>
              )}
              {uploadingAvatar && (
                <div className="absolute inset-0 flex items-center justify-center bg-black bg-opacity-50 rounded-full">
                  <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-white"></div>
                </div>
              )}
              <div className="absolute inset-0 flex items-center justify-center bg-black bg-opacity-0 group-hover:bg-opacity-30 rounded-full transition-all">
                <span className="text-white opacity-0 group-hover:opacity-100 text-xs font-medium">
                  שנה
                </span>
              </div>
            </button>
            <input
              ref={fileInputRef}
              type="file"
              accept="image/*"
              onChange={handleAvatarUpload}
              className="hidden"
            />
            <div className="flex-1">
              <FormField
                label="שם תצוגה"
                fieldType="input"
                type="text"
                name="display_name"
                value={formData.display_name}
                onChange={handleChange}
                required
                error={errors.display_name}
                fullWidth
              />
            </div>
          </div>

          {/* 2-Column Grid for Fields */}
          <div className="grid grid-cols-2 gap-3">
            {/* Username (Read-only) */}
            <FormField
              label="שם משתמש"
              fieldType="input"
              type="text"
              name="username"
              value={currentUser.username}
              disabled
              fullWidth
            />

            {/* Email */}
            <FormField
              label="אימייל"
              fieldType="input"
              type="email"
              name="email"
              value={formData.email}
              onChange={handleChange}
              required
              error={errors.email}
              fullWidth
            />

            {/* First Name */}
            <FormField
              label="שם פרטי"
              fieldType="input"
              type="text"
              name="first_name"
              value={formData.first_name}
              onChange={handleChange}
              fullWidth
            />

            {/* Last Name */}
            <FormField
              label="שם משפחה"
              fieldType="input"
              type="text"
              name="last_name"
              value={formData.last_name}
              onChange={handleChange}
              fullWidth
            />
          </div>

          {/* Password */}
          <FormField
            label="סיסמה חדשה"
            fieldType="input"
            type="password"
            name="password"
            value={formData.password}
            onChange={handleChange}
            placeholder="השאר ריק כדי לא לשנות"
            error={errors.password}
            helpText="אופציונלי - מינימום 8 תווים"
            fullWidth
          />

          {/* Submit Button */}
          <div className="flex justify-end pt-2">
            <Button
              type="submit"
              variant="primary"
              loading={saving}
              disabled={saving}
            >
              שמור שינויים
            </Button>
          </div>
        </form>
      </Card>

      {showToast && (
        <Toast
          message={toastMessage}
          type={toastType}
          onClose={() => setShowToast(false)}
        />
      )}
    </div>
  );
};

export default ProfileSection;
