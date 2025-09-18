import React from 'react';
import { PlusIcon, PencilIcon, TrashIcon } from '@heroicons/react/24/outline';
import { DEFAULT_THEME } from '../../config/theme.js';

const ActionButton = ({ icon: Icon, variant, onClick, disabled = false, title }) => {
  const theme = DEFAULT_THEME;
  
  const getVariantStyles = () => {
    switch (variant) {
      case 'primary':
        return {
          backgroundColor: disabled ? theme.bg_gray_100 : theme.primary_color,
          color: disabled ? theme.text_disabled : theme.bg_white,
          borderColor: disabled ? theme.border_light : theme.primary_color
        };
      case 'secondary':
        return {
          backgroundColor: disabled ? theme.bg_gray_50 : theme.bg_white,
          color: disabled ? theme.text_disabled : theme.text_primary,
          borderColor: disabled ? theme.border_light : theme.border_color
        };
      case 'error':
        return {
          backgroundColor: disabled ? theme.bg_gray_100 : theme.danger_color,
          color: disabled ? theme.text_disabled : theme.bg_white,
          borderColor: disabled ? theme.border_light : theme.danger_color
        };
      default:
        return {
          backgroundColor: theme.bg_white,
          color: theme.text_primary,
          borderColor: theme.border_color
        };
    }
  };

  const styles = getVariantStyles();

  return (
    <button
      onClick={onClick}
      disabled={disabled}
      title={title}
      className={`inline-flex items-center justify-center w-8 h-8 border rounded-md transition-all duration-200 ${
        disabled ? 'cursor-not-allowed' : 'cursor-pointer hover:opacity-90'
      }`}
      style={styles}
    >
      <Icon className="w-4 h-4" />
    </button>
  );
};

const TableHeader = ({ 
  title, 
  onCreateClick, 
  onEditClick, 
  onDeleteClick, 
  hasSelectedItem = false,
  strings = {} 
}) => {
  return (
    <div className="flex justify-between items-center mb-4">
      <div className="flex gap-2">
        <ActionButton
          icon={TrashIcon}
          variant="error"
          disabled={!hasSelectedItem}
          onClick={onDeleteClick}
          title={strings.delete || 'מחק'}
        />
        <ActionButton
          icon={PencilIcon}
          variant="secondary"
          disabled={!hasSelectedItem}
          onClick={onEditClick}
          title={strings.edit || 'ערוך'}
        />
        <ActionButton
          icon={PlusIcon}
          variant="primary"
          onClick={onCreateClick}
          title={strings.create || 'צור חדש'}
        />
      </div>
      <h2 className="text-lg text-neutral-800 font-bold">{title}</h2>
    </div>
  );
};

export default TableHeader;