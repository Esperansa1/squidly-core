/**
 * UserModal Component
 *
 * Modal for creating and editing admin users with full form validation
 */

import { useState, useRef } from 'react';
import UserCircleIcon from '@heroicons/react/24/outline/UserCircleIcon';
import KeyIcon from '@heroicons/react/24/outline/KeyIcon';
import { FormField, Button, Modal, IconButton } from './index';
import DropdownButton from './DropdownButton.jsx';
import { DEFAULT_THEME } from '../../config/theme.js';
import api from '../../services/api.js';

const UserModal = ({ user, onSave, onClose }) => {
  const theme = DEFAULT_THEME;
  const isEditMode = !!user;
  const [formData, setFormData] = useState({
    username: user?.username || '',
    display_name: user?.display_name || '',
    first_name: user?.first_name || '',
    last_name: user?.last_name || '',
    email: user?.email || '',
    role: user?.role || 'restaurant_staff',
    password: '',
  });
  const [avatarFile, setAvatarFile] = useState(null);
  const [avatarPreview, setAvatarPreview] = useState(user?.avatar_url || null);
  const [errors, setErrors] = useState({});
  const [saving, setSaving] = useState(false);
  const fileInputRef = useRef(null);

  const roleOptions = [
    { value: 'administrator', label: 'מנהל' },
    { value: 'restaurant_manager', label: 'מנהל מסעדה' },
    { value: 'restaurant_staff', label: 'צוות' }
  ];

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData((prev) => ({ ...prev, [name]: value }));
    if (errors[name]) {
      setErrors((prev) => ({ ...prev, [name]: null }));
    }
  };

  const handleAvatarClick = () => {
    fileInputRef.current?.click();
  };

  const handleAvatarChange = (e) => {
    const file = e.target.files?.[0];
    if (!file) return;

    if (!file.type.startsWith('image/')) {
      setErrors((prev) => ({ ...prev, avatar: 'יש להעלות קובץ תמונה בלבד' }));
      return;
    }

    if (file.size > 2 * 1024 * 1024) {
      setErrors((prev) => ({ ...prev, avatar: 'גודל הקובץ לא יכול לעבור 2MB' }));
      return;
    }

    setAvatarFile(file);
    const reader = new FileReader();
    reader.onloadend = () => {
      setAvatarPreview(reader.result);
    };
    reader.readAsDataURL(file);
    setErrors((prev) => ({ ...prev, avatar: null }));
  };

  const generatePassword = () => {
    const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    let password = '';
    for (let i = 0; i < 12; i++) {
      password += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    setFormData((prev) => ({ ...prev, password }));
  };

  const validate = () => {
    const newErrors = {};

    if (!isEditMode) {
      if (!formData.username.trim()) {
        newErrors.username = 'שם משתמש הוא שדה חובה';
      } else if (!/^[a-zA-Z0-9_]{3,20}$/.test(formData.username)) {
        newErrors.username = 'שם משתמש חייב להכיל 3-20 תווים (אותיות, מספרים וקו תחתון בלבד)';
      }

      if (!formData.password.trim()) {
        newErrors.password = 'סיסמה היא שדה חובה למשתמש חדש';
      } else if (formData.password.length < 8) {
        newErrors.password = 'הסיסמה חייבת להכיל לפחות 8 תווים';
      }
    } else {
      if (formData.password.trim() && formData.password.length < 8) {
        newErrors.password = 'הסיסמה חייבת להכיל לפחות 8 תווים';
      }
    }

    if (!formData.display_name.trim()) {
      newErrors.display_name = 'שם תצוגה הוא שדה חובה';
    }

    if (!formData.email.trim()) {
      newErrors.email = 'אימייל הוא שדה חובה';
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) {
      newErrors.email = 'אימייל לא תקין';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e) => {
    e.preventDefault();

    if (!validate()) {
      return;
    }

    try {
      setSaving(true);

      const userData = {
        display_name: formData.display_name,
        first_name: formData.first_name,
        last_name: formData.last_name,
        email: formData.email,
        role: formData.role,
      };

      if (!isEditMode) {
        userData.username = formData.username;
        userData.password = formData.password;
      } else if (formData.password.trim()) {
        userData.password = formData.password;
      }

      await onSave(userData);

      // Upload avatar if changed
      if (avatarFile && user?.id) {
        const avatarFormData = new FormData();
        avatarFormData.append('avatar', avatarFile);
        await api.uploadAvatar(user.id, avatarFormData);
      }
    } catch (error) {
      console.error('Error saving user:', error);
      const errorMessage = error.message || 'שגיאה בשמירת המשתמש';
      if (errorMessage.includes('username_exists')) {
        setErrors((prev) => ({ ...prev, username: 'שם משתמש כבר קיים במערכת' }));
      } else if (errorMessage.includes('email_exists')) {
        setErrors((prev) => ({ ...prev, email: 'אימייל כבר קיים במערכת' }));
      } else {
        setErrors((prev) => ({ ...prev, general: errorMessage }));
      }
    } finally {
      setSaving(false);
    }
  };

  return (
    <Modal
      isOpen={true}
      onClose={onClose}
      title={isEditMode ? 'עריכת משתמש' : 'הוספת משתמש'}
    >
      <form onSubmit={handleSubmit} className="space-y-4">
        {errors.general && (
          <div className="bg-red-50 border border-red-200 text-red-700 px-3 py-2 rounded-lg text-sm">
            {errors.general}
          </div>
        )}

        {/* Avatar + Display Name Row */}
        <div className="flex items-start gap-4">
          <button
            type="button"
            onClick={handleAvatarClick}
            className="group relative flex-shrink-0"
          >
            {avatarPreview ? (
              <img
                src={avatarPreview}
                alt="Avatar"
                className="w-16 h-16 rounded-full object-cover ring-2 ring-gray-200 group-hover:ring-blue-500 transition-all"
              />
            ) : (
              <div className="w-16 h-16 rounded-full bg-gray-200 flex items-center justify-center ring-2 ring-gray-200 group-hover:ring-blue-500 transition-all">
                <UserCircleIcon className="w-10 h-10 text-gray-400" />
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
            onChange={handleAvatarChange}
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
            {errors.avatar && (
              <p className="text-xs mt-1" style={{ color: theme.danger_color }}>{errors.avatar}</p>
            )}
          </div>
        </div>

        {/* 2-Column Grid for Main Fields */}
        <div className="grid grid-cols-2 gap-3">
          {/* Username (only for new users) */}
          {!isEditMode && (
            <FormField
              label="שם משתמש"
              fieldType="input"
              type="text"
              name="username"
              value={formData.username}
              onChange={handleChange}
              required
              error={errors.username}
              fullWidth
            />
          )}

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

          {/* Role */}
          <div className={isEditMode ? '' : ''}>
            <label className="block text-sm font-medium mb-1" style={{ color: theme.text_primary }}>
              תפקיד <span style={{ color: theme.danger_color }}>*</span>
            </label>
            <DropdownButton
              options={roleOptions}
              value={formData.role}
              onChange={(value) => setFormData((prev) => ({ ...prev, role: value }))}
              placeholder="בחר תפקיד..."
              getOptionLabel={(option) => option.label}
              getOptionValue={(option) => option.value}
            />
          </div>

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

        {/* Password Row */}
        <div>
          <label className="block text-sm font-medium mb-1" style={{ color: theme.text_primary }}>
            סיסמה {!isEditMode && <span style={{ color: theme.danger_color }}>*</span>}
            {isEditMode && <span className="text-xs" style={{ color: theme.text_muted }}> (השאר ריק כדי לא לשנות)</span>}
          </label>
          <div className="flex gap-2">
            <div className="flex-1">
              <FormField
                fieldType="input"
                type="text"
                name="password"
                value={formData.password}
                onChange={handleChange}
                placeholder={isEditMode ? 'השאר ריק כדי לא לשנות' : ''}
                error={errors.password}
                fullWidth
              />
            </div>
            <IconButton
              icon={KeyIcon}
              onClick={generatePassword}
              variant="outline"
              size="md"
              tooltip="צור סיסמה"
            />
          </div>
        </div>

        {/* Action Buttons */}
        <div className="flex justify-end gap-3 pt-2">
          <Button
            variant="outline"
            onClick={onClose}
            disabled={saving}
          >
            ביטול
          </Button>
          <Button
            type="submit"
            variant="primary"
            loading={saving}
            disabled={saving}
          >
            {isEditMode ? 'עדכן' : 'הוסף'}
          </Button>
        </div>
      </form>
    </Modal>
  );
};

export default UserModal;
