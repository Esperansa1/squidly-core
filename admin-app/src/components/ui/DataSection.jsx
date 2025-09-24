import React, { useState, useMemo, useEffect, useCallback, useRef } from 'react';
import { PlusIcon, PencilIcon, TrashIcon } from '@heroicons/react/24/outline';
import { Card, SearchBar, DataTable, ConfirmationModal, Toast } from './index';
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
      className={`inline-flex items-center justify-center w-10 h-10 border rounded-md transition-all duration-200 ${
        disabled ? 'cursor-not-allowed' : 'cursor-pointer hover:opacity-90'
      }`}
      style={styles}
    >
      <Icon className="w-4 h-4" />
    </button>
  );
};

const DataSection = ({
  title = '',
  data = [],
  selectedItem,
  setSelectedItem,
  strings = {},
  loading: externalLoading = false,
  error: externalError = null,
  branches = [],
  selectedBranchId = 0,
  onItemChange = () => {},
  columns = [],
  apiService = null,
  Modal = null,
  editingItemProp = 'editingItem',
  itemIdProp = 'id',
  itemNameProp = 'name',
  productGroups = []
}) => {
  const [searchTerm, setSearchTerm] = useState('');
  const [apiData, setApiData] = useState([]);
  const [apiLoading, setApiLoading] = useState(false);
  const [apiError, setApiError] = useState(null);
  const [lastFetchedBranchId, setLastFetchedBranchId] = useState(null);
  const branchDataCache = useRef(new Map());

  // Delete modal state
  const [showDeleteModal, setShowDeleteModal] = useState(false);
  const [isDeleting, setIsDeleting] = useState(false);

  // Create/Edit modal state
  const [showItemModal, setShowItemModal] = useState(false);
  const [editingItem, setEditingItem] = useState(null);
  const [isSaving, setIsSaving] = useState(false);

  // Toast state
  const [showToast, setShowToast] = useState(false);
  const [toastMessage, setToastMessage] = useState('');

  // Stable fetch function with useCallback
  const fetchData = useCallback(async (forceRefresh = false) => {
    // Skip if we already have data for this branch and it hasn't changed (unless forced)
    if (!forceRefresh && lastFetchedBranchId === selectedBranchId && branchDataCache.current.has(selectedBranchId)) {
      return;
    }

    if (data.length > 0 && !apiService) {
      // Use provided data only if no apiService is available
      setApiData(data);
      setLastFetchedBranchId(selectedBranchId);
      return;
    }

    // Check if we already have data for this branch in cache
    if (branchDataCache.current.has(selectedBranchId)) {
      const cachedData = branchDataCache.current.get(selectedBranchId);
      setApiData(cachedData);
      setLastFetchedBranchId(selectedBranchId);
      return;
    }

    if (!apiService) {
      return;
    }

    try {
      setApiLoading(true);
      setApiError(null);

      const filters = {};
      const response = await apiService.getAll(filters);

      // Cache the data for this branch
      branchDataCache.current.set(selectedBranchId, response);
      setApiData(response);
      setLastFetchedBranchId(selectedBranchId);
    } catch (error) {
      console.error(`Failed to fetch ${title.toLowerCase()}:`, error);
      // Show toast instead of setting error state
      setToastMessage(error.message || `שגיאה בטעינת ${title.toLowerCase()}`);
      setShowToast(true);
      // Don't clear existing data on error
    } finally {
      setApiLoading(false);
    }
  }, [selectedBranchId, data, lastFetchedBranchId, apiService, title]);

  // Fetch data from API only when branch actually changes
  useEffect(() => {
    fetchData();
  }, [fetchData]);

  // Delete functionality
  const handleDeleteClick = () => {
    if (selectedItem) {
      setShowDeleteModal(true);
    }
  };

  const handleDeleteConfirm = async () => {
    if (!selectedItem || !apiService) return;

    // Find the full item object from the selected ID
    const selectedItemData = filteredData.find(item => item[itemIdProp] === selectedItem);
    if (!selectedItemData) {
      console.error('Selected item not found in data');
      return;
    }

    try {
      setIsDeleting(true);
      // Extract ID from the selected item object
      const itemId = selectedItemData[itemIdProp];

      // Validate ID before calling API
      if (!itemId || itemId === 0) {
        throw new Error('מזהה פריט לא חוקי');
      }

      await apiService.delete(itemId, selectedItemData);

      // Clear selection
      setSelectedItem(null);

      // Clear cache and refetch data
      branchDataCache.current.clear();
      setLastFetchedBranchId(null);
      await fetchData(true); // Force refresh

      // Call change handler
      onItemChange();

      // Close modal
      setShowDeleteModal(false);
    } catch (error) {
      console.error(`Failed to delete ${title.toLowerCase()}:`, error);
      console.log('Error message received:', error.message);
      console.log('Error object:', error);

      // Provide user-friendly error messages
      let errorMessage = `שגיאה במחיקת ${title.toLowerCase()}`;

      if (error.message) {
        // If the error message is in Hebrew (from our API), use it directly
        if (error.message.includes('בשימוש על ידי') || error.message.includes('קבוצה זו')) {
          errorMessage = error.message;
        } else if (error.message.includes('Resource is in use by:')) {
          // Extract the dependent items from the English message
          const dependentItems = error.message.replace('Resource is in use by: ', '');
          errorMessage = `לא ניתן למחוק - ${title.toLowerCase()} זה בשימוש על ידי: ${dependentItems}`;
        } else if (error.message.includes('Resource is in use')) {
          errorMessage = `לא ניתן למחוק - ${title.toLowerCase()} זה בשימוש על ידי פריטים אחרים`;
        } else if (error.message.includes('not found')) {
          errorMessage = `${title} לא נמצא`;
        } else if (error.message.includes('network') || error.message.includes('fetch')) {
          errorMessage = 'שגיאת רשת - אנא בדוק את החיבור לאינטרנט';
        } else {
          errorMessage = error.message;
        }
      }

      setToastMessage(errorMessage);
      setShowToast(true);
    } finally {
      setIsDeleting(false);
    }
  };

  const handleDeleteCancel = () => {
    setShowDeleteModal(false);
  };

  // Create/Edit functionality
  const handleCreateClick = () => {
    setEditingItem(null);
    setShowItemModal(true);
  };

  const handleEditClick = () => {
    if (selectedItem) {
      const item = filteredData.find(dataItem => dataItem[itemIdProp] === selectedItem);
      if (item) {
        // Backend now consistently returns availability as object
        setEditingItem(item);
        setShowItemModal(true);
      }
    }
  };

  const handleItemSubmit = async (formData) => {
    if (!apiService) return;

    try {
      setIsSaving(true);

      if (editingItem) {
        // Update existing item
        await apiService.update(editingItem[itemIdProp], formData);
      } else {
        // Create new item
        await apiService.create(formData);
      }

      // Clear cache and refetch data
      branchDataCache.current.clear();
      setLastFetchedBranchId(null);
      await fetchData(true); // Force refresh

      // Call change handler
      onItemChange();

      // Close modal
      setShowItemModal(false);
      setEditingItem(null);
    } catch (error) {
      console.error(`Failed to save ${title.toLowerCase()}:`, error);
      // Show toast for save errors
      setToastMessage(error.message || `שגיאה בשמירת ${title.toLowerCase()}`);
      setShowToast(true);
    } finally {
      setIsSaving(false);
    }
  };

  const handleItemModalClose = () => {
    setShowItemModal(false);
    setEditingItem(null);
  };

  // Handle selection change - DataTable passes full item, we need just the ID
  const handleSelectionChange = (item) => {
    // Extract the ID from the item object and pass it to setSelectedItem
    const itemId = item ? item[itemIdProp] : null;
    setSelectedItem(itemId);
  };

  // Determine data source and loading/error states
  const dataToUse = apiData;
  const loading = externalLoading || apiLoading;
  const error = externalError; // Don't show API errors inline anymore

  // Filter data based on search term
  const filteredData = useMemo(() => {
    if (!searchTerm) return dataToUse;

    const term = searchTerm.toLowerCase();
    return dataToUse.filter(item =>
      item[itemNameProp].toLowerCase().includes(term)
    );
  }, [dataToUse, searchTerm, itemNameProp]);

  const selectedItemData = selectedItem ?
    filteredData.find(item => item[itemIdProp] === selectedItem) : null;

  return (
    <Card className="h-full flex flex-col" padding="none">
      <div className="flex-shrink-0 p-4 border-b border-gray-200">
        {/* Title */}
        <div className="mb-4">
          <h2 className="text-lg text-neutral-800 font-bold">{title}</h2>
        </div>

        {/* Search Bar and CED Buttons */}
        <div className="flex items-center gap-4">
          {/* Search Bar - Flexible width */}
          <div className="flex-1 mr-4">
            <SearchBar
              value={searchTerm}
              onChange={setSearchTerm}
              placeholder={strings.search_placeholder || 'חפש...'}
            />
          </div>

          {/* CED Buttons */}
          <div className="flex gap-2 flex-shrink-0">
            <ActionButton
              icon={TrashIcon}
              variant="error"
              disabled={!selectedItem}
              onClick={handleDeleteClick}
              title={strings.delete || 'מחק'}
            />
            <ActionButton
              icon={PencilIcon}
              variant="secondary"
              disabled={!selectedItem}
              onClick={handleEditClick}
              title={strings.edit || 'ערוך'}
            />
            <ActionButton
              icon={PlusIcon}
              variant="primary"
              onClick={handleCreateClick}
              title={strings.create || 'צור חדש'}
            />
          </div>
        </div>
      </div>

      <div className="flex-1 p-8 pt-6 min-h-0">
        <DataTable
          columns={columns}
          data={filteredData}
          selectedId={selectedItem}
          onSelectionChange={handleSelectionChange}
          loading={loading}
          error={error}
          emptyMessage={strings.no_items || 'אין פריטים להצגה'}
        />
      </div>

      {/* Delete Confirmation Modal */}
      <ConfirmationModal
        isOpen={showDeleteModal}
        onClose={handleDeleteCancel}
        onConfirm={handleDeleteConfirm}
        title={strings.delete_title || 'מחיקת פריט'}
        message={`${strings.delete_message_prefix || 'האם אתה בטוח שברצונך למחוק את'} "${
          selectedItemData ? selectedItemData[itemNameProp] || 'פריט לא ידוע' : ''
        }"? ${strings.delete_message_suffix || 'פעולה זו לא ניתן לבטלה.'}`}
        confirmText={strings.delete_confirm || 'כן, מחק'}
        cancelText={strings.cancel || 'ביטול'}
        loading={isDeleting}
      />

      {/* Create/Edit Modal */}
      {Modal && (
        <Modal
          isOpen={showItemModal}
          onClose={handleItemModalClose}
          onSave={handleItemSubmit}
          {...{[editingItemProp]: editingItem}}
          branches={branches}
          productGroups={productGroups}
          strings={strings}
          loading={isSaving}
        />
      )}

      {/* Toast for API errors */}
      <Toast
        message={toastMessage}
        type="error"
        isVisible={showToast}
        onClose={() => setShowToast(false)}
        duration={3000}
        position="top-right"
      />
    </Card>
  );
};

export default DataSection;