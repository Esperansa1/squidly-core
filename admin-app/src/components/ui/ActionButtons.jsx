import React from 'react';
import { PlusIcon, PencilIcon, TrashIcon } from '@heroicons/react/24/outline';
import { DEFAULT_THEME } from '../../config/theme.js';

const ActionButtons = ({
  onAdd = null,
  onEdit = null,
  onDelete = null,
  addDisabled = false,
  editDisabled = false,
  deleteDisabled = false,
  addTooltip = 'הוסף',
  editTooltip = 'ערוך',
  deleteTooltip = 'מחק',
  className = ''
}) => {
  const theme = DEFAULT_THEME;

  const buttonClass = "flex items-center justify-center w-10 h-10 rounded-lg transition-colors hover:opacity-80 disabled:opacity-50 disabled:cursor-not-allowed";

  return (
    <div className={`flex items-center gap-2 ${className}`}>
      {/* Add Button */}
      {onAdd && (
        <button
          onClick={onAdd}
          disabled={addDisabled}
          className={buttonClass}
          style={{ backgroundColor: theme.primary_color, color: 'white' }}
          title={addTooltip}
        >
          <PlusIcon className="w-4 h-4" />
        </button>
      )}

      {/* Edit Button */}
      {onEdit && (
        <button
          onClick={onEdit}
          disabled={editDisabled}
          className={buttonClass}
          style={{
            color: theme.text_secondary,
            backgroundColor: theme.bg_gray_100
          }}
          title={editTooltip}
        >
          <PencilIcon className="w-4 h-4" />
        </button>
      )}

      {/* Delete Button */}
      {onDelete && (
        <button
          onClick={onDelete}
          disabled={deleteDisabled}
          className={buttonClass}
          style={{ backgroundColor: theme.danger_color, color: 'white' }}
          title={deleteTooltip}
        >
          <TrashIcon className="w-4 h-4" />
        </button>
      )}
    </div>
  );
};

export default ActionButtons;