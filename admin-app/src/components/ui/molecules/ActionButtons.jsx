/**
 * ActionButtons Component
 *
 * Group of action buttons for CED (Create/Edit/Delete) operations using ActionButton atoms
 */

import React from 'react';
import PlusIcon from '@heroicons/react/24/outline/PlusIcon';
import PencilIcon from '@heroicons/react/24/outline/PencilIcon';
import TrashIcon from '@heroicons/react/24/outline/TrashIcon';
import ActionButton from './ActionButton.jsx';

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
  size = 'md',
  className = ''
}) => {
  return (
    <div className={`flex items-center gap-2 ${className}`}>
      {/* Delete Button - First to match TableHeader order */}
      {onDelete && (
        <ActionButton
          icon={TrashIcon}
          variant="error"
          onClick={onDelete}
          disabled={deleteDisabled}
          tooltip={deleteTooltip}
          size={size}
        />
      )}

      {/* Edit Button - Second to match TableHeader order */}
      {onEdit && (
        <ActionButton
          icon={PencilIcon}
          variant="secondary"
          onClick={onEdit}
          disabled={editDisabled}
          tooltip={editTooltip}
          size={size}
        />
      )}

      {/* Add Button - Last to match TableHeader order */}
      {onAdd && (
        <ActionButton
          icon={PlusIcon}
          variant="primary"
          onClick={onAdd}
          disabled={addDisabled}
          tooltip={addTooltip}
          size={size}
        />
      )}
    </div>
  );
};

export default ActionButtons;