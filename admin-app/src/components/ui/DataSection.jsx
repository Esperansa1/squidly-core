import React, { useState, useMemo, useEffect, useCallback, useRef } from 'react';
import { Card, TableHeader, SearchBar, DataTable, ConfirmationModal, Toast } from './index';

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
      setApiData(branchDataCache.current.get(selectedBranchId));
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
      const dataArray = response.data || response || [];

      // Cache the data for this branch
      branchDataCache.current.set(selectedBranchId, dataArray);
      setApiData(dataArray);
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
        // Convert availability array to object format expected by modal
        let availabilityObj = {};
        if (Array.isArray(item.availability)) {
          item.availability.forEach((isAvailable, index) => {
            availabilityObj[index] = isAvailable;
          });
        } else if (typeof item.availability === 'object') {
          availabilityObj = item.availability;
        }

        // Create the item object with converted availability
        const itemForEdit = {
          ...item,
          availability: availabilityObj
        };

        setEditingItem(itemForEdit);
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
      <div className="flex-shrink-0 p-8 border-b border-gray-200">
        <div className="mb-6">
          <TableHeader
            title={title}
            onCreateClick={handleCreateClick}
            onEditClick={handleEditClick}
            onDeleteClick={handleDeleteClick}
            hasSelectedItem={!!selectedItem}
            strings={{
              create: strings.create || 'צור חדש',
              edit: strings.edit || 'ערוך',
              delete: strings.delete || 'מחק'
            }}
          />
        </div>

        <SearchBar
          value={searchTerm}
          onChange={setSearchTerm}
          placeholder={strings.search_placeholder || 'חפש...'}
        />
      </div>

      <div className="flex-1 p-8 pt-6 min-h-0">
        <DataTable
          columns={columns}
          data={filteredData}
          selectedId={selectedItem}
          onSelectionChange={setSelectedItem}
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