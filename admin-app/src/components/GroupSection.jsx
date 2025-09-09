import React, { useState } from 'react';
import { ChevronUpIcon, ChevronDownIcon, PlusIcon, PencilIcon, TrashIcon } from '@heroicons/react/24/outline';
import { Card, ActionButton, GroupModal } from './ui';
import { DEFAULT_THEME } from '../config/theme.js';

const GroupSection = ({ 
  title, 
  groups, 
  selectedGroup, 
  setSelectedGroup, 
  type,
  strings,
  sortField,
  sortDirection,
  onSort,
  onDeleteGroup,
  loading,
  error,
  branches = [],
  onGroupChange = () => {} // Called when groups are created/updated/deleted
}) => {
  const theme = DEFAULT_THEME;
  
  // Modal state management
  const [modalState, setModalState] = useState({
    isOpen: false,
    mode: 'create', // 'create' | 'edit' | 'delete'
    selectedGroupData: null
  });

  const openModal = (mode, groupData = null) => {
    setModalState({
      isOpen: true,
      mode,
      selectedGroupData: groupData
    });
  };

  const openDeleteModal = (groupData) => {
    openModal('delete', groupData);
  };

  const closeModal = () => {
    setModalState({
      isOpen: false,
      mode: 'create',
      selectedGroupData: null
    });
  };

  const handleModalSuccess = () => {
    // Refresh the groups data
    onGroupChange();
    // Clear selection if we were editing/deleting the selected group
    if (modalState.mode === 'delete' || 
        (modalState.mode === 'edit' && selectedGroup?.id === modalState.selectedGroupData?.id)) {
      setSelectedGroup(null);
    }
  };

  const SortableHeader = ({ field, label, sortField, sortDirection, onSort }) => {
    const isActive = sortField === field;
    
    return (
      <button
        onClick={() => onSort(field)}
        className="btn-reset flex items-center gap-1 text-right hover:text-gray-900 transition-colors cursor-pointer"
      >
        <span className="text-sm text-gray-700" style={{ fontWeight: 600 }}>
          {label}
        </span>
        {isActive && (
          <div className="flex items-center">
            {sortDirection === 'asc' ? (
              <ChevronUpIcon className="w-3 h-3 text-gray-900" />
            ) : (
              <ChevronDownIcon className="w-3 h-3 text-gray-900" />
            )}
          </div>
        )}
      </button>
    );
  };

  const StatusIndicator = ({ status }) => (
    <div className="flex items-center gap-2 rtl:gap-2">
      <div 
        className={`w-2 h-2 rounded-full ${
          status === 'active' ? 'bg-green-500' : 'bg-red-500'
        }`}
      />
      <span className="text-sm text-gray-600">
        {status === 'active' ? (strings.active || 'פעילה') : (strings.inactive || 'לא פעילה')}
      </span>
    </div>
  );

  return (
    <Card className="h-full flex flex-col" padding="none">
      {/* Section Header - Fixed */}
      <div className="flex-shrink-0 p-6 border-b border-gray-200">
        <div className="flex justify-between items-center mb-4">
          <h2 className="text-lg text-neutral-800 font-bold">{title}</h2>
          <div className="flex gap-2 rtl:flex-row-reverse">
            <ActionButton 
              icon={PlusIcon} 
              variant="primary" 
              onClick={() => openModal('create')}
              title={strings.create_group || 'צור קבוצה חדשה'}
            />
            <ActionButton 
              icon={PencilIcon} 
              variant="secondary"
              disabled={!selectedGroup}
              onClick={() => {
                const groupData = groups.find(g => g.id === selectedGroup);
                if (groupData) {
                  openModal('edit', groupData);
                }
              }}
              title={strings.edit_group || 'ערוך קבוצה'}
            />
            <ActionButton 
              icon={TrashIcon} 
              variant="error"
              disabled={!selectedGroup}
              onClick={() => {
                const groupData = groups.find(g => g.id === selectedGroup);
                if (groupData) {
                  openDeleteModal(groupData);
                }
              }}
              title={strings.delete_group || 'מחק קבוצה'}
            />
          </div>
        </div>

        {/* Table Header with Sortable Columns */}
        <div className="grid grid-cols-12 gap-4 pb-2">
          <div className="col-span-6 text-right">
            <SortableHeader
              field="name"
              label={strings.group_name || 'שם הקבוצה'}
              sortField={sortField}
              sortDirection={sortDirection}
              onSort={onSort}
            />
          </div>
          <div className="col-span-5 text-right">
            <SortableHeader
              field="status"
              label={strings.group_status || 'סטטוס הקבוצה'}
              sortField={sortField}
              sortDirection={sortDirection}
              onSort={onSort}
            />
          </div>
          <div className="col-span-1"></div>
        </div>
      </div>

      {/* Table Rows - Scrollable */}
      <div className="flex-1 p-6 pt-4 overflow-y-auto">
        {loading ? (
          <div className="flex items-center justify-center h-full text-gray-500">
            <div className="text-center">
              <div className="w-8 h-8 border-4 border-gray-300 border-t-red-600 rounded-full animate-spin mx-auto mb-4"></div>
              <p>טוען נתונים...</p>
            </div>
          </div>
        ) : error ? (
          <div className="flex items-center justify-center h-full text-red-500">
            <div className="text-center">
              <div className="text-red-600 text-xl mb-4">⚠️</div>
              <p className="text-red-600 mb-4">שגיאה בטעינה: {error}</p>
              <button 
                onClick={() => window.location.reload()} 
                className="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700"
              >
                טען מחדש
              </button>
            </div>
          </div>
        ) : groups.length === 0 ? (
          <div className="flex items-center justify-center h-full text-gray-500">
            <div className="text-center">
              <div className="text-4xl mb-4">📋</div>
              <p>אין קבוצות להצגה</p>
            </div>
          </div>
        ) : (
          <div className="space-y-3">
            {groups.map((group) => (
              <div 
                key={group.id}
                className="grid grid-cols-12 gap-4 py-3 hover:bg-gray-50 rounded-lg px-2 transition-colors cursor-pointer"
                onClick={() => setSelectedGroup(group.id)}
              >
                <div className="col-span-6 text-right">
                  <span className="text-sm text-gray-800">{group.name}</span>
                </div>
                <div className="col-span-5 text-right">
                  <StatusIndicator status={group.status} />
                </div>
                <div className="col-span-1 flex justify-center">
                  {/* Radio button using theme colors */}
                  <div
                    onClick={() => setSelectedGroup(group.id)}
                    className="w-4 h-4 rounded-full border-2 cursor-pointer transition-all flex items-center justify-center"
                    style={{
                      borderColor: selectedGroup === group.id ? theme.primary_color : theme.border_light,
                      backgroundColor: selectedGroup === group.id ? theme.primary_color : theme.bg_white
                    }}
                  >
                    {/* White dot when selected */}
                    {selectedGroup === group.id && (
                      <div className="w-1.5 h-1.5 rounded-full bg-white" />
                    )}
                  </div>
                  {/* Hidden native radio for form functionality */}
                  <input
                    type="radio"
                    name={`${type}-selection`}
                    value={group.id}
                    checked={selectedGroup === group.id}
                    onChange={() => setSelectedGroup(group.id)}
                    style={{ display: 'none' }}
                  />
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Group Modal */}
      <GroupModal
        isOpen={modalState.isOpen}
        onClose={closeModal}
        mode={modalState.mode}
        groupType={type}
        initialData={modalState.selectedGroupData}
        branches={branches}
        categories={[]} // TODO: Pass actual categories
        groups={groups} // Pass all groups for selection
        onSuccess={handleModalSuccess}
        strings={strings}
      />
    </Card>
  );
};

export default GroupSection;