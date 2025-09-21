import React, { useState, useMemo, useEffect, useCallback, useRef } from 'react';
import { Card, TableHeader, SearchBar, DataTable, ConfirmationModal } from './index';

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
  itemNameProp = 'name'
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

  // Stable fetch function with useCallback
  const fetchData = useCallback(async () => {
    // Skip if we already have data for this branch and it hasn't changed
    if (lastFetchedBranchId === selectedBranchId && branchDataCache.current.has(selectedBranchId)) {
      return;
    }

    if (data.length > 0) {
      // Use provided data if available
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
      setApiError(error.message || `Failed to load ${title.toLowerCase()}`);
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

    try {
      setIsDeleting(true);
      await apiService.delete(selectedItem);

      // Clear selection
      setSelectedItem(null);

      // Clear cache and refetch data
      branchDataCache.current.clear();
      setLastFetchedBranchId(null);
      await fetchData();

      // Call change handler
      onItemChange();

      // Close modal
      setShowDeleteModal(false);
    } catch (error) {
      console.error(`Failed to delete ${title.toLowerCase()}:`, error);
      setApiError(error.message || `Failed to delete ${title.toLowerCase()}`);
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
      await fetchData();

      // Call change handler
      onItemChange();

      // Close modal
      setShowItemModal(false);
      setEditingItem(null);
    } catch (error) {
      console.error(`Failed to save ${title.toLowerCase()}:`, error);
      setApiError(error.message || `Failed to save ${title.toLowerCase()}`);
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
  const error = externalError || apiError;

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
      <div className="flex-shrink-0 p-6 border-b border-gray-200">
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

        <SearchBar
          value={searchTerm}
          onChange={setSearchTerm}
          placeholder={strings.search_placeholder || 'חפש...'}
        />
      </div>

      <div className="flex-1 p-6 pt-4 min-h-0">
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
          onSubmit={handleItemSubmit}
          {...{[editingItemProp]: editingItem}}
          branches={branches}
          strings={strings}
          loading={isSaving}
        />
      )}
    </Card>
  );
};

export default DataSection;