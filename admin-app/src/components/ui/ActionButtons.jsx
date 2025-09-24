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

  const buttonClass = "flex items-center justify-center w-8 h-8 rounded-md border transition-colors hover:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed";

  return (
    <div className={`flex items-center gap-2 ${className}`}>
      {/* Delete Button - First to match TableHeader order */}
      {onDelete && (
        <button
          onClick={onDelete}
          disabled={deleteDisabled}
          className={buttonClass}
          style={{
            backgroundColor: theme.danger_color,
            color: 'white',
            borderColor: theme.danger_color
          }}
          title={deleteTooltip}
        >
          <TrashIcon className="w-4 h-4" />
        </button>
      )}

      {/* Edit Button - Second to match TableHeader order */}
      {onEdit && (
        <button
          onClick={onEdit}
          disabled={editDisabled}
          className={buttonClass}
          style={{
            backgroundColor: theme.bg_white,
            color: theme.text_primary,
            borderColor: theme.border_color
          }}
          title={editTooltip}
        >
          <PencilIcon className="w-4 h-4" />
        </button>
      )}

      {/* Add Button - Last to match TableHeader order */}
      {onAdd && (
        <button
          onClick={onAdd}
          disabled={addDisabled}
          className={buttonClass}
          style={{
            backgroundColor: theme.primary_color,
            color: 'white',
            borderColor: theme.primary_color
          }}
          title={addTooltip}
        >
          <PlusIcon className="w-4 h-4" />
        </button>
      )}
    </div>
  );
};

export default ActionButtons;